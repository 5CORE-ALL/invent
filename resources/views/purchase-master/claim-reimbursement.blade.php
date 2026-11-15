@extends('layouts.vertical', ['title' => 'Claim & Reimbursement'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="{{ asset('css/select-searchable.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Custom styles for the Tabulator table */
    /* Pagination styling */
    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    /* Communication / platform dropdown icons (matches MFRG In-Progress) */
    .mip-plat-icon-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: transform 0.12s;
    }
    .mip-plat-icon-link:hover {
        transform: scale(1.2);
    }

    /* Allow the communication dropdown to overflow Tabulator's clipped cells */
    .tabulator .tabulator-cell.comm-cell {
        overflow: visible !important;
    }
    .tabulator-row {
        overflow: visible !important;
    }

    /* Read-only view (non-directors): select2 boxes must not open */
    #claimModal #claimFieldset:disabled .select2-container { pointer-events: none; opacity: .85; }
    #claimModal #claimFieldset:disabled #addRowBtn { display: none; }
    /* Delete button stays visible for everyone but is only active for directors */
    #claimModal .remove-row:disabled { background: #eef2f7; color: #94a3b8; cursor: not-allowed; opacity: .8; transform: none; }

    /* Claims Pending badge */
    .badge.claims-pending-badge {
        display: inline-flex; align-items: center;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: .82rem; font-weight: 700;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        box-shadow: 0 4px 12px rgba(217, 119, 6, .18);
    }
    .badge.cp-count-badge { background: linear-gradient(120deg, #fff7e0, #ffe9b8); border: 1px solid #f5c969; color: #92400e; }
    .badge.cp-count-badge i { color: #d97706; }
    .badge.cp-amount-badge { background: linear-gradient(120deg, #d97706, #b45309); border: 1px solid #b45309; color: #fff; }

    /* Action section (now inside the claim modal): always a light yellow background */
    #claimModal .claim-action-card {
        background: #fffbe0 !important;
        border-color: #f3e8a3;
        margin-top: 20px;
    }
    #claimModal .claim-action-card .claim-section-title i { color: #d97706; }
    #claimModal .claim-action-card .table { background: #fff; border-radius: 10px; overflow: hidden; margin-bottom: 0; }
    #claimModal .claim-action-card .table thead { background: #f1f5fb; }
    #claimModal .claim-recd-card { margin-top: 20px; background: #f0fdf4 !important; border-color: #bbf7d0; }
    #claimModal .claim-recd-card .claim-section-title i { color: #16a34a; }
    #claimModal .claim-recd-card #claimRecdInput { border-radius: 12px 0 0 12px; background: #fff; resize: vertical; }
    #claimModal .claim-recd-card .btn-claim-save { border-radius: 0 12px 12px 0; padding: 8px 20px; }
    .recd-amt-btn.recd-done { color: #16a34a; border: 1px solid #16a34a; background: #f0fdf4; border-radius: 8px; padding: 2px 10px; font-size: 16px; line-height: 1.4; }
    .recd-amt-btn.recd-done:hover { background: #dcfce7; }
    #claimModal .claim-action-card .btn-claim-save { border-radius: 0 12px 12px 0; padding: 8px 20px; }
    #claimModal .claim-action-card #claimActionInput { border-radius: 12px 0 0 12px; background: #fff; }

    /* ===== Next-gen Add Claim modal ===== */
    #claimModal .modal-content {
        border: 0;
        border-radius: 0;
        background: #f4f6fb;
    }
    #claimModal .claim-header {
        background: linear-gradient(120deg, #0ea5a4 0%, #2563eb 55%, #7c3aed 100%);
        color: #fff;
        border: 0;
        padding: 18px 28px;
        align-items: center;
    }
    #claimModal .claim-header .claim-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .18);
        backdrop-filter: blur(6px);
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 20px;
        margin-right: 14px;
    }
    #claimModal .claim-header .modal-title { font-weight: 700; letter-spacing: .2px; }
    #claimModal .claim-header .claim-sub { font-size: .8rem; opacity: .85; }
    #claimModal .claim-close {
        width: 36px; height: 36px;
        border: 0; border-radius: 10px;
        background: rgba(255, 255, 255, .18);
        color: #fff; font-size: 16px;
        transition: background .15s, transform .15s;
    }
    #claimModal .claim-close:hover { background: rgba(255, 255, 255, .32); transform: rotate(90deg); }

    #claimModal .modal-body { padding: 24px 28px; }
    #claimModal .claim-card {
        background: #fff;
        border: 1px solid #e6eaf2;
        border-radius: 16px;
        box-shadow: 0 4px 18px rgba(30, 41, 90, .06);
        padding: 20px 22px;
    }
    #claimModal .claim-card + .claim-card { margin-top: 20px; }
    #claimModal .claim-section-title {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .08em;
        color: #64748b; margin-bottom: 12px;
        display: flex; align-items: center; gap: 8px;
    }
    #claimModal .claim-section-title i { color: #2563eb; }
    #claimModal .form-label {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .06em;
        color: #64748b;
    }
    #claimModal .form-control,
    #claimModal .form-select {
        border-radius: 10px;
        border: 1px solid #dbe2ee;
        background-color: #f8fafc;
        min-height: 40px;
        font-size: .9rem;
        transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    #claimModal .form-control:focus,
    #claimModal .form-select:focus {
        background-color: #fff;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }
    #claimModal .form-control[readonly] { background-color: #eef2f7; color: #475569; }
    #claimModal #claimNo {
        font-weight: 700; color: #2563eb;
        font-variant-numeric: tabular-nums;
    }

    /* Items table */
    #claimModal .claim-table-wrap { border-radius: 12px; border: 1px solid #e6eaf2; overflow: hidden; }
    #claimModal #claimTable { margin: 0; border-collapse: separate; border-spacing: 0; }
    #claimModal #claimTable > :not(caption) > * > * { border-width: 0 0 1px 0; border-color: #eef1f6; padding: 10px 8px; }
    #claimModal #claimTable thead th {
        background: #f1f5fb;
        color: #475569;
        font-size: .7rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .07em;
        padding: 12px 8px;
        white-space: nowrap;
    }
    #claimModal #claimTable tbody tr { transition: background .15s; }
    #claimModal #claimTable tbody tr:hover { background: #f8faff; }
    #claimModal #claimTable tbody tr:last-child td { border-bottom: 0; }
    #claimModal #claimTable .amount { font-weight: 700; color: #059669; text-align: center; }
    #claimModal #claimTable .qty,
    #claimModal #claimTable .rate { text-align: center; }
    #claimModal #claimTable .row-parent { font-weight: 600; text-align: center; }
    /* SKU column: 60% of the previous width (220px -> 132px) */
    #claimModal #claimTable th.col-sku,
    #claimModal #claimTable td.col-sku {
        width: 132px; min-width: 132px; max-width: 132px;
        padding-left: 4px; padding-right: 4px;
    }
    #claimModal #claimTable td.col-sku .select2-container { width: 100% !important; }
    #claimModal #claimTable td.col-sku .select2-selection--single .select2-selection__rendered {
        padding-left: 8px; padding-right: 36px; font-size: .85rem;
    }

    /* Supplier column: 50% of the previous width (180px -> 90px) */
    #claimModal #claimTable th.col-supplier,
    #claimModal #claimTable td.col-supplier {
        width: 90px; min-width: 90px; max-width: 90px;
        padding-left: 4px; padding-right: 4px;
    }
    #claimModal #claimTable td.col-supplier .select2-container { width: 100% !important; }
    #claimModal #claimTable td.col-supplier .select2-selection--single .select2-selection__rendered {
        padding-left: 8px; padding-right: 30px; font-size: .85rem;
    }

    /* Parent column: half the previous width (160px -> 80px) */
    #claimModal #claimTable th.col-parent,
    #claimModal #claimTable td.col-parent {
        width: 80px; min-width: 80px; max-width: 80px;
        padding-left: 4px; padding-right: 4px;
    }
    #claimModal #claimTable td.col-parent .row-parent {
        width: 100%; padding: 6px 6px; font-size: .8rem;
        text-overflow: ellipsis;
    }

    /* Compact numeric columns: Qty / Rate = 4 characters, Amount = 6 characters */
    #claimModal #claimTable th.col-num4,
    #claimModal #claimTable td.col-num4 {
        width: calc(4ch + 22px); min-width: calc(4ch + 22px); max-width: calc(4ch + 22px);
        padding-left: 4px; padding-right: 4px;
    }
    #claimModal #claimTable th.col-num6,
    #claimModal #claimTable td.col-num6 {
        width: calc(6ch + 22px); min-width: calc(6ch + 22px); max-width: calc(6ch + 22px);
        padding-left: 4px; padding-right: 4px;
    }
    #claimModal #claimTable th.col-num4,
    #claimModal #claimTable th.col-num6 { white-space: normal; line-height: 1.2; font-size: .62rem; }
    #claimModal #claimTable td.col-num4 .form-control,
    #claimModal #claimTable td.col-num6 .form-control {
        width: 100%;
        padding: 6px 4px;
        text-align: center;
        font-variant-numeric: tabular-nums;
        -moz-appearance: textfield;
    }
    #claimModal #claimTable td.col-num4 .form-control::-webkit-outer-spin-button,
    #claimModal #claimTable td.col-num4 .form-control::-webkit-inner-spin-button,
    #claimModal #claimTable td.col-num6 .form-control::-webkit-outer-spin-button,
    #claimModal #claimTable td.col-num6 .form-control::-webkit-inner-spin-button {
        -webkit-appearance: none; margin: 0;
    }

    /* Screenshot: camera button -> thumbnail, large preview on hover */
    #claimModal #claimTable th.col-shot,
    #claimModal #claimTable td.col-shot { width: 72px; min-width: 72px; max-width: 72px; padding-left: 4px; padding-right: 4px; }
    #claimModal #claimTable th.col-shot { white-space: normal; font-size: .62rem; }
    #claimModal .claim-shot {
        position: relative;
        width: 40px; height: 40px;
        margin: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px;
        border: 1px solid #dbe2ee;
        background: #f8fafc;
        color: #64748b;
        font-size: 16px;
        cursor: pointer;
        overflow: hidden;
        transition: all .15s;
    }
    #claimModal .claim-shot-paste { border: 1px solid #cbd5e1; background: #fff; color: #2563eb; border-radius: 6px; padding: 0 5px; font-size: 11px; line-height: 18px; cursor: pointer; }
    #claimModal .claim-shot-paste:hover { background: #eaf2ff; border-color: #2563eb; }
    #claimShotToast { position: fixed; left: 50%; bottom: 90px; transform: translateX(-50%); z-index: 4000; max-width: 460px; background: #0f172a; color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; box-shadow: 0 8px 24px rgba(0,0,0,.25); display: none; text-align: center; }
    #claimModal .claim-shot:focus, #claimModal .claim-shot.shot-selected { outline: none; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .25); }
    #claimModal .claim-shot:hover { border-color: #2563eb; color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
    #claimModal .claim-shot.has-shot { border-color: #22c55e; }
    #claimModal #claimTable tr.claim-drop-over > td,
    #claimModal #claimTable.claim-drop-over { background: #eaf2ff !important; }
    #claimModal #claimTable tr.claim-drop-over .claim-shot { border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .18); }
    #claimModal .claim-shot .claim-shot-thumb { width: 100%; height: 100%; object-fit: cover; }
    #claimModal .claim-shot input[type="file"] {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        opacity: 0; cursor: pointer; pointer-events: none;
        font-size: 0; padding: 0; min-height: 0;
    }
    #scPreview {
        display: none;
        position: fixed;
        z-index: 3000;
        pointer-events: none;
        padding: 6px;
        background: #fff;
        border: 1px solid #c7d4ee;
        border-radius: 12px;
        box-shadow: 0 20px 50px rgba(15, 23, 42, .4);
    }
    #scPreview img {
        display: block;
        max-width: min(560px, 70vw);
        max-height: 70vh;
        border-radius: 8px;
    }

    /* Reason / Notes: icon button that opens an editor modal */
    #claimModal #claimTable td.col-reason { min-width: 240px; }
    #claimModal .reason-btn {
        width: 100%; min-height: 40px;
        padding: 6px 12px;
        display: flex; align-items: center; gap: 10px;
        text-align: left;
        border-radius: 10px;
        border: 1px solid #dbe2ee;
        background: #f8fafc;
        color: #94a3b8;
        font-size: .88rem;
        transition: all .15s;
    }
    #claimModal .reason-btn i { color: #64748b; font-size: 15px; flex-shrink: 0; }
    #claimModal .reason-btn .reason-text {
        flex: 1; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    #claimModal .reason-btn:hover { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 4px rgba(37, 99, 235, .1); }
    #claimModal .reason-btn:hover i { color: #2563eb; }
    #claimModal .reason-btn.has-text { background: #eef4ff; border-color: #bcd0fb; color: #1e293b; }
    #claimModal .reason-btn.has-text i { color: #2563eb; }

    #reasonModal .reason-modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 60px rgba(15, 23, 42, .35); }
    #reasonModal .reason-modal-header {
        background: linear-gradient(120deg, #0ea5a4 0%, #2563eb 55%, #7c3aed 100%);
        color: #fff; border: 0; padding: 14px 20px;
    }
    #reasonModal .reason-modal-header .modal-title { font-weight: 700; font-size: 1.05rem; }
    #reasonModal .claim-close {
        width: 34px; height: 34px; border: 0; border-radius: 10px;
        background: rgba(255, 255, 255, .18); color: #fff;
        transition: background .15s, transform .15s;
    }
    #reasonModal .claim-close:hover { background: rgba(255, 255, 255, .32); transform: rotate(90deg); }
    #reasonModal textarea.form-control {
        border-radius: 12px; border: 1px solid #dbe2ee; background: #f8fafc; resize: vertical;
    }
    #reasonModal textarea.form-control:focus {
        background: #fff; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }
    #reasonModal .btn-claim-save {
        background: linear-gradient(120deg, #2563eb, #7c3aed);
        border: 0; color: #fff; font-weight: 700;
        border-radius: 12px; padding: 9px 24px;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .3);
    }
    #reasonModal .btn-claim-save:hover { color: #fff; transform: translateY(-1px); }
    #reasonModal .btn-claim-cancel { background: #eef2f7; border: 0; color: #475569; border-radius: 12px; padding: 9px 18px; font-weight: 600; }
    #reasonModal .btn-claim-cancel:hover { background: #e2e8f0; }

    /* Attachment: icon-only picker */
    #claimModal #claimTable th.col-attach,
    #claimModal #claimTable td.col-attach { width: 64px; min-width: 64px; max-width: 64px; padding-left: 4px; padding-right: 4px; }
    #claimModal .claim-attach {
        position: relative;
        width: 38px; height: 38px;
        margin: 0;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 10px;
        border: 1px solid #dbe2ee;
        background: #f8fafc;
        color: #64748b;
        font-size: 16px;
        cursor: pointer;
        transition: all .15s;
    }
    #claimModal .claim-attach:hover { background: #2563eb; border-color: #2563eb; color: #fff; }
    #claimModal .claim-attach input[type="file"] {
        position: absolute; inset: 0;
        width: 100%; height: 100%;
        opacity: 0; cursor: pointer;
        font-size: 0; padding: 0; min-height: 0;
    }
    #claimModal .claim-attach.has-file { background: #dcfce7; border-color: #22c55e; color: #15803d; }
    #claimModal .claim-attach.has-file::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free'; font-weight: 900;
        position: absolute; top: -6px; right: -6px;
        width: 16px; height: 16px; border-radius: 50%;
        background: #22c55e; color: #fff;
        font-size: 9px;
        display: flex; align-items: center; justify-content: center;
    }
    #claimModal .remove-row {
        width: 34px; height: 34px; padding: 0;
        border-radius: 10px; border: 0;
        background: #fee2e2; color: #dc2626;
        transition: background .15s, color .15s, transform .15s;
    }
    #claimModal .remove-row:hover { background: #dc2626; color: #fff; transform: scale(1.06); }

    #claimModal #addRowBtn {
        border: 1.5px dashed #93b4f5;
        background: #f3f7ff;
        color: #2563eb;
        border-radius: 10px;
        font-weight: 600;
        padding: 8px 16px;
        transition: all .15s;
    }
    #claimModal #addRowBtn:hover { background: #2563eb; color: #fff; border-style: solid; }

    /* Select2 inside the modal */
    #claimModal .select2-container--default .select2-selection--single {
        height: 40px;
        border-radius: 10px;
        border: 1px solid #dbe2ee;
        background-color: #f8fafc;
        display: flex; align-items: center;
    }
    #claimModal .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px; padding-left: 12px; font-size: .9rem; color: #1e293b;
    }
    #claimModal .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }
    #claimModal .select2-container--default.select2-container--focus .select2-selection--single,
    #claimModal .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        background-color: #fff;
    }

    /* Wide, tall dropdown panels (SKU + Supplier) that open downward */
    .select2-container--open .select2-dropdown.claim-select-dropdown {
        min-width: 460px;
        width: auto !important;
        max-width: calc(100vw - 48px);
        border: 1px solid #c7d4ee;
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(30, 41, 90, .22);
        overflow: hidden;
        z-index: 2000;
    }
    .claim-select-dropdown .select2-search--dropdown { padding: 12px; background: #f1f5fb; }
    .claim-select-dropdown .select2-search--dropdown .select2-search__field {
        border-radius: 10px;
        border: 1px solid #c7d4ee;
        padding: 9px 12px;
        font-size: .95rem;
        outline: 0;
    }
    .claim-select-dropdown .select2-search--dropdown .select2-search__field:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
    }
    .claim-select-dropdown .select2-results__options {
        max-height: max(260px, calc(100vh - 400px)) !important;
    }
    .claim-select-dropdown .select2-results__option {
        padding: 9px 16px;
        font-size: .92rem;
        white-space: nowrap;
    }
    .claim-select-dropdown .select2-results__option--highlighted[aria-selected],
    .claim-select-dropdown .select2-results__option--highlighted.select2-results__option--selectable {
        background: #2563eb !important;
        color: #fff !important;
    }

    /* Footer */
    #claimModal .claim-footer {
        background: #fff;
        border-top: 1px solid #e6eaf2;
        padding: 14px 28px;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 -6px 20px rgba(30, 41, 90, .05);
    }
    #claimModal .claim-total {
        display: flex; align-items: baseline; gap: 10px;
    }
    #claimModal .claim-total .label {
        font-size: .72rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .08em; color: #64748b;
    }
    #claimModal .claim-total .value {
        font-size: 1.7rem; font-weight: 800; color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    #claimModal .claim-total .value small { color: #2563eb; font-size: 1rem; margin-right: 2px; }
    #claimModal .btn-claim-save {
        background: linear-gradient(120deg, #2563eb, #7c3aed);
        border: 0; color: #fff; font-weight: 700;
        border-radius: 12px; padding: 10px 26px;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .3);
        transition: transform .15s, box-shadow .15s;
    }
    #claimModal .btn-claim-save:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(37, 99, 235, .38); color: #fff; }
    #claimModal .btn-claim-cancel {
        background: #eef2f7; border: 0; color: #475569;
        border-radius: 12px; padding: 10px 20px; font-weight: 600;
    }
    #claimModal .btn-claim-cancel:hover { background: #e2e8f0; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Claim & Reimbursement', 'sub_title' => 'Claim & Reimbursement'])

@if(Session::has('flash_message'))
<div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert" style="background-color: #169e28 !important; color: #fff !important;">
    {{ Session::get('flash_message') }}
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <h4 class="mb-0">Claim & Reimbursement</h4>
                        {{-- Two real .badge elements: Auto Task Events (ATE) reads them as "Claims Pending" and "Total Amt". --}}
                        <span class="d-inline-flex align-items-center gap-2 flex-wrap">
                            <span id="claimsPendingCountBadge" class="badge claims-pending-badge cp-count-badge" title="Active claims that are not archived / resolved">
                                <i class="fas fa-hourglass-half me-1"></i>Claims Pending: <span id="claimsPendingCount">-</span>
                            </span>
                            <span id="claimsPendingAmountBadge" class="badge claims-pending-badge cp-amount-badge" title="Total amount of the pending claims">
                                Total Amt: $<span id="claimsPendingAmount">-</span>
                            </span>
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'claim_reimbursement'])
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#claimModal">
                        <i class="fas fa-plus-circle me-1"></i>Add Claim / Reimbursement
                    </button>
                    </div>
                </div>
                <div class="row mb-3 align-items-end">
                    <div class="col-md-4 col-lg-3">
                        <label for="supplier-filter" class="form-label fw-semibold mb-1">Suppler</label>
                        <select id="supplier-filter" class="form-select form-select-sm select-searchable">
                            <option value="">All Supplers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show-archived-filter">
                            <label class="form-check-label fw-semibold" for="show-archived-filter">
                                Show archived (all claims)
                            </label>
                        </div>
                    </div>
                </div>

@php $canArchive = $canArchive ?? false; @endphp
<script>
    window.CLAIM_CAN_ARCHIVE = @json($canArchive);
    window.CLAIM_SUPPLIER_OPTIONS = '<option value="">Select Supplier</option>' + @json($suppliers->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values())
        .map(function (s) { return '<option value="' + s.id + '">' + String(s.name).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;') + '</option>'; })
        .join('');
</script>
                <div id="claim-reimbursement-table"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="claimModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content">
      <div class="modal-header claim-header">
        <div class="d-flex align-items-center">
          <span class="claim-icon"><i class="fas fa-file-invoice-dollar"></i></span>
          <div>
            <h5 class="modal-title mb-0" id="claimModalLabel">Add Claim / Reimbursement</h5>
            <div class="claim-sub">Log damaged, missing or overbilled items against a supplier</div>
          </div>
        </div>
        <button type="button" class="claim-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>

      <div class="modal-body">
        <form id="claimForm" action="{{ route('claim.reimbursement.save') }}" method="POST" enctype="multipart/form-data">
            @csrf
          <fieldset id="claimFieldset">
          <div class="claim-card">
            <div class="row g-3">
              <div class="col-md-6">
                <label for="claimNo" class="form-label">Claim No.</label>
                <input type="text" id="claimNo" name="claim_number" class="form-control" value="{{ $claimNumber }}" readonly>
              </div>
              <div class="col-md-6">
                <label for="claimDate" class="form-label">Date</label>
                <input type="date" id="claimDate" name="claim_date" class="form-control" required>
              </div>
            </div>
          </div>

          <div class="claim-card">
          <div class="claim-section-title"><i class="fas fa-boxes-stacked"></i> Items</div>
          <!-- Dynamic Item Table -->
          <div class="table-responsive claim-table-wrap">
            <table class="table table-bordered align-middle text-center" id="claimTable">
              <thead class="table-light">
                <tr>
                  <th class="col-parent">Parent</th>
                  <th class="col-sku">SKU</th>
                  <th class="col-supplier">Supplier</th>
                  <th class="col-num4">Qty</th>
                  <th class="col-num4">Rate USD</th>
                  <th class="col-num6">Amount</th>
                  <th class="col-reason" style="min-width: 240px;">Reason/Notes</th>
                  <th class="col-attach" title="Image (if any)"><i class="fas fa-paperclip"></i></th>
                  <th class="col-shot">Screenshot <button type="button" id="claimShotPasteBtn" class="claim-shot-paste" title="Paste a copied screenshot / snippet (or press Cmd/Ctrl+V)"><i class="fas fa-paste"></i></button></th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="claimTableBody">
                <!-- Default row -->
                <tr>
                  <td class="col-parent"><input type="text" name="row_parent[]" class="form-control row-parent" readonly placeholder="Auto"></td>
                  <td class="col-sku"><select name="item[]" class="form-control sku-select" required style="width: 100%;"><option value="">Search SKU...</option></select></td>
                  <td class="col-supplier">
                    <select name="row_supplier[]" class="form-select row-supplier" required>
                      <option value="">Select Supplier</option>
                      @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td class="col-num4"><input type="number" name="qty[]" class="form-control qty" required></td>
                  <td class="col-num4"><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                  <td class="col-num6"><input type="number" name="amount[]" class="form-control amount" readonly></td>
                  <td class="col-reason"><input type="hidden" name="reason[]" class="reason-value"><button type="button" class="btn reason-btn" title="Add reason / notes"><i class="fas fa-pen-to-square"></i><span class="reason-text">Add reason / notes</span></button></td>
                  <td class="col-attach"><input type="hidden" name="existing_image[]" class="existing-image"><label class="claim-attach" title="Attach image / PDF"><i class="fas fa-paperclip"></i><input type="file" name="image[]" class="claim-attach-input" accept=".jpg,.jpeg,.png,.pdf"></label></td>
                  <td class="col-shot"><input type="hidden" name="existing_screenshot[]" class="existing-screenshot"><label class="claim-shot" tabindex="0" title="Click to select, then press Cmd/Ctrl+V to paste a snippet. Double-click to choose a file."><i class="fas fa-camera"></i><img class="claim-shot-thumb" alt="" style="display:none;"><input type="file" name="screenshot[]" class="claim-shot-input" accept="image/*"></label></td>
                  <td><button type="button" class="btn remove-row" title="Remove row"><i class="fas fa-trash-can"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            <button type="button" class="btn btn-sm" id="addRowBtn"><i class="fas fa-plus me-1"></i> Add Row</button>
          </div>
          </div>
          </fieldset>
        </form>

        {{-- Received amount / goods. Shown when an existing claim is opened. --}}
        <div class="claim-card claim-recd-card" id="claimRecdCard" style="display: none;">
          <div class="claim-section-title"><i class="fas fa-hand-holding-dollar"></i> Recd Amt/Goods $</div>
          <div class="input-group">
            <textarea id="claimRecdInput" class="form-control" rows="2" placeholder="Enter received amount / goods details..."></textarea>
            <button type="button" class="btn btn-claim-save" id="claimRecdSaveBtn"><i class="fas fa-check me-1"></i> Save</button>
          </div>
        </div>

        {{-- Action section (claim history + new action). Shown when an existing claim is opened. --}}
        <div class="claim-card claim-action-card" id="claimActionCard" style="display: none;">
          <div class="claim-section-title"><i class="fas fa-bolt"></i> Action</div>
          <div id="claimActionHistory"></div>
          <div class="input-group mt-3">
            <input type="text" id="claimActionInput" class="form-control" placeholder="Action being taken..." autocomplete="off">
            <button type="button" class="btn btn-claim-save" id="claimActionAddBtn"><i class="fas fa-plus me-1"></i> Add Action</button>
          </div>
        </div>
      </div>

      <div class="claim-footer">
        <div class="claim-total">
          <span class="label">Total Amount</span>
          <span class="value"><small>$</small><span id="totalAmount">0.00</span></span>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-claim-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="claimSaveBtn" form="claimForm" class="btn btn-claim-save"><i class="fas fa-check me-1"></i> Save Claim</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Reason / Notes editor (opened from the claim modal rows) --}}
<div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true" style="z-index: 1070;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content reason-modal-content">
      <div class="modal-header reason-modal-header">
        <h5 class="modal-title" id="reasonModalLabel"><i class="fas fa-pen-to-square me-2"></i>Reason / Notes</h5>
        <button type="button" class="claim-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2" id="reasonModalSku"></div>
        <textarea id="reasonModalInput" class="form-control" rows="5" placeholder="Describe the reason for this claim (damaged, missing, overbilled...)"></textarea>
        <div class="small text-muted mt-2">Tip: press Ctrl/Cmd + Enter to save.</div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-claim-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-claim-save" id="reasonModalSave"><i class="fas fa-check me-1"></i> Save</button>
      </div>
    </div>
  </div>
</div>

{{-- Action History Modal --}}
<div class="modal fade" id="actionHistoryModal" tabindex="-1" aria-labelledby="actionHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="actionHistoryModalLabel"><i class="fas fa-history me-2"></i>Action being taken</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="action_claim_id">
        <div class="mb-2">
          <label for="action_note_input" class="form-label fw-semibold">Action being taken</label>
          <textarea id="action_note_input" class="form-control" rows="3" placeholder="Enter action being taken..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveActionBtn"><i class="fas fa-plus me-1"></i> Add</button>
      </div>
    </div>
  </div>
</div>

{{-- Details Note Modal --}}
<div class="modal fade" id="detailsNoteModal" tabindex="-1" aria-labelledby="detailsNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="detailsNoteModalLabel">Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="details_note_claim_id">
        <div class="mb-2">
          <label for="details_note_input" class="form-label fw-semibold">Details</label>
          <textarea id="details_note_input" class="form-control" rows="4" placeholder="Enter details..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveDetailsNoteBtn">Save</button>
      </div>
    </div>
  </div>
</div>


@endsection

@section('script')
<script src="{{ asset('js/select-searchable.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const supplierFilter = document.getElementById('supplier-filter');
        const table = new Tabulator("#claim-reimbursement-table", {
            ajaxURL: "/claim-reimbursement/view-data",
            ajaxConfig: "GET",
            layout: "fitColumns",
            height: "500px",
            resizableColumns: true,
            pagination: "remote", 
            paginationSize: 10,
            columnDefaults: {
                headerHozAlign: "center",
                resizable: true,
            },
            columns: [
                { title: "Claim Number", field: "claim_number", hozAlign: "center", minWidth: 130, visible: false },
                {
                    title: "Supplier",
                    field: "supplier_name",
                    hozAlign: "center",
                    width: 72,
                    minWidth: 56,
                    maxWidth: 96,
                    widthGrow: 0,
                    formatter: function (cell) {
                        const value = String(cell.getValue() || '').trim();
                        if (!value) return '-';
                        const first = value.split(/\s+/).filter(Boolean)[0] || value;
                        const esc = String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        const escFirst = String(first).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        return `<span title="${esc}" style="font-weight:700;font-size:0.72rem;white-space:nowrap;">${escFirst}</span>`;
                    }
                },
                {
                    title: "Claim",
                    field: "claim_date",
                    hozAlign: "center",
                    width: 65,
                    formatter: function (cell) {
                        const val = cell.getValue();
                        if (!val) return '';
                        const d = new Date(String(val).substring(0, 10) + 'T00:00:00');
                        if (isNaN(d.getTime())) return val;
                        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        return d.getDate() + ' ' + months[d.getMonth()];
                    }
                },
                {
                  title: "Details",
                  field: "details",
                  minWidth: 200,
                  widthGrow: 3,
                  headerSort: false,
                  variableHeight: true,
                  formatter: function (cell) {
                      const rowData = cell.getRow().getData();
                      return buildDetailsInline(cell.getValue(), rowData.details_note);
                  },
                  cellClick: function (e, cell) {
                      if (e.target.closest('.details-note-btn')) {
                          const rowData = cell.getRow().getData();
                          openDetailsNoteModal(rowData.id, rowData.details_note);
                      }
                  },
                  hozAlign: "left"
                },
                {
                    title: "Amt $",
                    field: "total_amount",
                    hozAlign: "center",
                    width: 70,
                    formatter: function (cell) {
                        const num = parseFloat(cell.getValue());
                        if (isNaN(num)) return '';
                        return Math.round(num).toLocaleString('en-US');
                    }
                },
                {
                    title: "Created by",
                    field: "created_by",
                    visible: false,
                    hozAlign: "center",
                    width: 85,
                    formatter: function (cell) {
                        const data = cell.getRow().getData();
                        const name = cell.getValue() || 'System';
                        const date = data.created_date ? `<div class="text-muted small">${data.created_date}</div>` : '';
                        return `<div><span class="fw-semibold">${name}</span>${date}</div>`;
                    }
                },
                {
                    title: "Recd",
                    field: "received_amount",
                    hozAlign: "center",
                    width: 80,
                    headerSort: false,
                    formatter: function (cell) {
                        const val = cell.getValue();
                        if (val && String(val).trim() !== '') {
                            return `<button class="btn btn-sm recd-amt-btn recd-done" title="${escapeHtml(val)}"><i class="fas fa-circle-check"></i></button>`;
                        }
                        return `<button class="btn btn-sm btn-outline-primary recd-amt-btn" title="Add Recd Amt/Goods"><i class="fas fa-plus"></i></button>`;
                    },
                    cellClick: function (e, cell) {
                        if (e.target.closest('.recd-amt-btn')) {
                            openClaimRecd(cell.getRow().getData());
                        }
                    }
                },
                {
                    title: '<i class="fas fa-box-archive" title="Resolved"></i>',
                    field: "is_archived",
                    hozAlign: "center",
                    headerSort: false,
                    width: 100,
                    formatter: function (cell) {
                        return buildResolvedCell(cell.getRow().getData());
                    },
                    cellClick: function (e, cell) {
                        if (e.target.closest('.edit-claim-btn')) {
                            openEditClaim(cell.getRow().getData(), false);
                            return;
                        }
                        if (e.target.closest('.view-claim-btn')) {
                            openEditClaim(cell.getRow().getData(), true);
                            return;
                        }
                        const btn = e.target.closest('.archive-btn');
                        if (btn) {
                            const d = cell.getRow().getData();
                            toggleArchive(d.id, btn.dataset.archive === '1');
                        }
                    }
                },
            ],
        });

        window.claimTable = table;

        const showArchivedFilter = document.getElementById('show-archived-filter');

        // "Claims Pending" badge: active (not archived) claims -> count + total amount.
        window.refreshClaimsPending = function () {
            const sid = supplierFilter ? (supplierFilter.value || '') : '';
            fetch('/claim-reimbursement/pending-summary' + (sid ? '?supplier_id=' + encodeURIComponent(sid) : ''), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                document.getElementById('claimsPendingCount').textContent = Number(res.count || 0).toLocaleString('en-US');
                document.getElementById('claimsPendingAmount').textContent = Math.round(Number(res.total_amount || 0)).toLocaleString('en-US');
            })
            .catch(() => {});
        };
        window.refreshClaimsPending();

        window.reloadClaimTable = function () {
            window.refreshClaimsPending();
            table.setData("/claim-reimbursement/view-data", {
                supplier_id: supplierFilter ? (supplierFilter.value || '') : '',
                show_archived: (showArchivedFilter && showArchivedFilter.checked) ? 1 : 0
            });
        };

        if (supplierFilter) {
            supplierFilter.addEventListener('change', window.reloadClaimTable);
        }
        if (showArchivedFilter) {
            showArchivedFilter.addEventListener('change', window.reloadClaimTable);
        }

        const saveDetailsNoteBtn = document.getElementById('saveDetailsNoteBtn');
        if (saveDetailsNoteBtn) {
            saveDetailsNoteBtn.addEventListener('click', function () {
                const id = document.getElementById('details_note_claim_id').value;
                const value = document.getElementById('details_note_input').value;
                const btn = this;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

                const formData = new FormData();
                formData.append('details_note', value);
                formData.append('_token', '{{ csrf_token() }}');

                fetch(`/claim-reimbursement/${id}/details-note`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const row = table.getRow(id);
                        if (row) {
                            row.update({ details_note: res.details_note });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('detailsNoteModal')).hide();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Something went wrong while saving.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        }

        const saveActionBtn = document.getElementById('saveActionBtn');
        if (saveActionBtn) {
            saveActionBtn.addEventListener('click', function () {
                const id = document.getElementById('action_claim_id').value;
                const note = document.getElementById('action_note_input').value.trim();
                if (note === '') {
                    document.getElementById('action_note_input').focus();
                    return;
                }
                const btn = this;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const formData = new FormData();
                formData.append('note', note);
                formData.append('_token', '{{ csrf_token() }}');

                fetch(`/claim-reimbursement/${id}/action`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const row = table.getRow(id);
                        if (row) {
                            row.update({ action_history: res.action_history });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('actionHistoryModal')).hide();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Something went wrong while saving.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        }

        addNewRow();

        // Open Add Claim modal when linked from QC Container (open_add=1).
        try {
            const params = new URLSearchParams(window.location.search);
            if (params.get('open_add') === '1') {
                const sku = String(params.get('sku') || '').trim();
                const supplierName = String(params.get('supplier') || '').trim();

                const supplierSelect = document.querySelector('#claimTableBody tr .row-supplier');
                if (supplierSelect && supplierName) {
                    const target = supplierName.toLowerCase();
                    let matched = '';
                    Array.from(supplierSelect.options).forEach(function (opt) {
                        if (!matched && String(opt.text || '').trim().toLowerCase() === target) {
                            matched = opt.value;
                        }
                    });
                    if (!matched) {
                        Array.from(supplierSelect.options).forEach(function (opt) {
                            const text = String(opt.text || '').trim().toLowerCase();
                            if (!matched && text && (text.includes(target) || target.includes(text))) {
                                matched = opt.value;
                            }
                        });
                    }
                    if (matched) {
                        setRowSupplier(supplierSelect, matched);
                        // Keep the supplier passed in the link when the SKU autofill runs.
                        window.CLAIM_PREFILL_SUPPLIER_ID = matched;
                    }
                }

                const modalEl = document.getElementById('claimModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();

                    if (sku) {
                        setTimeout(function () {
                            const skuSelect = document.querySelector('#claimTableBody tr .sku-select');
                            if (skuSelect && window.jQuery) {
                                const option = new Option(sku, sku, true, true);
                                jQuery(skuSelect).append(option).trigger('change');
                            }
                        }, 350);
                    }
                }

                // Clean query params so refresh doesn't re-open the modal.
                params.delete('open_add');
                const clean = window.location.pathname + (params.toString() ? ('?' + params.toString()) : '');
                window.history.replaceState({}, '', clean);
            }
        } catch (e) {
            console.warn('Unable to auto-open claim modal', e);
        }
    });

    function addNewRow() {
      const addRowBtn = document.getElementById('addRowBtn');
      const claimTableBody = document.getElementById('claimTableBody');
      const totalAmountDisplay = document.getElementById('totalAmount');

      function updateTotal() {
          let total = 0;
          document.querySelectorAll('.amount').forEach(input => {
              total += parseFloat(input.value) || 0;
          });
          totalAmountDisplay.textContent = total.toFixed(2);
      }

      function attachListeners(row) {
          const qtyInput = row.querySelector('.qty');
          const rateInput = row.querySelector('.rate');
          const amountInput = row.querySelector('.amount');

          // A real keystroke on Rate means the user owns the value now.
          rateInput.addEventListener('input', (e) => {
              if (e.isTrusted) delete rateInput.dataset.auto;
          });

          [qtyInput, rateInput].forEach(input => {
              input.addEventListener('input', () => {
                  const qty = parseFloat(qtyInput.value) || 0;
                  const rate = parseFloat(rateInput.value) || 0;
                  const amt = qty * rate;
                  amountInput.value = Math.round(amt); // rounded off to a whole number
                  updateTotal();
              });
          });

          const removeBtn = row.querySelector('.remove-row');
          removeBtn.addEventListener('click', () => {
              if (claimTableBody.querySelectorAll('tr').length > 1) {
                  row.remove();
                  updateTotal();
              } else {
                  alert("At least one row must remain.");
              }
          });
      }

      // Build one item row (blank, or prefilled when editing an existing claim).
      function buildRow(data, fallbackSupplierId) {
          const newRow = document.createElement('tr');
          newRow.innerHTML = `
              <td class="col-parent"><input type="text" name="row_parent[]" class="form-control row-parent" readonly placeholder="Auto"></td>
              <td class="col-sku"><select name="item[]" class="form-control sku-select" required style="width: 100%;"><option value="">Search SKU...</option></select></td>
              <td class="col-supplier"><select name="row_supplier[]" class="form-select row-supplier" required>${window.CLAIM_SUPPLIER_OPTIONS}</select></td>
              <td class="col-num4"><input type="number" name="qty[]" class="form-control qty" required></td>
              <td class="col-num4"><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
              <td class="col-num6"><input type="number" name="amount[]" class="form-control amount" readonly></td>
              <td class="col-reason"><input type="hidden" name="reason[]" class="reason-value"><button type="button" class="btn reason-btn" title="Add reason / notes"><i class="fas fa-pen-to-square"></i><span class="reason-text">Add reason / notes</span></button></td>
              <td class="col-attach"><input type="hidden" name="existing_image[]" class="existing-image"><label class="claim-attach" title="Attach image / PDF"><i class="fas fa-paperclip"></i><input type="file" name="image[]" class="claim-attach-input" accept=".jpg,.jpeg,.png,.pdf"></label></td>
              <td class="col-shot"><input type="hidden" name="existing_screenshot[]" class="existing-screenshot"><label class="claim-shot" tabindex="0" title="Click to select, then press Cmd/Ctrl+V to paste a snippet. Double-click to choose a file."><i class="fas fa-camera"></i><img class="claim-shot-thumb" alt="" style="display:none;"><input type="file" name="screenshot[]" class="claim-shot-input" accept="image/*"></label></td>
              <td><button type="button" class="btn remove-row" title="Remove row"><i class="fas fa-trash-can"></i></button></td>
          `;
          claimTableBody.appendChild(newRow);
          attachListeners(newRow);
          initializeSkuSelect(newRow.querySelector('.sku-select'));
          initializeSupplierSelect(newRow.querySelector('.row-supplier'));

          if (data) {
              const $row = jQuery(newRow);
              const sku = String(data.item || '');

              if (sku) {
                  // 'change.select2' refreshes the UI without running the SKU autofill (keeps saved values).
                  $row.find('.sku-select').append(new Option(sku, sku, true, true)).trigger('change.select2');
              }

              const parentInput = newRow.querySelector('.row-parent');
              parentInput.value = data.parent || '';
              parentInput.title = parentInput.value;

              const supplierId = data.supplier_id || fallbackSupplierId || '';
              setRowSupplier(newRow.querySelector('.row-supplier'), supplierId ? String(supplierId) : '');

              newRow.querySelector('.qty').value = data.qty ?? '';
              newRow.querySelector('.rate').value = data.rate ?? '';
              newRow.querySelector('.amount').value = data.amount !== undefined && data.amount !== null && data.amount !== ''
                  ? Math.round(parseFloat(data.amount) || 0)
                  : '';

              const reasonHidden = newRow.querySelector('.reason-value');
              reasonHidden.value = data.reason || '';
              if (typeof window.claimRefreshReasonBtn === 'function') {
                  window.claimRefreshReasonBtn(newRow.querySelector('.reason-btn'));
              }

              if (data.screenshot) {
                  newRow.querySelector('.existing-screenshot').value = data.screenshot;
                  setShotPreview(newRow.querySelector('.claim-shot'), '/' + String(data.screenshot).replace(/^\/+/, ''));
              }

              if (data.image) {
                  newRow.querySelector('.existing-image').value = data.image;
                  const attach = newRow.querySelector('.claim-attach');
                  attach.classList.add('has-file');
                  attach.title = 'Current file: ' + String(data.image).split('/').pop() + ' (choose a file to replace)';
              }
          }

          return newRow;
      }

      // Handle Add Row button
      addRowBtn.addEventListener('click', () => buildRow());

      // Helpers used by the "Edit claim" flow (directors).
      window.claimUpdateTotal = updateTotal;
      window.claimAddRow = buildRow;
      window.claimClearRows = function () {
          claimTableBody.querySelectorAll('tr').forEach(function (tr) {
              if (window.jQuery) {
                  jQuery(tr).find('select').each(function () {
                      if (jQuery(this).data('select2')) jQuery(this).select2('destroy');
                  });
              }
              tr.remove();
          });
      };

      const firstRow = claimTableBody.querySelector('tr');
      if (firstRow) {
          attachListeners(firstRow);
          initializeSkuSelect(firstRow.querySelector('.sku-select'));
          initializeSupplierSelect(firstRow.querySelector('.row-supplier'));
      }
    }

    // Initialize Select2 searchable SKU dropdown on a given <select> element
    function initializeSkuSelect(element) {
        if (!element) return;
        jQuery(element).select2({
            ajax: {
                url: '/purchase/search-sku',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: {
                            more: data.has_more
                        }
                    };
                },
                cache: true
            },
            placeholder: 'Search SKU...',
            allowClear: true,
            minimumInputLength: 0,
            width: '100%',
            dropdownParent: jQuery('#claimModal'),
            dropdownCssClass: 'claim-select-dropdown'
        });

        // Autofill the row's Supplier from the supplier list (matched via SKU / Parent).
        jQuery(element).on('change', function () {
            const sku = jQuery(this).val();
            const supplierSelect = jQuery(this).closest('tr').find('.row-supplier')[0];
            const parentInput = jQuery(this).closest('tr').find('.row-parent')[0];
            if (!supplierSelect) return;

            if (!sku) {
                setRowSupplier(supplierSelect, '');
                if (parentInput) parentInput.value = '';
                return;
            }

            fetch('/claim-reimbursement/sku-supplier?sku=' + encodeURIComponent(sku), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                if (parentInput) {
                    parentInput.value = (res && res.parent) || '';
                    parentInput.title = parentInput.value; // full parent on hover (column is narrow)
                }
                const list = (res && res.suppliers) || [];
                let supplierId = list.length ? String(list[0].id) : '';
                if (window.CLAIM_PREFILL_SUPPLIER_ID) {
                    supplierId = String(window.CLAIM_PREFILL_SUPPLIER_ID);
                    window.CLAIM_PREFILL_SUPPLIER_ID = null;
                }
                setRowSupplier(supplierSelect, supplierId);

                // Autofill Rate USD from the last purchase (never overwrite a hand-typed rate).
                const rateInput = jQuery(element).closest('tr').find('.rate')[0];
                if (rateInput && res && res.last_rate != null && (rateInput.value === '' || rateInput.dataset.auto === '1')) {
                    rateInput.value = parseFloat(Number(res.last_rate).toFixed(2)); // keep it short (e.g. 37.5)
                    rateInput.title = 'Last purchase rate' + (res.last_rate_source ? ' (' + res.last_rate_source + ')' : '');
                    rateInput.dispatchEvent(new Event('input', { bubbles: true }));
                    rateInput.dataset.auto = '1';
                }

                supplierSelect.title = list.length > 1
                    ? 'Multiple suppliers match: ' + list.map(s => s.name).join(', ')
                    : '';
            })
            .catch(() => {});
        });
    }

    // Lower-case and strip everything except letters/digits so "ARM-SET 16" == "armset16".
    function normalizeSearch(str) {
        return String(str == null ? '' : str).toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    // Searchable supplier dropdown with the same normalized quick search.
    function initializeSupplierSelect(element) {
        if (!element) return;
        jQuery(element).select2({
            width: '100%',
            placeholder: 'Select Supplier',
            allowClear: true,
            dropdownParent: jQuery('#claimModal'),
            dropdownCssClass: 'claim-select-dropdown',
            matcher: function (params, data) {
                const term = String(params.term || '').trim();
                if (term === '') return data;
                if (!data.text) return null;
                const hay = normalizeSearch(data.text);
                const ok = term.split(/\s+/).every(function (tok) {
                    const n = normalizeSearch(tok);
                    return n === '' || hay.indexOf(n) !== -1;
                });
                return ok ? data : null;
            }
        });
    }

    // Set a row supplier and refresh the select2 UI.
    function setRowSupplier(select, value) {
        if (!select) return;
        if (window.jQuery) {
            jQuery(select).val(value || '').trigger('change');
        } else {
            select.value = value || '';
        }
    }

    // Reason / Notes: edit icon opens a modal; the text is kept in the row's hidden input.
    (function () {
        let activeBtn = null;

        function refreshReasonBtn(btn) {
            const hidden = btn.parentElement.querySelector('.reason-value');
            const text = (hidden && hidden.value || '').trim();
            btn.classList.toggle('has-text', text !== '');
            btn.title = text !== '' ? text : 'Add reason / notes';
            const label = btn.querySelector('.reason-text');
            if (label) label.textContent = text !== '' ? text : 'Add reason / notes';
        }

        window.claimRefreshReasonBtn = refreshReasonBtn;

        document.addEventListener('click', function (e) {
            const btn = e.target.closest ? e.target.closest('.reason-btn') : null;
            if (!btn) return;
            activeBtn = btn;
            const row = btn.closest('tr');
            const sku = row && row.querySelector('.sku-select') ? (row.querySelector('.sku-select').value || '') : '';
            document.getElementById('reasonModalSku').textContent = sku ? 'SKU: ' + sku : '';
            document.getElementById('reasonModalInput').value = btn.parentElement.querySelector('.reason-value').value || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reasonModal')).show();
        });

        const reasonModalEl = document.getElementById('reasonModal');
        if (!reasonModalEl) return;

        // Stack above the (full-screen) claim modal and its backdrop.
        reasonModalEl.addEventListener('show.bs.modal', function () {
            setTimeout(function () {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                if (backdrops.length) backdrops[backdrops.length - 1].style.zIndex = '1065';
            }, 10);
        });
        reasonModalEl.addEventListener('shown.bs.modal', function () {
            const input = document.getElementById('reasonModalInput');
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        });
        // Closing the top modal must not unlock scrolling while the claim modal is still open.
        reasonModalEl.addEventListener('hidden.bs.modal', function () {
            if (document.getElementById('claimModal').classList.contains('show')) {
                document.body.classList.add('modal-open');
            }
        });

        function saveReason() {
            if (!activeBtn) return;
            activeBtn.parentElement.querySelector('.reason-value').value = document.getElementById('reasonModalInput').value.trim();
            refreshReasonBtn(activeBtn);
            bootstrap.Modal.getInstance(reasonModalEl).hide();
        }

        document.getElementById('reasonModalSave').addEventListener('click', saveReason);
        document.getElementById('reasonModalInput').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                saveReason();
            }
        });
    })();

    // ---- Screenshot column: thumbnail in the row, large preview on hover ----
    function setShotPreview(label, src) {
        if (!label) return;
        const img = label.querySelector('.claim-shot-thumb');
        const icon = label.querySelector('i');
        if (src) {
            img.src = src;
            img.style.display = 'block';
            if (icon) icon.style.display = 'none';
            label.classList.add('has-shot');
            label.dataset.scSrc = src;
            label.title = '';
        } else {
            img.removeAttribute('src');
            img.style.display = 'none';
            if (icon) icon.style.display = '';
            label.classList.remove('has-shot');
            delete label.dataset.scSrc;
            label.title = 'Click to select, then press Cmd/Ctrl+V to paste a snippet. Double-click to choose a file.';
        }
    }

    document.addEventListener('change', function (e) {
        const input = e.target;
        if (!input || !input.classList || !input.classList.contains('claim-shot-input')) return;
        const label = input.closest('.claim-shot');
        const file = input.files && input.files[0];
        if (!file) {
            // Cleared: fall back to the saved screenshot (if editing), otherwise empty.
            const saved = label.closest('td').querySelector('.existing-screenshot').value;
            setShotPreview(label, saved ? '/' + saved.replace(/^\/+/, '') : '');
            return;
        }
        const reader = new FileReader();
        reader.onload = function (ev) { setShotPreview(label, ev.target.result); };
        reader.readAsDataURL(file);
    });

    // Snippets: paste (Ctrl/Cmd+V) a copied screenshot / snippet, or drag an image onto the camera button.
    (function () {
        let hovered = null;

        document.addEventListener('mouseover', function (e) {
            const l = e.target.closest ? e.target.closest('.claim-shot') : null;
            if (l) hovered = l;
        });
        document.addEventListener('mouseout', function (e) {
            const l = e.target.closest ? e.target.closest('.claim-shot') : null;
            if (l && !(e.relatedTarget && l.contains(e.relatedTarget))) hovered = null;
        });

        function setShotFile(label, file) {
            const input = label.querySelector('.claim-shot-input');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function claimOpenAndEditable() {
            const modal = document.getElementById('claimModal');
            const fieldset = document.getElementById('claimFieldset');
            return modal && modal.classList.contains('show') && !(fieldset && fieldset.disabled);
        }

        // Click = select the camera (so Cmd/Ctrl+V pastes into it). Double-click / Enter = choose a file.
        let selectedShot = null;

        // Safari/Chrome only fire "paste" reliably when an editable element has focus, so a
        // label can't receive Cmd+V itself. Selecting a camera focuses this invisible field instead.
        let catcher = null;
        function getCatcher() {
            if (!catcher) {
                catcher = document.createElement('textarea');
                catcher.id = 'claimShotCatcher';
                catcher.setAttribute('aria-label', 'Paste screenshot here');
                catcher.setAttribute('autocomplete', 'off');
                catcher.style.cssText = 'position:fixed;left:-200px;top:0;width:10px;height:10px;opacity:0;pointer-events:none;border:0;padding:0;';
                (document.getElementById('claimModal') || document.body).appendChild(catcher);

                catcher.addEventListener('keydown', function (ev) {
                    if (!selectedShot) return;
                    if (ev.key === 'Enter' || ev.key === ' ') {
                        ev.preventDefault();
                        selectedShot.querySelector('.claim-shot-input').click();
                    }
                });
                // Fallback when the browser does not fire a paste event: read the clipboard directly.
                catcher.addEventListener('keydown', function (ev) {
                    if (!((ev.metaKey || ev.ctrlKey) && (ev.key === 'v' || ev.key === 'V'))) return;
                    pasteFired = false;
                    setTimeout(function () {
                        if (pasteFired || !selectedShot || !navigator.clipboard || !navigator.clipboard.read) return;
                        navigator.clipboard.read().then(function (clipItems) {
                            for (const ci of clipItems) {
                                const type = ci.types.find(function (t) { return /^image\//.test(t); });
                                if (type) { ci.getType(type).then(function (b) { applyBlob(b, false); }); return; }
                            }
                        }).catch(function () {});
                    }, 200);
                });
            }
            return catcher;
        }

        let pasteFired = false;

        function selectShot(label) {
            document.querySelectorAll('.claim-shot.shot-selected').forEach(function (l) { l.classList.remove('shot-selected'); });
            selectedShot = label;
            if (label) {
                label.classList.add('shot-selected');
                const c = getCatcher();
                c.value = '';
                c.focus({ preventScroll: true });
            }
        }

        document.addEventListener('click', function (e) {
            if (e.target.classList && e.target.classList.contains('claim-shot-input')) return; // programmatic picker open
            const label = e.target.closest ? e.target.closest('#claimTableBody .claim-shot') : null;
            if (!label) {
                if (!(e.target.closest && e.target.closest('#claimShotPasteBtn'))) selectShot(null);
                return;
            }
            e.preventDefault(); // stop the label from opening the file picker on a single click
            if (!claimOpenAndEditable()) return;
            selectShot(label);
        });

        document.addEventListener('dblclick', function (e) {
            const label = e.target.closest ? e.target.closest('#claimTableBody .claim-shot') : null;
            if (!label || !claimOpenAndEditable()) return;
            e.preventDefault();
            label.querySelector('.claim-shot-input').click();
        });

        document.addEventListener('keydown', function (e) {
            const label = e.target.closest ? e.target.closest('#claimTableBody .claim-shot') : null;
            if (!label || e.target !== label) return;
            if ((e.key === 'Enter' || e.key === ' ') && claimOpenAndEditable()) {
                e.preventDefault();
                label.querySelector('.claim-shot-input').click();
            }
        });

        let toastTimer = null;
        function shotToast(msg) {
            let t = document.getElementById('claimShotToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'claimShotToast';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.style.display = 'block';
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function () { t.style.display = 'none'; }, 5000);
        }

        const MAC_HINT = 'No screenshot on the clipboard. On Mac press Cmd+Ctrl+Shift+4 (note the Ctrl) to copy a snippet instead of saving it to the Desktop, then paste again.';

        // Target: the camera under the mouse, else the row being edited, else the first empty row.
        function pasteTarget(useHover) {
            let label = (selectedShot && document.body.contains(selectedShot)) ? selectedShot : null;
            if (!label && useHover) label = hovered;
            if (!label && document.activeElement && document.activeElement.closest) {
                const row = document.activeElement.closest('#claimTableBody tr');
                if (row) label = row.querySelector('.claim-shot');
            }
            if (!label) {
                const all = Array.from(document.querySelectorAll('#claimTableBody .claim-shot'));
                label = all.find(function (l) { return !l.classList.contains('has-shot'); }) || all[0];
            }
            return label;
        }

        function applyBlob(blob, useHover) {
            const label = pasteTarget(useHover);
            if (!label) return false;
            const type = blob.type || 'image/png';
            const ext = (type.split('/')[1] || 'png').replace('jpeg', 'jpg');
            setShotFile(label, new File([blob], 'snippet_' + Date.now() + '.' + ext, { type: type }));
            shotToast('Screenshot added.');
            return true;
        }

        document.addEventListener('paste', function (e) {
            if (!claimOpenAndEditable()) return;
            pasteFired = true;
            const cd = e.clipboardData;
            let blob = null;
            if (cd) {
                const items = cd.items ? Array.from(cd.items) : [];
                const imgItem = items.find(function (it) { return it.kind === 'file' && /^image\//.test(it.type); });
                if (imgItem) blob = imgItem.getAsFile();
                if (!blob && cd.files && cd.files.length) {
                    // Files copied in Finder can arrive with an empty MIME type: fall back to the extension.
                    blob = Array.from(cd.files).find(function (f) {
                        return /^image\//.test(f.type) || (!f.type && /\.(png|jpe?g|gif|webp|bmp)$/i.test(f.name));
                    }) || null;
                }
            }

            if (!blob) {
                // Plain-text paste into a field is left alone; otherwise explain what to do.
                const a = document.activeElement;
                const typing = a && a !== catcher && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable);
                const overShot = !!hovered;
                const catching = a === catcher;
                if (catching) e.preventDefault();
                if (!typing || overShot || catching) {
                    const hasText = cd && cd.types && Array.prototype.indexOf.call(cd.types, 'text/plain') !== -1;
                    if (!hasText || overShot || catching) {
                        // Include what the browser actually received, to help diagnose Mac clipboard issues.
                        const seen = cd && cd.types ? Array.from(cd.types).join(', ') : 'nothing';
                        shotToast(MAC_HINT + ' (Clipboard contained: ' + (seen || 'nothing') + ')');
                    }
                }
                return;
            }

            e.preventDefault();
            applyBlob(blob, true);
        });

        // Header "paste" button: reads the clipboard directly (needs HTTPS/localhost + permission).
        const pasteBtn = document.getElementById('claimShotPasteBtn');
        if (pasteBtn) {
            pasteBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                if (!claimOpenAndEditable()) return;
                if (!navigator.clipboard || !navigator.clipboard.read) {
                    shotToast('Click on the page and press Cmd+V (Ctrl+V) to paste your screenshot. ' + 'On Mac copy the snippet with Cmd+Ctrl+Shift+4.');
                    return;
                }
                try {
                    const clipItems = await navigator.clipboard.read();
                    for (const ci of clipItems) {
                        const type = ci.types.find(function (t) { return /^image\//.test(t); });
                        if (type) {
                            const blob = await ci.getType(type);
                            applyBlob(blob, false);
                            return;
                        }
                    }
                    shotToast(MAC_HINT);
                } catch (err) {
                    shotToast('Clipboard access was blocked. Press Cmd+V (Ctrl+V) to paste instead.');
                }
            });
        }

        // Drag & drop: drop image(s) on a row's camera, anywhere on a row, or anywhere in the items area.
        // Extra images fill the following empty rows (new rows are added as needed).
        function dragHasFiles(e) {
            const t = e.dataTransfer && e.dataTransfer.types;
            return !!t && Array.prototype.indexOf.call(t, 'Files') !== -1;
        }

        function clearDropHighlight() {
            document.querySelectorAll('.claim-drop-over').forEach(function (el) { el.classList.remove('claim-drop-over'); });
        }

        function dropTargetFor(e) {
            const zone = e.target.closest ? e.target.closest('#claimModal .modal-body') : null;
            if (!zone) return null;
            const row = e.target.closest('#claimTableBody tr');
            return { zone: zone, row: row };
        }

        document.addEventListener('dragover', function (e) {
            if (!dragHasFiles(e)) return;
            const t = dropTargetFor(e);
            if (!t) return;
            e.preventDefault(); // also stops the browser from opening the dropped file
            if (!claimOpenAndEditable()) { e.dataTransfer.dropEffect = 'none'; return; }
            e.dataTransfer.dropEffect = 'copy';
            clearDropHighlight();
            (t.row || document.getElementById('claimTable') || t.zone).classList.add('claim-drop-over');
        });

        document.addEventListener('dragleave', function (e) {
            if (!e.relatedTarget || !(e.relatedTarget.closest && e.relatedTarget.closest('#claimModal'))) clearDropHighlight();
        });

        document.addEventListener('drop', function (e) {
            if (!dragHasFiles(e)) return;
            const t = dropTargetFor(e);
            if (!t) return;
            e.preventDefault();
            clearDropHighlight();
            if (!claimOpenAndEditable()) return;

            const files = Array.from(e.dataTransfer.files).filter(function (f) { return /^image\//.test(f.type); });
            if (!files.length) return;

            const rows = function () { return Array.from(document.querySelectorAll('#claimTableBody tr')); };
            let startIdx = t.row ? rows().indexOf(t.row) : rows().findIndex(function (r) {
                return !r.querySelector('.claim-shot').classList.contains('has-shot');
            });
            if (startIdx < 0) startIdx = rows().length; // nothing free: append new rows

            files.forEach(function (file, i) {
                let r = rows()[startIdx + i];
                if (!r && typeof window.claimAddRow === 'function') r = window.claimAddRow();
                const label = r && r.querySelector('.claim-shot');
                if (label) setShotFile(label, file);
            });
        });
    })();

    // One floating large preview shared by every [data-sc-src] element (modal rows + list table).
    (function () {
        const box = document.createElement('div');
        box.id = 'scPreview';
        box.innerHTML = '<img alt="Screenshot preview">';
        document.body.appendChild(box);
        const img = box.querySelector('img');

        function place(e) {
            const pad = 18;
            const w = box.offsetWidth || 480;
            const h = box.offsetHeight || 320;
            let x = e.clientX + pad;
            let y = e.clientY + pad;
            if (x + w > window.innerWidth - 8) x = Math.max(8, e.clientX - w - pad);
            if (y + h > window.innerHeight - 8) y = Math.max(8, window.innerHeight - h - 8);
            box.style.left = x + 'px';
            box.style.top = y + 'px';
        }

        document.addEventListener('mouseover', function (e) {
            const t = e.target.closest ? e.target.closest('[data-sc-src]') : null;
            if (!t) return;
            img.onload = function () { place(e); };
            img.src = t.dataset.scSrc;
            box.style.display = 'block';
            place(e);
        });
        document.addEventListener('mousemove', function (e) {
            if (box.style.display === 'block') place(e);
        });
        document.addEventListener('mouseout', function (e) {
            const t = e.target.closest ? e.target.closest('[data-sc-src]') : null;
            if (!t) return;
            if (e.relatedTarget && t.contains(e.relatedTarget)) return;
            box.style.display = 'none';
        });
        // Never leave the preview stuck open (e.g. a modal closes or the user clicks).
        document.addEventListener('click', function () { box.style.display = 'none'; });
    })();

    // Attachment icon: show a green tick + file name tooltip once a file is chosen.
    document.addEventListener('change', function (e) {
        const input = e.target;
        if (!input || !input.classList || !input.classList.contains('claim-attach-input')) return;
        const label = input.closest('.claim-attach');
        if (!label) return;
        const file = input.files && input.files[0];
        label.classList.toggle('has-file', !!file);
        label.title = file ? file.name : 'Attach image / PDF';
    });

    // Always focus the search box when any claim dropdown opens.
    jQuery(document).on('select2:open', function () {
        const field = document.querySelector('.select2-container--open .select2-search__field');
        if (field) field.focus();
    });

    // Recd Amt/Goods lives inside the main claim modal: open the claim and jump to that section.
    function openClaimRecd(d) {
        openEditClaim(d, true);
        const modalEl = document.getElementById('claimModal');
        const focusIt = function () {
            const card = document.getElementById('claimRecdCard');
            const input = document.getElementById('claimRecdInput');
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (input) input.focus();
        };
        if (modalEl.classList.contains('show')) focusIt();
        else modalEl.addEventListener('shown.bs.modal', focusIt, { once: true });
    }

    function buildDetailsInline(detailsData, detailsNote) {
        let items = detailsData || [];
        if (typeof items === 'string') {
            try { items = JSON.parse(items) || []; } catch (e) { items = []; }
        }
        if (!Array.isArray(items)) items = [];

        let html = '';

        if (items.length > 0) {
            html += '<table class="table table-sm table-bordered mb-1" style="font-size: 12px;">';
            html += '<thead class="table-light"><tr>'
                + '<th>Parent</th><th>SKU</th><th>Supplier</th><th>Qty</th><th>Rate</th><th>Amount</th><th>Reason/Notes</th><th>Image</th><th>Screenshot</th>'
                + '</tr></thead><tbody>';
            items.forEach(item => {
                const img = item.image
                    ? `<a href="/${escapeHtml(item.image)}" target="_blank"><img src="/${escapeHtml(item.image)}" style="height:28px;"></a>`
                    : '<span class="text-muted">N/A</span>';
                const shotSrc = item.screenshot ? '/' + String(item.screenshot).replace(/^\/+/, '') : '';
                const shot = shotSrc
                    ? `<img src="${escapeHtml(shotSrc)}" data-sc-src="${escapeHtml(shotSrc)}" style="height:28px;width:28px;object-fit:cover;border-radius:4px;cursor:zoom-in;" alt="Screenshot">`
                    : '<span class="text-muted">N/A</span>';
                html += '<tr>'
                    + `<td>${escapeHtml(item.parent || '')}</td>`
                    + `<td>${escapeHtml(item.item || '')}</td>`
                    + `<td>${escapeHtml(item.supplier || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.qty || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.rate || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.amount || '')}</td>`
                    + `<td>${escapeHtml(item.reason || '')}</td>`
                    + `<td class="text-center">${img}</td>`
                    + `<td class="text-center">${shot}</td>`
                    + '</tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<span class="text-muted">No items</span><br>';
        }

        if (detailsNote && String(detailsNote).trim() !== '') {
            html += `<div class="small"><span class="fw-semibold">Note:</span> ${escapeHtml(detailsNote)} `
                + `<button class="btn btn-sm btn-link p-0 details-note-btn" title="Edit details"><i class="fas fa-pen"></i></button></div>`;
        }

        return html;
    }

    function openDetailsNoteModal(claimId, currentValue) {
        document.getElementById('details_note_claim_id').value = claimId || '';
        document.getElementById('details_note_input').value = currentValue || '';
        const modal = new bootstrap.Modal(document.getElementById('detailsNoteModal'));
        modal.show();
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function buildCommunicationDropdown(linksData) {
        let list = linksData || [];
        if (typeof list === 'string') {
            try { list = JSON.parse(list) || []; } catch (e) { list = []; }
        }
        if (!Array.isArray(list) || list.length === 0) {
            return '<span class="text-muted">-</span>';
        }

        const iconMap = {
            'Website':  'fas fa-globe',
            'Email':    'fas fa-envelope',
            'WhatsApp': 'fab fa-whatsapp',
            'WeChat':   'fab fa-weixin',
            'Alibaba':  'fas fa-store',
        };
        const colorMap = {
            'Website':  '#2563eb',
            'Email':    '#dc3545',
            'WhatsApp': '#25d366',
            'WeChat':   '#09b83e',
            'Alibaba':  '#ff6a00',
        };

        let items = '';
        for (let i = 0; i < list.length; i++) {
            const p = list[i];
            const icon = iconMap[p.label] || 'fas fa-link';
            const color = colorMap[p.label] || '#6b7280';
            const title = escapeHtml(p.label + (p.display ? ': ' + p.display : ''));
            if (p.url) {
                const ext = p.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                items += '<a class="mip-plat-icon-link" href="' + escapeHtml(p.url) + '"' + ext + ' title="' + title + '" style="color:' + color + ';font-size:18px;"><i class="' + icon + '"></i></a>';
            } else {
                items += '<span class="mip-plat-icon-link" title="' + title + '" style="color:' + color + ';font-size:18px;"><i class="' + icon + '"></i></span>';
            }
        }

        return '<div class="dropdown d-inline-block">' +
            '<button class="btn btn-sm btn-light py-0 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 11px;" title="Communication">C (' + list.length + ')</button>' +
            '<ul class="dropdown-menu dropdown-menu-end" style="overflow: visible; min-width: auto; padding: 6px;"><li class="d-flex align-items-center gap-2 px-2">' + items + '</li></ul></div>';
    }

    // ---- Edit a whole claim in the full modal (directors only; also enforced on the server) ----
    function normalizeClaimItems(raw) {
        let items = raw || [];
        for (let i = 0; i < 2 && typeof items === 'string'; i++) {
            try { items = JSON.parse(items) || []; } catch (e) { items = []; }
        }
        return Array.isArray(items) ? items : [];
    }

    function renderClaimActions(history) {
        document.getElementById('claimActionHistory').innerHTML = buildActionHistoryInline(history, false);
    }

    function openEditClaim(d, readOnly) {
        // Directors may edit; everyone else only gets the read-only view.
        if (!readOnly && window.CLAIM_CAN_ARCHIVE !== true) readOnly = true;

        const modalEl = document.getElementById('claimModal');
        const form = document.getElementById('claimForm');

        window.claimEditId = d.id;
        form.action = '/claim-reimbursement/' + d.id + '/update';
        document.getElementById('claimModalLabel').textContent = readOnly ? 'Claim / Reimbursement' : 'Edit Claim / Reimbursement';
        document.getElementById('claimNo').value = d.claim_number || '';
        document.getElementById('claimDate').value = String(d.claim_date || '').substring(0, 10);
        document.getElementById('claimSaveBtn').innerHTML = '<i class="fas fa-check me-1"></i> Update Claim';
        document.getElementById('claimSaveBtn').style.display = readOnly ? 'none' : '';
        document.getElementById('claimFieldset').disabled = !!readOnly;

        // Action section (history + add) below the items.
        renderClaimActions(d.action_history);
        document.getElementById('claimActionInput').value = '';
        document.getElementById('claimActionCard').style.display = '';

        // Recd Amt/Goods section (editable by everyone, like Action).
        document.getElementById('claimRecdInput').value = d.received_amount || '';
        document.getElementById('claimRecdCard').style.display = '';

        window.claimClearRows();
        const items = normalizeClaimItems(d.details);
        if (items.length) {
            items.forEach(function (it) { window.claimAddRow(it, d.supplier_id); });
        } else {
            window.claimAddRow();
        }
        window.claimUpdateTotal();

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    // Add an Action to the claim that is open in the modal.
    (function () {
        const input = document.getElementById('claimActionInput');
        const btn = document.getElementById('claimActionAddBtn');
        if (!input || !btn) return;

        function addAction() {
            const id = window.claimEditId;
            const note = input.value.trim();
            if (!id) return;
            if (note === '') { input.focus(); return; }

            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('note', note);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('/claim-reimbursement/' + id + '/action', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const row = window.claimTable ? window.claimTable.getRow(id) : null;
                    if (row) row.update({ action_history: res.action_history });
                    renderClaimActions(res.action_history);
                    input.value = '';
                } else {
                    alert('Failed: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(() => alert('Something went wrong while saving.'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        }

        btn.addEventListener('click', addAction);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addAction(); }
        });
    })();

    // Save Recd Amt/Goods for the claim open in the modal.
    (function () {
        const input = document.getElementById('claimRecdInput');
        const btn = document.getElementById('claimRecdSaveBtn');
        if (!input || !btn) return;

        btn.addEventListener('click', function () {
            const id = window.claimEditId;
            if (!id) return;

            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const formData = new FormData();
            formData.append('received_amount', input.value);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('/claim-reimbursement/' + id + '/received-amount', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const row = window.claimTable ? window.claimTable.getRow(id) : null;
                    if (row) row.update({ received_amount: res.received_amount, action_history: res.action_history });
                    renderClaimActions(res.action_history);
                    input.value = res.received_amount || '';
                    btn.innerHTML = '<i class="fas fa-check me-1"></i> Saved';
                    setTimeout(function () { btn.innerHTML = original; }, 1200);
                } else {
                    btn.innerHTML = original;
                    alert('Failed: ' + (res.message || 'Unknown error'));
                }
            })
            .catch(() => { btn.innerHTML = original; alert('Something went wrong while saving.'); })
            .finally(() => { btn.disabled = false; });
        });
    })();

    // Put the modal back into "Add" mode once it closes.
    (function () {
        const modalEl = document.getElementById('claimModal');
        if (!modalEl) return;
        const defaults = {
            action: document.getElementById('claimForm').getAttribute('action'),
            claimNo: document.getElementById('claimNo').value,
            title: document.getElementById('claimModalLabel').textContent,
            saveHtml: document.getElementById('claimSaveBtn').innerHTML
        };
        modalEl.addEventListener('hidden.bs.modal', function (e) {
            if (e.target !== modalEl || !window.claimEditId) return;
            window.claimEditId = null;
            document.getElementById('claimForm').setAttribute('action', defaults.action);
            document.getElementById('claimModalLabel').textContent = defaults.title;
            document.getElementById('claimNo').value = defaults.claimNo;
            document.getElementById('claimDate').value = '';
            document.getElementById('claimSaveBtn').innerHTML = defaults.saveHtml;
            document.getElementById('claimSaveBtn').style.display = '';
            document.getElementById('claimFieldset').disabled = false;
            document.getElementById('claimActionCard').style.display = 'none';
            document.getElementById('claimActionInput').value = '';
            document.getElementById('claimRecdCard').style.display = 'none';
            document.getElementById('claimRecdInput').value = '';
            window.claimClearRows();
            window.claimAddRow();
            window.claimUpdateTotal();
        });
    })();

    function buildResolvedCell(data) {
        const canArchive = window.CLAIM_CAN_ARCHIVE === true;
        const archived = data.is_archived === true || data.is_archived === 1;

        let html = '';

        // Directors edit the whole claim; everyone else gets a read-only view (+ can log Actions).
        if (canArchive) {
            html += `<button class="btn btn-sm btn-outline-primary edit-claim-btn me-1" title="Edit claim"><i class="fas fa-pen-to-square"></i></button>`;
        } else {
            html += `<button class="btn btn-sm btn-outline-secondary view-claim-btn me-1" title="Open claim &amp; actions"><i class="fas fa-eye"></i></button>`;
        }

        if (archived) {
            const by = data.archived_by ? escapeHtml(data.archived_by) : '';
            const date = data.archived_date ? escapeHtml(data.archived_date) : '';
            const tip = ('Resolved' + (by ? ' by ' + by : '') + (date ? ' · ' + date : '')).replace(/"/g, '');
            html += `<span class="text-success" title="${tip}"><i class="fas fa-circle-check"></i></span>`;
            // Shown to everyone; only directors can use it.
            html += canArchive
                ? `<button class="btn btn-sm btn-link p-0 ms-1 archive-btn" data-archive="0" title="Restore"><i class="fas fa-rotate-left"></i></button>`
                : `<button class="btn btn-sm btn-link p-0 ms-1 archive-btn" disabled title="Only directors can restore"><i class="fas fa-rotate-left"></i></button>`;
        } else {
            html += canArchive
                ? `<button class="btn btn-sm btn-outline-success archive-btn" data-archive="1" title="Archive (resolve)"><i class="fas fa-box-archive"></i></button>`
                : `<button class="btn btn-sm btn-outline-secondary archive-btn" disabled title="Only directors can archive"><i class="fas fa-box-archive"></i></button>`;
        }

        return html;
    }

    function toggleArchive(id, archive) {
        if (archive && !confirm('Archive (mark resolved) this claim?')) return;
        if (!archive && !confirm('Restore this claim?')) return;

        const formData = new FormData();
        formData.append('archive', archive ? 1 : 0);
        formData.append('_token', '{{ csrf_token() }}');

        fetch(`/claim-reimbursement/${id}/archive`, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
        .then(({ ok, body }) => {
            if (ok && body.success) {
                if (typeof window.reloadClaimTable === 'function') {
                    window.reloadClaimTable();
                }
            } else {
                alert(body.message || 'Action failed.');
            }
        })
        .catch(() => alert('Something went wrong.'));
    }

    function buildFollowUpDisplay(val) {
        if (!val || String(val).trim() === '') {
            return '<span class="text-muted"><i class="fas fa-calendar-plus me-1"></i>Set date</span>';
        }
        const d = new Date(val + 'T00:00:00');
        if (isNaN(d.getTime())) {
            return '<span class="text-muted">Set date</span>';
        }
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const label = d.getDate() + ' ' + months[d.getMonth()];

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (d < today) {
            return `<span class="fw-semibold text-danger">${label} <i class="fas fa-exclamation-circle" title="Overdue"></i></span>`;
        }
        return `<span class="fw-semibold">${label}</span>`;
    }

    function saveFollowUpDate(id, value, cell) {
        const formData = new FormData();
        formData.append('follow_up_date', value || '');
        formData.append('_token', '{{ csrf_token() }}');

        fetch(`/claim-reimbursement/${id}/follow-up`, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert('Failed: ' + (res.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Something went wrong while saving the follow up date.'));
    }

    function buildActionHistoryInline(historyData, withAddBtn) {
    if (withAddBtn === undefined) withAddBtn = true;
    let history = historyData || [];
    if (typeof history === 'string') {
        try { history = JSON.parse(history) || []; } catch (e) { history = []; }
    }
    if (!Array.isArray(history)) history = [];

    let html = '';

    if (history.length > 0) {
        html += '<table class="table table-sm table-bordered mb-1" style="font-size: 12px;">';
        html += '<thead class="table-light"><tr><th>Action</th><th>Note</th><th>User</th><th>Date</th></tr></thead><tbody>';
        history.forEach(entry => {
            html += '<tr>'
                + `<td><span class="badge bg-info-subtle text-dark">${escapeHtml(entry.action || '-')}</span></td>`
                + `<td>${escapeHtml(entry.note || '-')}</td>`
                + `<td>${escapeHtml(entry.user || '-')}</td>`
                + `<td>${escapeHtml(entry.date || '-')}</td>`
                + '</tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<span class="text-muted">No history</span><br>';
    }

    if (withAddBtn) {
        html += '<button class="btn btn-sm btn-outline-primary add-action-btn"><i class="fas fa-plus me-1"></i> Add Action</button>';
    }
    return html;
}

function openAddActionModal(claimId) {
    document.getElementById('action_claim_id').value = claimId || '';
    document.getElementById('action_note_input').value = '';
    const modal = new bootstrap.Modal(document.getElementById('actionHistoryModal'));
    modal.show();
}




</script>
@endsection