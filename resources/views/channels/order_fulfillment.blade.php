@extends('layouts.vertical', ['title' => 'Order Fulfillment', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #order-fulfillment-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 8px 6px;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal;
            text-align: center;
            line-height: 1.2;
            font-size: 12px;
            font-weight: 600;
            color: black !important;
        }
        #order-fulfillment-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #order-fulfillment-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #of-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 30;
        }
        #of-toolbar .of-summary-badge {
            font-size: 0.86rem !important;
            padding: 0.24rem 0.54rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
        }
        #of-channels-badge {
            cursor: pointer;
            border: 0;
        }
        #of-channels-badge:hover { filter: brightness(1.12); }
        .of-alert-wrap { display: inline-flex; align-items: center; gap: 6px; }
        .of-alert-icon { color: #dc3545; font-size: 0.95rem; }
        #orderFulfillmentNav .of-nav-count {
            margin-left: 0.35rem;
            font-size: 0.65rem;
            background: #e9ecef;
            color: #212529;
            font-weight: 600;
        }
        #orderFulfillmentNav .of-nav-count:empty { display: none; }
        #of-toolbar .form-control-sm,
        #of-toolbar .form-select-sm {
            min-height: 28px;
            height: 28px;
            font-size: 0.78rem;
        }
        .of-paid {
            display: inline-block;
            background: #d1e7dd;
            color: #0f5132;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 50rem;
        }
        .of-unpaid {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 50rem;
        }
        .of-dt-cell { display: flex; flex-direction: column; line-height: 1.15; }
        .of-dt-date { font-weight: 600; color: #0f172a; }
        .of-dt-time { font-size: 0.72rem; color: #64748b; }
        .of-sku { font-size: 0.78rem; }
        .of-sku-list { display: flex; flex-direction: column; gap: 0.1rem; line-height: 1.25; }
        .of-sku-line { display: flex; align-items: baseline; gap: 0.3rem; white-space: nowrap; }
        .of-sku-qty { color: #64748b; font-size: 0.72rem; }
        /* View-order modal */
        .of-view-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem 1rem; }
        .of-view-item { min-width: 0; }
        .of-view-item.of-view-wide { grid-column: 1 / -1; }
        .of-view-item .of-view-label { font-size: 0.66rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; color: #94a3b8; }
        .of-view-item .of-view-value { font-size: 0.84rem; color: #0f172a; word-break: break-word; }
        .of-view-item .of-view-value.of-view-muted { color: #94a3b8; }
        .of-view-table { font-size: 0.82rem; margin-bottom: 0; }
        .of-view-table th { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; color: #94a3b8; border-bottom: 1px solid #eef2f6; padding: 0.3rem 0.4rem; }
        .of-view-table td { padding: 0.35rem 0.4rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .of-view-table tfoot td { border-bottom: 0; font-weight: 600; }
        .of-edit-btn {
            color: #0d6efd;
            line-height: 1;
        }
        .of-edit-btn:hover { color: #0a58ca; }
        .of-actions .btn { padding: 0 0.3rem; }
        .of-actions .btn:disabled { opacity: 0.6; }
        /* Create / edit order modal: compact, sectioned layout */
        .of-modal .modal-dialog { max-width: 820px; }
        .of-modal .modal-content { border: 0; border-radius: 0.6rem; box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22); }
        .of-modal .modal-header { padding: 0.7rem 1.1rem; border-bottom: 1px solid #e9edf3; }
        .of-modal .modal-title { font-size: 0.98rem; font-weight: 700; color: #0f172a; }
        .of-modal .modal-body { padding: 0.85rem 1.1rem 0.6rem; background: #f8fafc; }
        .of-modal .modal-footer { padding: 0.6rem 1.1rem; border-top: 1px solid #e9edf3; justify-content: space-between; }
        .of-modal .form-label { font-size: 0.7rem; font-weight: 600; letter-spacing: 0.02em; text-transform: uppercase; color: #64748b; margin-bottom: 0.15rem; }
        .of-modal .form-label .text-danger { margin-left: 1px; }
        .of-modal .form-control-sm, .of-modal .form-select-sm { font-size: 0.82rem; padding: 0.28rem 0.5rem; min-height: 30px; border-color: #d7dde6; }
        .of-modal .form-control-sm:focus, .of-modal .form-select-sm:focus { border-color: #86b7fe; box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.12); }
        .of-modal textarea.form-control-sm { min-height: 0; }
        .of-sec { background: #fff; border: 1px solid #e6eaf0; border-radius: 0.5rem; padding: 0.65rem 0.8rem 0.7rem; margin-bottom: 0.6rem; }
        .of-sec-title { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.5rem; }
        .of-sec-title h6 { margin: 0; font-size: 0.78rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 0.4rem; }
        .of-sec-title h6 i { color: #0d6efd; font-size: 0.75rem; width: 14px; text-align: center; }
        .of-sec-title .of-sec-sub { font-size: 0.7rem; color: #94a3b8; font-weight: 400; }
        .of-order-lines-head, .of-order-line {
            display: grid; grid-template-columns: minmax(0, 1fr) 64px 96px 84px 26px;
            gap: 0.4rem; align-items: center;
        }
        .of-order-lines-head { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; color: #94a3b8; padding: 0 0 0.25rem; border-bottom: 1px solid #eef2f6; margin-bottom: 0.35rem; }
        .of-order-lines-head .of-line-qty, .of-order-lines-head .of-line-price { text-align: center; }
        .of-order-lines-head .of-line-total { text-align: right; }
        .of-order-line { margin-bottom: 0.35rem; }
        .of-order-line .of-line-sku-wrap { position: relative; min-width: 0; }
        .of-order-line .of-line-sku { width: 100%; }
        .of-order-line .of-line-qty { text-align: center; }
        .of-order-line .of-line-price { text-align: right; }
        .of-order-line .of-line-total { text-align: right; font-size: 0.82rem; font-weight: 600; color: #0f172a; white-space: nowrap; }
        .of-order-line .of-line-remove { padding: 0; line-height: 1; font-size: 0.85rem; }
        .of-order-lines-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 0.4rem; padding-top: 0.45rem; border-top: 1px dashed #e2e8f0; font-size: 0.76rem; color: #64748b; }
        .of-order-lines-foot strong { color: #0f172a; font-size: 0.88rem; }
        .of-amount-hint { font-size: 0.68rem; color: #94a3b8; text-transform: none; letter-spacing: 0; font-weight: 400; }
        .of-modal .of-footer-summary { font-size: 0.78rem; color: #64748b; }
        .of-modal .of-footer-summary strong { color: #0f172a; }
        .of-sku-suggest {
            position: absolute; left: 0; right: 0; top: 100%; z-index: 1080;
            background: #fff; border: 1px solid #dee2e6; border-radius: 0.375rem;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.12); max-height: 240px; overflow-y: auto;
            margin-top: 2px; display: none;
        }
        .of-sku-suggest.show { display: block; }
        .of-sku-suggest .of-sku-item {
            display: flex; justify-content: space-between; gap: 0.75rem; align-items: baseline;
            padding: 0.35rem 0.6rem; cursor: pointer; font-size: 0.82rem;
        }
        .of-sku-suggest .of-sku-item:hover, .of-sku-suggest .of-sku-item.active { background: #eef4ff; }
        .of-sku-suggest .of-sku-item code { font-size: 0.8rem; color: #0f172a; }
        .of-sku-suggest .of-sku-item small { color: #64748b; white-space: nowrap; }
        .of-sku-suggest .of-sku-empty { padding: 0.4rem 0.6rem; color: #64748b; font-size: 0.8rem; }
        .of-tracking {
            font-size: 0.78rem;
            font-weight: 600;
            color: #0f172a;
        }
        .of-pdf-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            vertical-align: middle;
        }
        .of-pdf-dot.is-set { background: #198754; }
        .of-pdf-dot.is-empty { background: #dc3545; }

        /* ---------- Pending history badge + modal (gen-z) ---------- */
        .of-ph-badge {
            position: relative; display: inline-flex; align-items: center; gap: 7px;
            border: 0; cursor: pointer; color: #fff; font-weight: 700; font-size: 0.86rem; line-height: 1.15;
            padding: 0.3rem 0.8rem 0.3rem 0.65rem; border-radius: 999px;
            background: linear-gradient(135deg, #7c3aed 0%, #ec4899 55%, #f97316 100%);
            box-shadow: 0 6px 16px -6px rgba(236, 72, 153, 0.7);
            transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
            white-space: nowrap;
        }
        .of-ph-badge:hover { transform: translateY(-1px) scale(1.03); box-shadow: 0 10px 22px -6px rgba(236, 72, 153, 0.85); filter: saturate(1.15); }
        .of-ph-badge:active { transform: scale(.97); }
        .of-ph-badge b { font-variant-numeric: tabular-nums; font-size: 0.95rem; }
        .of-ph-pulse { width: 8px; height: 8px; border-radius: 50%; background: #a7f3d0; box-shadow: 0 0 0 0 rgba(167, 243, 208, .8); animation: of-ph-pulse 1.8s infinite; }
        @keyframes of-ph-pulse { 70% { box-shadow: 0 0 0 7px rgba(167, 243, 208, 0); } 100% { box-shadow: 0 0 0 0 rgba(167, 243, 208, 0); } }
        .of-ph-delta { font-size: .72rem; font-weight: 800; padding: 1px 7px; border-radius: 99px; background: rgba(0,0,0,.25); }
        .of-ph-delta:empty { display: none; }

        .of-ph-modal .modal-dialog { max-width: 1020px; }
        .of-ph-modal .modal-content {
            border: 0; border-radius: 26px; overflow: hidden; color: #e9eaff;
            background: radial-gradient(1200px 500px at 10% -10%, rgba(124, 58, 237, .28), transparent 60%), #0f1020;
            box-shadow: 0 30px 80px -20px rgba(0, 0, 0, .7);
        }
        .of-ph-head {
            position: relative; padding: 22px 26px 20px; color: #fff; overflow: hidden;
            background: linear-gradient(135deg, #7c3aed 0%, #ec4899 60%, #f97316 100%);
        }
        .of-ph-head::after { content: ''; position: absolute; right: -60px; top: -70px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255,255,255,.15); }
        .of-ph-head .btn-close { position: absolute; right: 18px; top: 18px; z-index: 3; filter: invert(1) grayscale(1) brightness(2); opacity: .9; }
        .of-ph-pill { display: inline-flex; align-items: center; gap: 6px; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; padding: 4px 11px; border-radius: 99px; background: rgba(255,255,255,.22); backdrop-filter: blur(6px); position: relative; z-index: 1; }
        .of-ph-head h5 { margin: 8px 0 2px; font-weight: 800; font-size: 1.45rem; letter-spacing: -.01em; position: relative; z-index: 1; color: #fff; }
        .of-ph-sub { font-size: .78rem; opacity: .92; position: relative; z-index: 1; }
        .of-ph-stats { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; position: relative; z-index: 1; }
        .of-ph-stat { background: rgba(255,255,255,.18); backdrop-filter: blur(8px); border-radius: 16px; padding: 9px 15px; min-width: 128px; }
        .of-ph-stat b { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
        .of-ph-stat span { font-size: .66rem; text-transform: uppercase; letter-spacing: .08em; opacity: .88; }
        .of-ph-body { padding: 16px 22px 22px; max-height: 68vh; overflow-y: auto; }
        .of-ph-tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
        .of-ph-tools .of-ph-spacer { flex: 1; }
        .of-ph-btn { border: 1px solid rgba(255,255,255,.16); background: transparent; color: #cfd1ff; border-radius: 99px; padding: 6px 14px; font-size: .78rem; font-weight: 700; cursor: pointer; transition: all .15s; }
        .of-ph-btn:hover { background: rgba(255,255,255,.08); }
        .of-ph-btn.is-on { background: linear-gradient(135deg, #7c3aed, #ec4899); color: #fff; border-color: transparent; }
        .of-ph-chartbox { position: relative; height: 360px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 20px; padding: 12px 14px; }
        .of-ph-hint { font-size: .72rem; color: rgba(233,234,255,.55); margin: 8px 2px 12px; }
        .of-ph-badge--daily { background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 60%, #a855f7 100%); box-shadow: 0 6px 16px -6px rgba(99, 102, 241, 0.75); }
        .of-ph-badge--daily:hover { box-shadow: 0 10px 22px -6px rgba(99, 102, 241, 0.9); }
        .of-ph-stat-btn { border: 0; color: #fff; text-align: left; cursor: pointer; transition: transform .15s, background .15s; }
        .of-ph-stat-btn:hover { background: rgba(255,255,255,.3); transform: translateY(-2px); }
        .of-ph-stat-btn.is-on { background: rgba(255,255,255,.34); box-shadow: inset 0 0 0 2px rgba(255,255,255,.8); }
        .of-ph-stat-btn em { display: block; font-style: normal; font-size: .62rem; font-weight: 700; opacity: .9; margin-top: 2px; }
        .of-ph-picker-title { font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: rgba(233,234,255,.7); margin: 4px 2px 8px; }
        .of-ph-sites { display: flex; flex-direction: column; gap: 6px; }
        .of-ph-site { display: grid; grid-template-columns: minmax(130px, 210px) minmax(0, 1fr) 54px 64px; align-items: center; gap: 12px; text-align: left; padding: 8px 12px; border-radius: 14px; cursor: pointer; color: #e9eaff; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); transition: all .15s; }
        .of-ph-site:hover { background: rgba(255,255,255,.1); transform: translateX(2px); }
        .of-ph-site.is-sel { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.55); box-shadow: 0 0 0 2px rgba(255,255,255,.12); }
        .of-ph-site-name { display: flex; align-items: center; gap: 8px; min-width: 0; font-size: .82rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .of-ph-bar { height: 12px; border-radius: 99px; background: rgba(255,255,255,.08); overflow: hidden; }
        .of-ph-bar i { display: block; height: 100%; border-radius: 99px; }
        .of-ph-site-num { font-weight: 800; font-size: 1rem; text-align: right; font-variant-numeric: tabular-nums; }
        .of-ph-site-d { text-align: right; }
        .of-ph-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; box-shadow: 0 0 10px currentColor; }
        .of-ph-d { font-size: .66rem; font-weight: 800; padding: 1px 6px; border-radius: 99px; }
        @media (max-width: 640px) { .of-ph-site { grid-template-columns: minmax(0, 1fr) 46px 56px; } .of-ph-bar { display: none; } }
        .of-ph-d.up { background: rgba(251, 113, 133, .22); color: #fda4af; }
        .of-ph-d.down { background: rgba(52, 211, 153, .2); color: #6ee7b7; }
        .of-ph-table { width: 100%; margin-top: 14px; font-size: .8rem; border-collapse: separate; border-spacing: 0 4px; }
        .of-ph-table th { color: rgba(233,234,255,.5); font-weight: 700; text-transform: uppercase; font-size: .64rem; letter-spacing: .06em; padding: 0 10px; text-align: left; }
        .of-ph-table td { background: rgba(255,255,255,.05); padding: 8px 10px; color: #e9eaff; }
        .of-ph-table td:first-child { border-radius: 12px 0 0 12px; font-weight: 700; }
        .of-ph-table td:last-child { border-radius: 0 12px 12px 0; }
        .of-ph-empty { text-align: center; padding: 46px 10px; color: rgba(233,234,255,.7); }
        .of-ph-empty big { display: block; font-size: 2.4rem; margin-bottom: 6px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $ofPageTitle ?? 'Order Fulfillment',
        'sub_title'  => 'Sales',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div id="of-toolbar" class="mb-2">
                        @if(!empty($ofCreateOrders))
                            <button type="button" class="btn btn-sm btn-primary" id="of-create-order-btn"><i class="fas fa-plus me-1" aria-hidden="true"></i>Create order</button>
                        @endif
                        <span class="badge bg-dark of-summary-badge" id="of-rows-badge" title="Number of rows currently shown after filters">Rows: <span id="of-order-count">0</span></span>
                        <button type="button" class="badge bg-secondary of-summary-badge" id="of-channels-badge" title="Show connected marketplaces">Channels: <span id="of-channel-count">0</span></button>
                        <span class="badge of-summary-badge" style="background:#d1e7dd; color:#0f5132;">Paid: <span id="of-paid-count">0</span></span>
                        <span class="badge of-summary-badge" style="background:#fff3cd; color:#856404;">Unpaid: <span id="of-unpaid-count">0</span></span>
                        @if(!empty($ofPendingOnly))
                            <button type="button" class="of-ph-badge" id="of-ph-badge" title="Pending orders by site: hourly history graph and the 3:30 PM Ohio daily record">
                                <span class="of-ph-pulse" aria-hidden="true"></span>
                                <i class="fas fa-chart-line" aria-hidden="true"></i>
                                Pending by site <b id="of-ph-total">…</b>
                                <span class="of-ph-delta" id="of-ph-delta"></span>
                            </button>
                            <button type="button" class="of-ph-badge of-ph-badge--daily" id="of-ph-badge-daily" title="Pending orders at 3:30 PM Ohio time, day by day">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                                3:30 PM Ohio <b id="of-ph-daily-total">—</b>
                            </button>
                        @endif
                        @if(!empty($ofScanPendingOnly))
                            <span class="badge of-summary-badge" style="background:#f8d7da; color:#b02a37;" title="Rows with a tracking number and no carrier scan, over 36 hours after the order time"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Alerts: <span id="of-alert-count">0</span></span>
                            <button type="button" class="badge of-summary-badge" id="of-days-badge" style="background:#dc3545; color:#fff; border:0; cursor:pointer;" title="Alert rows grouped by how many days old the order is">Days</button>
                        @endif
                        <input type="date" id="of-date-from" class="form-control form-control-sm" style="width:140px;" value="{{ $ofDateFrom ?? '' }}" min="{{ $ofDateEarliest ?? '2026-09-15' }}" title="From date">
                        <input type="date" id="of-date-to" class="form-control form-control-sm" style="width:140px;" value="{{ $ofDateTo ?? '' }}" min="{{ $ofDateEarliest ?? '2026-09-15' }}" title="To date">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="of-apply-dates">Apply</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="of-clear-dates">Last 30 days</button>
                        <input type="text" id="of-channel-filter" class="form-control form-control-sm" style="width:150px;" list="of-channel-datalist" placeholder="Channel…" autocomplete="off">
                        <datalist id="of-channel-datalist">
                            @foreach(($ofChannels ?? []) as $chOpt)
                                @if(($chOpt['slug'] ?? '') !== '')
                                    <option value="{{ $chOpt['label'] }}"></option>
                                @endif
                            @endforeach
                        </datalist>
                        <select id="of-status-filter" class="form-select form-select-sm" style="width:110px;" title="Status">
                            <option value="">Status</option>
                        </select>
                        @if(!empty($ofScanPendingOnly))
                            <select id="of-alert-filter" class="form-select form-select-sm" style="width:150px;" title="Show only rows with the red triangle alert (over 36 hours since the order time), or all rows">
                                <option value="alert" selected>&#9650; Triangle alert</option>
                                <option value="all">All</option>
                            </select>
                        @endif
                        <select id="of-paid-filter" class="form-select form-select-sm" style="width:130px;" title="Paid or unpaid">
                            <option value="">Paid / Unpaid</option>
                            <option value="Paid">Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                        <input type="text" id="of-search" class="form-control form-control-sm" style="min-width:180px; flex:1;" placeholder="Search…" autocomplete="off" title="Type any words from the row: channel, order id, SKU, status, date, carrier, or tracking">
                    </div>
                    <p class="small text-muted mb-2">@if(!empty($ofCreateOrders))Orders entered by hand for marketplaces without an API. They also appear on the Orders page under the marketplace name. A new order shows <strong>Order Created</strong>; use <i class="fas fa-check-circle" aria-hidden="true"></i> Fulfill once the label is bought — the tracking number is fetched from Veeqo / 4Seller at that moment and the status becomes Fulfilled.@elseif(!empty($ofDeliveredOnly))Orders from 15 Sep 2026 whose marketplace status or carrier tracking status is Delivered. The date filter defaults to the last 30 days.@elseif(!empty($ofTransitOnly))Orders from 15 Sep 2026 that are shipped or in transit and not yet delivered. The date filter defaults to the last 30 days.@elseif(!empty($ofScanPendingOnly))Orders from 15 Sep 2026 that have a tracking number and no carrier scan yet. The date filter defaults to the last 30 days.@elseif(!empty($ofUnpaidOnly))Unpaid orders from 15 Sep 2026. The date filter defaults to the last 30 days.@elseif(!empty($ofPendingOnly))Orders from 15 Sep 2026 that are still waiting to ship. Search matches channel, order id, SKU, status, carrier, and tracking. Use Status to show one marketplace status. The date filter defaults to the last 30 days.@else Orders from 15 Sep 2026. The date filter defaults to the last 30 days. Search matches channel, order id, SKU, status, carrier, and tracking. Carrier and tracking status come from the tracking number (USPS, GOFO, FedEx, UPS).@endif</p>
                    <div id="order-fulfillment-table" style="height: calc(100vh - 280px);"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="of-channels-modal" tabindex="-1" aria-labelledby="of-channels-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="of-channels-modal-label">Connected marketplaces</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="list-group list-group-flush" id="of-channel-list"></ul>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($ofPendingOnly))
    <div class="modal fade of-ph-modal" id="of-ph-modal" tabindex="-1" aria-labelledby="of-ph-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="of-ph-head">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <span class="of-ph-pill"><i class="fas fa-bolt" aria-hidden="true"></i> Refreshes every hour</span>
                    <h5 id="of-ph-title">Pending orders by site</h5>
                    <div class="of-ph-sub" id="of-ph-updated">Loading…</div>
                    <div class="of-ph-stats">
                        <div class="of-ph-stat"><b id="of-ph-s-now">—</b><span>Pending now</span></div>
                        <div class="of-ph-stat"><b id="of-ph-s-delta">—</b><span>vs last hour</span></div>
                        <button type="button" class="of-ph-stat of-ph-stat-btn" data-ph-tab="daily" title="Open the day-by-day 3:30 PM graph">
                            <b id="of-ph-s-daily">—</b><span id="of-ph-s-daily-label">3:30 PM record (Ohio)</span>
                            <em><i class="fas fa-chart-line" aria-hidden="true"></i> day-by-day graph</em>
                        </button>
                        <div class="of-ph-stat"><b id="of-ph-s-sites">—</b><span>Sites with pending</span></div>
                    </div>
                </div>
                <div class="of-ph-body">
                    <div class="of-ph-tools">
                        <button type="button" class="of-ph-btn is-on" data-ph-tab="hourly">Hourly</button>
                        <button type="button" class="of-ph-btn" data-ph-tab="daily">3:30 PM each day</button>
                        <span class="of-ph-spacer"></span>
                        <button type="button" class="of-ph-btn" data-ph-range="24h">24h</button>
                        <button type="button" class="of-ph-btn is-on" data-ph-range="7d">7d</button>
                        <button type="button" class="of-ph-btn" data-ph-range="30d">30d</button>
                        <button type="button" class="of-ph-btn" id="of-ph-reload" title="Reload the saved history"><i class="fas fa-rotate" aria-hidden="true"></i></button>
                    </div>
                    <div id="of-ph-content">
                        <div class="of-ph-chartbox" id="of-ph-chartbox"><canvas id="of-ph-canvas"></canvas></div>
                        <div class="of-ph-empty" id="of-ph-tab-empty" style="display:none;"></div>
                        <div class="of-ph-hint" id="of-ph-hint"></div>
                        <div id="of-ph-picker">
                            <div class="of-ph-picker-title" id="of-ph-picker-title"></div>
                            <div class="of-ph-sites" id="of-ph-sites"></div>
                        </div>
                        <div id="of-ph-daily-table"></div>
                    </div>
                    <div class="of-ph-empty" id="of-ph-empty" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!empty($ofScanPendingOnly))
    <div class="modal fade" id="of-days-modal" tabindex="-1" aria-labelledby="of-days-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="of-days-modal-label"><i class="fas fa-exclamation-triangle text-danger me-1" aria-hidden="true"></i>Alert rows by days</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr><th class="ps-3">Days</th><th class="text-end pe-3">Rows</th></tr>
                        </thead>
                        <tbody id="of-days-list"></tbody>
                        <tfoot id="of-days-foot"></tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="modal fade" id="of-tracking-modal" tabindex="-1" aria-labelledby="of-tracking-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="of-tracking-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="of-tracking-modal-label">Add tracking number</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="of-track-id">
                        <input type="hidden" id="of-track-slug">
                        <div class="mb-2">
                            <div class="small text-muted">Channel</div>
                            <div id="of-track-channel" class="fw-semibold"></div>
                        </div>
                        <div class="mb-2">
                            <div class="small text-muted">Order ID</div>
                            <div id="of-track-order" class="fw-semibold"></div>
                        </div>
                        <div class="mb-3">
                            <div class="small text-muted">SKU</div>
                            <div id="of-track-sku"></div>
                        </div>
                        <label for="of-track-number" class="form-label">Tracking number</label>
                        <input type="text" class="form-control" id="of-track-number" maxlength="128" required autocomplete="off">
                        <div class="mt-3" id="of-track-pdf-wrap" style="display:none;">
                            <label for="of-track-pdf" class="form-label">PDF name</label>
                            <input type="text" class="form-control" id="of-track-pdf" maxlength="191" autocomplete="off" placeholder="PDF name">
                        </div>
                        <div class="mt-3" id="of-track-status-wrap" style="display:none;">
                            <label for="of-track-status" class="form-label">Tracking status</label>
                            <select class="form-select" id="of-track-status">
                                <option value="blank">Select status</option>
                                <option value="info_received">Label Created / No Scan</option>
                                <option value="preshipment">Preshipment</option>
                                <option value="pending">Pending</option>
                                <option value="in_transit">In transit</option>
                                <option value="lost">Lost in transit</option>
                                <option value="delivered">Delivered</option>
                            </select>
                        </div>
                        <div id="of-track-error" class="text-danger small mt-2" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="of-track-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Manual order: create / edit --}}
    <div class="modal fade of-modal" id="of-order-modal" tabindex="-1" aria-labelledby="of-order-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="of-order-form" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="of-order-modal-label">Create order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="of-order-manual-id" value="">

                        <div class="of-sec">
                            <div class="of-sec-title">
                                <h6><i class="fas fa-receipt" aria-hidden="true"></i>Order details</h6>
                                <span class="of-sec-sub">Times are {{ $ofTimezone ?? 'America/Los_Angeles' }}</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label" for="of-order-marketplace-select">Marketplace <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="of-order-marketplace-select" required>
                                        <option value="">Select…</option>
                                        @foreach(($ofManualMarketplaces ?? []) as $mpName)
                                            <option value="{{ $mpName }}">{{ $mpName }}</option>
                                        @endforeach
                                        <option value="__other__">Other (type a name)…</option>
                                    </select>
                                    <input type="text" class="form-control form-control-sm mt-1" id="of-order-marketplace" maxlength="128" placeholder="New marketplace name" style="display:none;">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="of-order-order-id">Order ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-order-id" maxlength="128" required placeholder="Marketplace order #">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="of-order-date">Order date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control form-control-sm" id="of-order-date" required min="{{ ($ofDateEarliest ?? '2026-09-15') }}T00:00">
                                </div>
                                <div class="col-md-3">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label" for="of-order-paid">Payment</label>
                                            <select class="form-select form-select-sm" id="of-order-paid">
                                                <option value="1">Paid</option>
                                                <option value="0">Unpaid</option>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label" for="of-order-amount">Amount</label>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" id="of-order-amount" placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="of-amount-hint mt-1 text-end" id="of-order-amount-hint">auto: items × qty</div>
                                </div>
                            </div>
                        </div>

                        <div class="of-sec">
                            <div class="of-sec-title">
                                <h6><i class="fas fa-box-open" aria-hidden="true"></i>Items <span class="text-danger">*</span></h6>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="of-order-add-line" style="font-size:0.75rem;"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add SKU</button>
                            </div>
                            <div class="of-order-lines-head">
                                <div class="of-line-sku-wrap">SKU</div>
                                <div class="of-line-qty">Qty</div>
                                <div class="of-line-price">Price</div>
                                <div class="of-line-total">Total</div>
                                <div class="of-line-remove"></div>
                            </div>
                            <div id="of-order-lines"></div>
                            <div class="of-order-lines-foot">
                                <span id="of-order-lines-help">One grid row is created per SKU.</span>
                                <span><span id="of-order-lines-count">0 items</span> &middot; Items total <strong id="of-order-lines-total">—</strong></span>
                            </div>
                        </div>

                        <div class="of-sec">
                            <div class="of-sec-title">
                                <h6><i class="fas fa-user" aria-hidden="true"></i>Customer &amp; shipping</h6>
                                <span class="of-sec-sub">Optional</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label" for="of-order-customer">Customer name</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-customer" maxlength="191">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="of-order-email">Email</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-email" maxlength="191">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="of-order-phone">Phone</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-phone" maxlength="64">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="of-order-address1">Address line 1</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-address1" maxlength="191">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="of-order-address2">Address line 2</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-address2" maxlength="191">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="of-order-city">City</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-city" maxlength="128">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="of-order-state">State</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-state" maxlength="128">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="of-order-zip">ZIP</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-zip" maxlength="32">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="of-order-country">Country</label>
                                    <input type="text" class="form-control form-control-sm" id="of-order-country" maxlength="64" value="US">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="of-order-notes">Notes</label>
                                    <textarea class="form-control form-control-sm" id="of-order-notes" rows="1" maxlength="5000" placeholder="Internal notes (optional)"></textarea>
                                </div>
                            </div>
                        </div>

                        <div id="of-order-error" class="text-danger small mb-1" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <span class="of-footer-summary" id="of-order-footer-summary"></span>
                        <span>
                            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary px-3" id="of-order-save">Create order</button>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Manual order: read-only view of the complete order --}}
    <div class="modal fade of-modal" id="of-view-modal" tabindex="-1" aria-labelledby="of-view-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="of-view-modal-label">Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="of-sec">
                        <div class="of-sec-title">
                            <h6><i class="fas fa-receipt" aria-hidden="true"></i>Order details</h6>
                            <span id="of-view-status-badge"></span>
                        </div>
                        <div class="of-view-grid" id="of-view-order"></div>
                    </div>
                    <div class="of-sec">
                        <div class="of-sec-title">
                            <h6><i class="fas fa-box-open" aria-hidden="true"></i>Items</h6>
                            <span class="of-sec-sub" id="of-view-items-sub"></span>
                        </div>
                        <table class="table of-view-table">
                            <thead>
                                <tr><th>SKU</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th><th class="text-center">Inv.</th></tr>
                            </thead>
                            <tbody id="of-view-lines"></tbody>
                            <tfoot id="of-view-lines-foot"></tfoot>
                        </table>
                    </div>
                    <div class="of-sec">
                        <div class="of-sec-title">
                            <h6><i class="fas fa-truck" aria-hidden="true"></i>Shipping &amp; tracking</h6>
                        </div>
                        <div class="of-view-grid" id="of-view-shipping"></div>
                    </div>
                    <div class="of-sec">
                        <div class="of-sec-title">
                            <h6><i class="fas fa-user" aria-hidden="true"></i>Customer</h6>
                        </div>
                        <div class="of-view-grid" id="of-view-customer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <span class="of-footer-summary" id="of-view-footer"></span>
                    <span>
                        @if(!empty($ofCreateOrders))
                        <button type="button" class="btn btn-sm btn-outline-primary" id="of-view-edit"><i class="fas fa-edit me-1" aria-hidden="true"></i>Edit</button>
                        @endif
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Manual order: fulfil when no tracking number could be fetched --}}
    <div class="modal fade" id="of-fulfill-modal" tabindex="-1" aria-labelledby="of-fulfill-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="of-fulfill-form" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="of-fulfill-modal-label">Mark as fulfilled</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="of-fulfill-manual-id" value="">
                        <div class="mb-2">
                            <div class="small text-muted">Order</div>
                            <div id="of-fulfill-order" class="fw-semibold"></div>
                        </div>
                        <div class="alert alert-warning py-2 small" id="of-fulfill-message"></div>
                        <label for="of-fulfill-tracking" class="form-label">Tracking number</label>
                        <input type="text" class="form-control" id="of-fulfill-tracking" maxlength="128" placeholder="Enter the number from the label">
                        <div id="of-fulfill-error" class="text-danger small mt-2" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Not yet</button>
                        <button type="button" class="btn btn-outline-primary" id="of-fulfill-retry">Search again</button>
                        <button type="submit" class="btn btn-primary" id="of-fulfill-save">Fulfill with this number</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const defaultFrom = @json($ofDateFrom ?? '');
    const defaultTo = @json($ofDateTo ?? '');
    const earliestDate = @json($ofDateEarliest ?? '2026-09-15');
    const deliveredOnly = @json((bool) ($ofDeliveredOnly ?? false));
    const transitOnly = @json((bool) ($ofTransitOnly ?? false));
    const scanPendingOnly = @json((bool) ($ofScanPendingOnly ?? false));
    const unpaidOnly = @json((bool) ($ofUnpaidOnly ?? false));
    const pendingOnly = @json((bool) ($ofPendingOnly ?? false));
    const createOrdersPage = @json((bool) ($ofCreateOrders ?? false));
    const pageKey = createOrdersPage ? 'create_orders'
        : (deliveredOnly ? 'delivered'
        : (transitOnly ? 'transit'
        : (scanPendingOnly ? 'scan_pending'
        : (unpaidOnly ? 'unpaid'
        : (pendingOnly ? 'pending' : 'orders')))));
    const statusStorageKey = 'of-status-filter:' + pageKey;
    // Scan Pending lists the oldest orders first and can show only the red triangle alerts.
    const defaultSortDir = scanPendingOnly ? 'asc' : 'desc';
    function alertsOnly() {
        return scanPendingOnly && String(document.getElementById('of-alert-filter')?.value || 'alert') === 'alert';
    }
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function clampDateInput(id) {
        const el = document.getElementById(id);
        if (!el || !el.value) return;
        if (earliestDate && el.value < earliestDate) el.value = earliestDate;
    }

    function dateParams() {
        clampDateInput('of-date-from');
        clampDateInput('of-date-to');
        const params = {
            date_from: document.getElementById('of-date-from')?.value || defaultFrom,
            date_to: document.getElementById('of-date-to')?.value || defaultTo,
        };
        if (deliveredOnly) params.delivered = 1;
        if (transitOnly) params.transit = 1;
        if (scanPendingOnly) params.scan_pending = 1;
        if (unpaidOnly) params.unpaid = 1;
        if (pendingOnly) params.pending = 1;
        if (createOrdersPage) params.manual = 1;
        return params;
    }

    // Manual orders share one slug; count each typed marketplace name on its own.
    function channelKey(row) {
        if (!row) return '';
        if (row.mm_slug === 'manual') return 'manual:' + String(row.channel || '').trim().toLowerCase();
        return String(row.mm_slug || row.channel || '').trim();
    }

    function formatDateTime(raw) {
        const m = String(raw || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
        if (!m) return null;
        const day = String(Number(m[3]));
        const month = months[Number(m[2]) - 1] || m[2];
        const date = day + ' ' + month + ' ' + m[1];
        if (!m[4]) return { date: date, time: '' };
        let hour = Number(m[4]);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;
        return { date: date, time: hour + ':' + m[5] + ' ' + ampm };
    }

    function setCount(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = Number(value || 0).toLocaleString();
    }

    function countsFromRows(rows) {
        const channels = {};
        let paid = 0;
        let unpaid = 0;
        (rows || []).forEach(function (row) {
            const key = channelKey(row);
            if (key) channels[key] = true;
            if (row.paid) paid++;
            else unpaid++;
        });
        setCount('of-order-count', rows.length);
        setCount('of-channel-count', Object.keys(channels).length);
        setCount('of-paid-count', paid);
        setCount('of-unpaid-count', unpaid);
        if (scanPendingOnly) {
            setCount('of-alert-count', (rows || []).filter(function (row) { return row.scan_alert; }).length);
        }
        // The sidebar count is every Scan Pending row, not just the alerts shown.
        setNavCount(pageKey, alertsOnly() ? table.getData().length : rows.length);
    }

    function setNavCount(key, value) {
        document.querySelectorAll('[data-of-count="' + key + '"]').forEach(function (el) {
            el.textContent = Number(value || 0).toLocaleString();
        });
    }

    function setNavCounts(counts) {
        if (!counts) return;
        Object.keys(counts).forEach(function (key) {
            setNavCount(key, counts[key]);
        });
        refreshSavedNavCounts();
    }

    let navStatusCounts = null;

    function savedStatusesFor(key) {
        try {
            const parsed = JSON.parse(localStorage.getItem('of-status-filter:' + key) || '');
            if (!parsed || parsed.all) return null;
            return Array.isArray(parsed.statuses) ? parsed.statuses : null;
        } catch (e) {
            return null;
        }
    }

    function readSavedStatuses() {
        return savedStatusesFor(pageKey);
    }

    function refreshSavedNavCounts() {
        ['orders', 'pending', 'unpaid', 'scan_pending', 'transit', 'delivered'].forEach(function (key) {
            if (key === pageKey || !navStatusCounts || !navStatusCounts[key]) return;
            const map = navStatusCounts[key];
            const saved = savedStatusesFor(key);
            const total = Object.keys(map).reduce(function (sum, status) {
                if (saved !== null && saved.indexOf(status) === -1) return sum;
                return sum + Number(map[status] || 0);
            }, 0);
            setNavCount(key, total);
        });
    }

    function writeSavedStatuses(all, statuses) {
        localStorage.setItem(statusStorageKey, JSON.stringify({
            all: !!all,
            statuses: statuses || [],
        }));
    }

    function statusLabel(row) {
        const value = String((row && row.status) || '').trim();
        return value && value !== '—' ? value : '—';
    }

    function selectedStatuses() {
        const el = document.getElementById('of-status-filter');
        const value = String(el?.value || '').trim();
        return value === '' ? null : [value];
    }

    function renderStatusOptions(rows) {
        const el = document.getElementById('of-status-filter');
        if (!el) return;
        const counts = {};
        (rows || []).forEach(function (row) {
            const label = statusLabel(row);
            counts[label] = (counts[label] || 0) + 1;
        });
        const labels = Object.keys(counts).sort(function (a, b) {
            return a.localeCompare(b);
        });
        const saved = readSavedStatuses();
        const current = String(el.value || '').trim();
        const preferred = current || (saved && saved.length === 1 ? saved[0] : '');
        el.innerHTML = '<option value="">Status</option>' + labels.map(function (label) {
            return '<option value="' + escapeHtml(label) + '">' + escapeHtml(label) + ' (' + counts[label].toLocaleString() + ')</option>';
        }).join('');
        if (preferred && labels.indexOf(preferred) !== -1) {
            el.value = preferred;
        } else {
            el.value = '';
        }
    }

    function rowSearchText(data) {
        const parts = [
            data && data.channel,
            data && data.order_id,
            data && data.sku,
            data && data.status,
            data && data.tracking,
            data && data.carrier,
            data && data.tracking_status,
            data && data.paid_label,
            data && data.customer_name,
            data && data.customer_email,
            data && data.city,
            data && data.state,
            data && data.zip,
            data && data.address1,
            data && data.reference,
        ];
        const shown = formatDateTime(data && data.order_date);
        if (shown) {
            parts.push(shown.date, shown.time);
        }
        if (data && Array.isArray(data.lines)) {
            data.lines.forEach(function (line) {
                parts.push(line && line.sku);
            });
        }
        return parts.map(function (value) {
            return String(value || '').toLowerCase();
        }).join(' ');
    }

    function applyFilters(table) {
        const q = String(document.getElementById('of-search')?.value || '').trim().toLowerCase();
        const channel = String(document.getElementById('of-channel-filter')?.value || '').trim().toLowerCase();
        const paid = String(document.getElementById('of-paid-filter')?.value || '').trim();
        const statuses = selectedStatuses();
        const onlyAlerts = alertsOnly();
        if (!q && !channel && !paid && statuses === null && !onlyAlerts) {
            table.clearFilter(true);
            countsFromRows(table.getData());
            return;
        }
        const words = q.split(/\s+/).filter(Boolean);
        table.setFilter(function (data) {
            if (onlyAlerts && !data.scan_alert) return false;
            if (statuses && statuses.indexOf(statusLabel(data)) === -1) return false;
            if (paid && String(data.paid_label || '') !== paid) return false;
            if (channel && !String(data.channel || '').toLowerCase().includes(channel)) return false;
            if (!words.length) return true;
            const hay = rowSearchText(data);
            return words.every(function (word) { return hay.indexOf(word) !== -1; });
        });
        countsFromRows(table.getData('active'));
    }

    // Tabulator only reads the placeholder option once; write the text into the DOM too.
    function showTablePlaceholder(message) {
        if (!table) return;
        table.options.placeholder = message;
        const el = document.querySelector('#order-fulfillment-table .tabulator-placeholder-contents')
            || document.querySelector('#order-fulfillment-table .tabulator-placeholder');
        if (el) el.textContent = message;
    }

    const table = new Tabulator('#order-fulfillment-table', {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 500, true],
        index: 'id',
        layout: 'fitColumns',
        placeholder: 'Loading orders…',
        initialSort: [{ column: 'order_date', dir: defaultSortDir }],
        ajaxURL: @json(route('order.fulfillment.data')),
        ajaxConfig: 'GET',
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, dateParams()),
                    timeout: 90000,
                    success: resolve,
                    error: function (xhr) {
                        let message = 'Could not load orders. Narrow the dates and try again.';
                        try {
                            const body = JSON.parse(xhr.responseText || '{}');
                            if (body && body.message) message = body.message;
                        } catch (e) { /* keep default */ }
                        if (xhr && xhr.status) message += ' (HTTP ' + xhr.status + ')';
                        else if (xhr && xhr.statusText === 'timeout') message += ' (timed out)';
                        showTablePlaceholder(message);
                        reject(xhr);
                    },
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data)) ? response.data : [];
            if (response && response.date_from) {
                const from = document.getElementById('of-date-from');
                if (from) from.value = response.date_from;
            }
            if (response && response.date_to) {
                const to = document.getElementById('of-date-to');
                if (to) to.value = response.date_to;
            }
            setCount('of-order-count', response && response.count != null ? response.count : rows.length);
            setCount('of-channel-count', response && response.channel_count);
            setCount('of-paid-count', response && response.paid_count);
            setCount('of-unpaid-count', response && response.unpaid_count);
            setCount('of-alert-count', response && response.alert_count);
            navStatusCounts = (response && response.nav_status_counts) || null;
            setNavCounts(response && response.nav_counts);
            if (response && response.success === false) {
                showTablePlaceholder(response.message || 'Failed to load orders.');
            } else if (!rows.length) {
                showTablePlaceholder(createOrdersPage ? 'No manual orders in this date range yet. Click "Create order" to add one.' : 'No orders in this date range.');
            }
            return rows;
        },
        dataLoaded: function () {
            table.setSort([{ column: 'order_date', dir: defaultSortDir }]);
            renderStatusOptions(table.getData());
            applyFilters(table);
            setTimeout(function () {
                statusWave = 0;
                statusFailCount = 0;
                fillTracking(0);
                fillTrackingStatus(0);
            }, 400);
        },
        columns: [
            {
                title: 'Channels',
                field: 'channel',
                minWidth: 120,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    return '<span style="font-weight:600;">' + escapeHtml(cell.getValue() || '—') + '</span>';
                },
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 160,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 180,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    if (row.manual && Array.isArray(row.lines) && row.lines.length) {
                        return '<span class="of-sku-list">' + row.lines.map(function (line) {
                            const qty = Number(line.qty || 1);
                            return '<span class="of-sku-line"><code class="of-sku">' + escapeHtml(line.sku || '') + '</code>'
                                + (qty > 1 ? '<small class="of-sku-qty">×' + qty + '</small>' : '') + '</span>';
                        }).join('') + '</span>';
                    }
                    const sku = String(cell.getValue() || '').trim();
                    return sku ? '<code class="of-sku">' + escapeHtml(sku) + '</code>' : '—';
                },
            },
            {
                title: 'Order Date and Time',
                field: 'order_date',
                minWidth: 140,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const parts = formatDateTime(cell.getValue());
                    if (!parts) return '—';
                    let html = '<span class="of-dt-cell"><span class="of-dt-date">' + escapeHtml(parts.date) + '</span>';
                    if (parts.time) html += '<span class="of-dt-time">' + escapeHtml(parts.time) + '</span>';
                    html += '</span>';
                    if (scanPendingOnly && cell.getRow().getData().scan_alert) {
                        html = '<span class="of-alert-wrap"><i class="fas fa-exclamation-triangle of-alert-icon" title="Over 36 hours since the order time and still no carrier scan" aria-label="Alert"></i>' + html + '</span>';
                    }
                    return html;
                },
            },
            {
                title: 'Paid / Unpaid',
                field: 'paid_label',
                minWidth: 110,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '');
                    if (value === 'Paid') return '<span class="of-paid">Paid</span>';
                    if (value === 'Unpaid') return '<span class="of-unpaid">Unpaid</span>';
                    return '—';
                },
            },
            {
                title: 'Order Amt',
                field: 'order_amt',
                minWidth: 90,
                width: 105,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Total amount of the whole order (all its SKUs)',
                visible: !createOrdersPage,
                formatter: function (cell) {
                    const value = cell.getValue();
                    if (value === null || value === undefined || value === '' || isNaN(Number(value))) return '—';
                    return '$' + Number(value).toFixed(2);
                },
            },
            {
                title: 'Amount',
                field: 'amount',
                minWidth: 90,
                width: 100,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                visible: createOrdersPage,
                formatter: function (cell) {
                    const value = cell.getValue();
                    if (value === null || value === undefined || value === '') return '—';
                    return '$' + Number(value).toFixed(2);
                },
            },
            {
                title: 'Status',
                field: 'status',
                minWidth: 140,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value && value !== '—' ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'Tracking',
                field: 'tracking',
                minWidth: 150,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    if (!value) {
                        return cell.getRow().getData().tracking_checked ? '—' : '…';
                    }
                    const source = String(cell.getRow().getData().tracking_source || '');
                    const label = source === '4seller' ? '4Seller' : (source === 'gofo' ? 'GOFO' : (source === 'veeqo' ? 'Veeqo' : (source === 'channel' ? 'Marketplace' : (source === 'manual' ? 'Manual' : ''))));
                    const title = label ? ' title="' + escapeHtml(label) + '"' : '';
                    return '<span class="of-tracking"' + title + '>' + escapeHtml(value) + '</span>';
                },
            },
            {
                title: 'Carrier',
                field: 'carrier',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'PDF',
                field: 'pdf_name',
                width: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                visible: scanPendingOnly,
                formatter: function (cell) {
                    const name = String(cell.getValue() || '').trim();
                    const tip = name !== '' ? name : 'No PDF name';
                    return '<span class="of-pdf-dot ' + (name !== '' ? 'is-set' : 'is-empty') + '" title="' + escapeHtml(tip) + '" aria-label="' + escapeHtml(tip) + '"></span>';
                },
            },
            {
                title: 'Tracking Status',
                field: 'tracking_status',
                minWidth: 150,
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const value = String(cell.getValue() || '').trim();
                    return value ? escapeHtml(value) : '—';
                },
            },
            {
                title: 'Inv.',
                field: 'inv',
                minWidth: 70,
                width: 80,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    if (row.manual && Array.isArray(row.lines) && row.lines.length > 1) {
                        return '<span class="of-sku-list">' + row.lines.map(function (line) {
                            const inv = line.inv;
                            return '<span class="of-sku-line">' + ((inv === null || inv === undefined || inv === '') ? '—' : Number(inv).toLocaleString()) + '</span>';
                        }).join('') + '</span>';
                    }
                    const value = cell.getValue();
                    if (value === null || value === undefined || value === '') return '—';
                    return Number(value).toLocaleString();
                },
            },
            {
                title: 'Edit',
                field: 'id',
                width: createOrdersPage ? 175 : 125,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const wrap = document.createElement('span');
                    wrap.className = 'of-actions';
                    const add = function (icon, title, cls, handler, disabled) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-sm btn-link of-edit-btn ' + (cls || '');
                        btn.title = title;
                        btn.disabled = !!disabled;
                        btn.innerHTML = '<i class="fas ' + icon + '" aria-hidden="true"></i>';
                        btn.addEventListener('click', function (ev) {
                            ev.preventDefault();
                            ev.stopPropagation();
                            handler(cell.getRow().getData());
                        });
                        wrap.appendChild(btn);
                    };
                    add('fa-pen', 'Add tracking number', '', openTrackingModal);
                    if (row.manual) {
                        add('fa-eye', 'View complete order', '', openOrderViewModal);
                        const fulfilled = String(row.status || '').toLowerCase() === 'fulfilled';
                        add('fa-check-circle', fulfilled ? 'Fulfilled' : 'Fulfill (fetch tracking and mark fulfilled)', fulfilled ? 'text-success' : '', fulfillManualOrder, fulfilled);
                        if (createOrdersPage) {
                            add('fa-edit', 'Edit order', '', openOrderModalForEdit);
                            add('fa-trash', 'Delete order', 'text-danger', deleteManualOrder);
                        }
                    }
                    return wrap;
                },
            },
        ],
    });

    document.getElementById('of-status-filter')?.addEventListener('change', function () {
        const value = String(this.value || '').trim();
        writeSavedStatuses(value === '', value === '' ? [] : [value]);
        applyFilters(table);
        refreshSavedNavCounts();
    });

    ['of-search', 'of-channel-filter', 'of-paid-filter', 'of-alert-filter'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', function () { applyFilters(table); });
        document.getElementById(id)?.addEventListener('change', function () { applyFilters(table); });
    });

    document.getElementById('of-apply-dates')?.addEventListener('click', function () {
        table.setData();
    });
    document.getElementById('of-date-from')?.addEventListener('change', function () {
        table.setData();
    });
    document.getElementById('of-date-to')?.addEventListener('change', function () {
        table.setData();
    });
    function rowsForCounts() {
        const q = String(document.getElementById('of-search')?.value || '').trim();
        const channel = String(document.getElementById('of-channel-filter')?.value || '').trim();
        const paid = String(document.getElementById('of-paid-filter')?.value || '').trim();
        if (!q && !channel && !paid) return table.getData();
        return table.getData('active');
    }

    function openChannelsModal() {
        const grouped = {};
        rowsForCounts().forEach(function (row) {
            const slug = channelKey(row);
            if (!slug) return;
            if (!grouped[slug]) {
                grouped[slug] = { label: String(row.channel || slug), count: 0 };
            }
            grouped[slug].count += 1;
        });
        const list = Object.keys(grouped).map(function (slug) { return grouped[slug]; })
            .sort(function (a, b) { return a.label.localeCompare(b.label); });
        const ul = document.getElementById('of-channel-list');
        if (ul) {
            ul.innerHTML = list.length
                ? list.map(function (ch) {
                    return '<li class="list-group-item d-flex justify-content-between align-items-center">'
                        + '<span>' + escapeHtml(ch.label) + '</span>'
                        + '<span class="badge bg-light text-dark border">' + Number(ch.count).toLocaleString() + '</span>'
                        + '</li>';
                }).join('')
                : '<li class="list-group-item text-muted">No marketplaces in this view.</li>';
        }
        const el = document.getElementById('of-channels-modal');
        if (window.bootstrap && bootstrap.Modal && el) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }

    document.getElementById('of-channels-badge')?.addEventListener('click', openChannelsModal);

    // Red-alert rows only, grouped by whole days since the order time (36h+ is 1 day and up).
    function openDaysModal() {
        const byDays = {};
        let total = 0;
        rowsForCounts().forEach(function (row) {
            if (!row.scan_alert) return;
            const days = Number(row.scan_alert_days);
            if (!isFinite(days)) return;
            byDays[days] = (byDays[days] || 0) + 1;
            total++;
        });
        const keys = Object.keys(byDays).map(Number).sort(function (a, b) { return a - b; });
        const body = document.getElementById('of-days-list');
        if (body) {
            body.innerHTML = keys.length
                ? keys.map(function (d) {
                    return '<tr><td class="ps-3">' + d + (d === 1 ? ' day' : ' days') + '</td>'
                        + '<td class="text-end pe-3">' + byDays[d].toLocaleString() + '</td></tr>';
                }).join('')
                : '<tr><td colspan="2" class="text-muted text-center py-3">No alert rows.</td></tr>';
        }
        const foot = document.getElementById('of-days-foot');
        if (foot) {
            foot.innerHTML = keys.length
                ? '<tr class="fw-semibold"><td class="ps-3">Total</td><td class="text-end pe-3">' + total.toLocaleString() + '</td></tr>'
                : '';
        }
        const el = document.getElementById('of-days-modal');
        if (window.bootstrap && bootstrap.Modal && el) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }
    document.getElementById('of-days-badge')?.addEventListener('click', openDaysModal);

    document.getElementById('of-clear-dates')?.addEventListener('click', function () {
        const from = document.getElementById('of-date-from');
        const to = document.getElementById('of-date-to');
        if (from) from.value = defaultFrom;
        if (to) to.value = defaultTo;
        table.setData();
    });

    const lookupUrl = @json(route('order.fulfillment.tracking.lookup'));
    const saveUrl = @json(route('order.fulfillment.tracking.save'));
    const statusUrl = @json(route('order.fulfillment.tracking.status'));
    let trackingLookupRunning = false;
    let trackingFailCount = 0;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function applyTrackingUpdate(update) {
        const row = table.getRow(update.id);
        if (!row) return;
        const patch = {
            tracking: update.tracking || '',
            tracking_source: update.tracking_source || '',
            tracking_checked: true,
            carrier: update.carrier || '',
            tracking_status: update.tracking_status || '',
            tracking_status_checked: !!update.tracking_status_checked,
        };
        if (Object.prototype.hasOwnProperty.call(update, 'pdf_name')) {
            patch.pdf_name = update.pdf_name || '';
        }
        row.update(patch);
    }

    function applyStatusUpdate(update) {
        const number = String(update.tracking || '').replace(/\s+/g, '').toUpperCase();
        const orderId = String(update.order_id || '').trim();
        const slug = String(update.mm_slug || '').trim().toLowerCase();
        if (!number && !orderId) return;
        table.getRows().forEach(function (row) {
            const data = row.getData();
            const current = String(data.tracking || '').replace(/\s+/g, '').toUpperCase();
            const sameNumber = number && current === number;
            const sameOrder = orderId && String(data.order_id || '').trim() === orderId
                && (!slug || String(data.mm_slug || '').trim().toLowerCase() === slug)
                && (!current || current === number);
            if (!sameNumber && !sameOrder) return;
            row.update({
                carrier: update.carrier || data.carrier || '',
                tracking_status: update.tracking_status || data.tracking_status || '',
                tracking_status_checked: !!update.tracking_status_checked,
            });
        });
    }

    const TRACKING_ROWS_PER_REQUEST = {{ (int) \App\Http\Controllers\Channels\OrderFulfillmentController::TRACKING_ROWS_PER_REQUEST }};

    function needsTracking(row) {
        return row && row.id && !row.tracking_checked && !String(row.tracking || '').trim();
    }

    // Rows the user can see come first, then the rest in the current sort order.
    function pendingTrackingRows() {
        const seen = {};
        const out = [];
        const push = function (row) {
            if (!needsTracking(row) || seen[row.id]) return;
            seen[row.id] = true;
            out.push(row);
        };
        try { table.getRows('visible').forEach(function (r) { push(r.getData()); }); } catch (e) { /* older tabulator */ }
        if (out.length < TRACKING_ROWS_PER_REQUEST) {
            try { table.getRows('active').forEach(function (r) { push(r.getData()); }); } catch (e) { /* fall through */ }
        }
        if (out.length < TRACKING_ROWS_PER_REQUEST) {
            table.getData().forEach(push);
        }
        return out.slice(0, TRACKING_ROWS_PER_REQUEST);
    }

    function fillTracking(attempt) {
        if (trackingLookupRunning || attempt > 400) return;
        const pending = pendingTrackingRows();
        if (!pending.length) return;
        trackingLookupRunning = true;
        $.ajax({
            url: lookupUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                rows: pending.map(function (row) {
                    return {
                        id: row.id,
                        mm_slug: row.mm_slug || '',
                        order_id: row.order_id || '',
                        sku: row.sku || '',
                        source_id: row.source_id || 0,
                    };
                }),
            },
        }).done(function (res) {
            const updates = (res && Array.isArray(res.updates)) ? res.updates : [];
            updates.forEach(applyTrackingUpdate);
            trackingFailCount = 0;
            trackingLookupRunning = false;
            if (updates.some(function (update) { return String(update.tracking || '').trim(); })) {
                statusWave = 0;
                fillTrackingStatus(0);
            }
            fillTracking(attempt + 1);
        }).fail(function () {
            trackingLookupRunning = false;
            trackingFailCount += 1;
            if (trackingFailCount < 3) {
                setTimeout(function () { fillTracking(attempt); }, 1500);
                return;
            }
            pending.forEach(function (row) {
                const live = table.getRow(row.id);
                if (live && !String(live.getData().tracking || '').trim()) {
                    live.update({ tracking_checked: true });
                }
            });
            trackingFailCount = 0;
            fillTracking(attempt + 1);
        });
    }

    // Paging, sorting, or filtering changes which rows are visible; restart the
    // lookup so those rows are checked next (no-op while a request is in flight).
    ['pageLoaded', 'dataSorted', 'dataFiltered'].forEach(function (event) {
        table.on(event, function () {
            setTimeout(function () { fillTracking(0); }, 250);
        });
    });

    let statusLookupRunning = false;
    let statusFailCount = 0;
    let statusWave = 0;

    function pendingStatusShipments() {
        const seen = {};
        const shipments = [];
        table.getData().forEach(function (row) {
            const number = String(row.tracking || '').trim();
            const key = number.replace(/\s+/g, '').toUpperCase();
            if (!key || key.length < 8 || row.tracking_status_checked || seen[key]) return;
            seen[key] = true;
            shipments.push({
                number: number,
                order_id: row.order_id || '',
                mm_slug: row.mm_slug || '',
                carrier: row.carrier || '',
            });
        });
        return shipments;
    }

    function fillTrackingStatus(attempt) {
        if (statusLookupRunning || attempt > 200) return;
        const shipments = pendingStatusShipments();
        const batch = shipments.slice(0, 10);
        if (!batch.length) return;
        statusLookupRunning = true;
        $.ajax({
            url: statusUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 50000,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { shipments: batch },
        }).done(function (res) {
            const updates = (res && Array.isArray(res.updates)) ? res.updates : [];
            updates.forEach(applyStatusUpdate);
            statusLookupRunning = false;
            statusFailCount = 0;
            if (shipments.length > batch.length) {
                fillTrackingStatus(attempt + 1);
                return;
            }
            statusWave += 1;
            if (statusWave < 3 && pendingStatusShipments().length) {
                setTimeout(function () { fillTrackingStatus(0); }, 3000);
            }
        }).fail(function () {
            statusLookupRunning = false;
            statusFailCount += 1;
            if (statusFailCount < 3) {
                setTimeout(function () { fillTrackingStatus(attempt); }, 1500);
            }
        });
    }

    function openTrackingModal(row) {
        document.getElementById('of-track-id').value = row.id || '';
        document.getElementById('of-track-slug').value = row.mm_slug || '';
        document.getElementById('of-track-channel').textContent = row.channel || '—';
        document.getElementById('of-track-order').textContent = row.order_id || '—';
        document.getElementById('of-track-sku').textContent = row.sku || '—';
        document.getElementById('of-track-number').value = row.tracking || '';
        const pdfWrap = document.getElementById('of-track-pdf-wrap');
        const pdfInput = document.getElementById('of-track-pdf');
        if (pdfWrap) pdfWrap.style.display = scanPendingOnly ? '' : 'none';
        if (pdfInput) pdfInput.value = scanPendingOnly ? (row.pdf_name || '') : '';
        const statusWrap = document.getElementById('of-track-status-wrap');
        const statusSelect = document.getElementById('of-track-status');
        if (statusWrap) statusWrap.style.display = scanPendingOnly ? '' : 'none';
        if (statusSelect && scanPendingOnly) {
            const current = String(row.tracking_status || '').trim().toLowerCase();
            const statusValue = {
                'delivered': 'delivered',
                'lost in transit': 'lost',
                'in transit': 'in_transit',
                'preshipment': 'preshipment',
                'pending': 'pending',
                'pending scan': 'pending',
                'label created / no scan': 'info_received',
            };
            statusSelect.value = statusValue[current] || 'blank';
        }
        const err = document.getElementById('of-track-error');
        err.style.display = 'none';
        err.textContent = '';
        const el = document.getElementById('of-tracking-modal');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
        setTimeout(function () { document.getElementById('of-track-number')?.focus(); }, 200);
    }

    document.getElementById('of-tracking-form')?.addEventListener('submit', function (ev) {
        ev.preventDefault();
        const err = document.getElementById('of-track-error');
        const saveBtn = document.getElementById('of-track-save');
        const number = String(document.getElementById('of-track-number')?.value || '').trim();
        const id = document.getElementById('of-track-id')?.value || '';
        if (!number) {
            err.textContent = 'Enter a tracking number.';
            err.style.display = 'block';
            return;
        }
        saveBtn.disabled = true;
        const payload = {
            id: id,
            mm_slug: document.getElementById('of-track-slug')?.value || '',
            order_id: document.getElementById('of-track-order')?.textContent === '—' ? '' : document.getElementById('of-track-order')?.textContent,
            sku: document.getElementById('of-track-sku')?.textContent === '—' ? '' : document.getElementById('of-track-sku')?.textContent,
            tracking_number: number,
            tracking_status: scanPendingOnly ? (document.getElementById('of-track-status')?.value || '') : '',
        };
        if (scanPendingOnly) {
            payload.pdf_name = document.getElementById('of-track-pdf')?.value || '';
        }
        $.ajax({
            url: saveUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: payload,
        }).done(function (res) {
            if (!res || res.success === false) {
                err.textContent = (res && res.message) || 'Could not save the tracking number.';
                err.style.display = 'block';
                return;
            }
            applyTrackingUpdate(res);
            const staysOnScanPending = ['', 'label created / no scan', 'pending', 'pending scan', 'preshipment'];
            if (scanPendingOnly && staysOnScanPending.indexOf(String(res.tracking_status || '').toLowerCase()) === -1) {
                const savedRow = table.getRow(id);
                if (savedRow) savedRow.delete();
            }
            fillTrackingStatus(0);
            const el = document.getElementById('of-tracking-modal');
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).hide();
            }
        }).fail(function (xhr) {
            const message = xhr && xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message
                : 'Could not save the tracking number.';
            err.textContent = message;
            err.style.display = 'block';
        }).always(function () {
            saveBtn.disabled = false;
        });
    });

    /* ----------------------------------------------------------------
     | Manual orders (Create Orders page + manual rows on the other pages)
     |----------------------------------------------------------------*/
    const manualStoreUrl = @json(route('order.fulfillment.manual.store'));
    const manualUrlBase = @json(url('/order-fulfillment/manual-orders'));

    function manualUrl(id, suffix) {
        return manualUrlBase + '/' + encodeURIComponent(id) + (suffix || '');
    }

    function showFormError(id, message) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = message || '';
        el.style.display = message ? 'block' : 'none';
    }

    function ajaxErrorMessage(xhr, fallback) {
        const body = xhr && xhr.responseJSON;
        if (body && body.errors) {
            const first = Object.keys(body.errors)[0];
            if (first && body.errors[first] && body.errors[first][0]) return body.errors[first][0];
        }
        return (body && body.message) || fallback;
    }

    function showModal(id) {
        const el = document.getElementById(id);
        if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
    }

    function hideModal(id) {
        const el = document.getElementById(id);
        if (el && window.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).hide();
    }

    function refreshAfterRowsChanged() {
        try {
            const sorters = table.getSorters();
            if (sorters.length) table.setSort(sorters.map(function (s) { return { column: s.field, dir: s.dir }; }));
        } catch (e) { /* keep current order */ }
        renderStatusOptions(table.getData());
        applyFilters(table);
        setNavCount('create_orders', table.getData().filter(function (r) { return r.manual; }).length);
        setTimeout(function () {
            statusWave = 0;
            statusFailCount = 0;
            fillTracking(0);
            fillTrackingStatus(0);
        }, 200);
    }

    // Order amount follows the item lines until the user types their own figure.
    // In edit mode the stored total is kept until a line changes.
    let amountTouched = false;
    let editingOrder = false;

    function money(n) {
        return Number(n || 0).toFixed(2);
    }

    function lineTotal(line) {
        const qty = parseInt(line.querySelector('.of-line-qty')?.value || '1', 10) || 1;
        const priceRaw = String(line.querySelector('.of-line-price')?.value || '').trim();
        if (priceRaw === '' || isNaN(parseFloat(priceRaw))) return null;
        return Math.round(parseFloat(priceRaw) * qty * 100) / 100;
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function updateFooterSummary() {
        const lines = document.querySelectorAll('#of-order-lines .of-order-line');
        let count = 0;
        let units = 0;
        lines.forEach(function (line) {
            if (String(line.querySelector('.of-line-sku')?.value || '').trim() === '') return;
            count += 1;
            units += parseInt(line.querySelector('.of-line-qty')?.value || '1', 10) || 1;
        });
        const amount = String(document.getElementById('of-order-amount')?.value || '').trim();
        const parts = [count + (count === 1 ? ' SKU' : ' SKUs') + ', ' + units + (units === 1 ? ' unit' : ' units')];
        if (amount !== '' && !isNaN(parseFloat(amount))) parts.push('Order total $' + money(parseFloat(amount)));
        const el = document.getElementById('of-order-footer-summary');
        if (el) el.innerHTML = parts.map(function (p, i) { return i === parts.length - 1 && parts.length > 1 ? '<strong>' + escapeHtml(p) + '</strong>' : escapeHtml(p); }).join(' &middot; ');
    }

    function recalcOrderAmount() {
        let sum = 0;
        let priced = false;
        let count = 0;
        document.querySelectorAll('#of-order-lines .of-order-line').forEach(function (line) {
            const total = lineTotal(line);
            const cell = line.querySelector('.of-line-total');
            if (cell) cell.textContent = total === null ? '—' : '$' + money(total);
            if (total !== null) { sum += total; priced = true; }
            if (String(line.querySelector('.of-line-sku')?.value || '').trim() !== '') count += 1;
        });
        setText('of-order-lines-count', count + (count === 1 ? ' item' : ' items'));
        setText('of-order-lines-total', priced ? '$' + money(sum) : '—');
        const amount = document.getElementById('of-order-amount');
        if (amount && !amountTouched) {
            amount.value = priced ? money(sum) : '';
            amount.placeholder = '0.00';
            setText('of-order-amount-hint', 'auto: items × qty');
        }
        updateFooterSummary();
    }

    document.getElementById('of-order-amount')?.addEventListener('input', function () {
        amountTouched = true;
        setText('of-order-amount-hint', 'entered manually');
        updateFooterSummary();
    });

    function addOrderLine(sku, qty, removable, price, lineId) {
        const host = document.getElementById('of-order-lines');
        if (!host) return;
        const line = document.createElement('div');
        line.className = 'of-order-line';
        line.dataset.lineId = lineId ? String(lineId) : '';
        line.innerHTML =
            '<div class="of-line-sku-wrap">' +
                '<input type="text" class="form-control form-control-sm of-line-sku" placeholder="Search CP Master SKU…" maxlength="191" required autocomplete="off" spellcheck="false">' +
                '<div class="of-sku-suggest" role="listbox"></div>' +
            '</div>' +
            '<input type="number" class="form-control form-control-sm of-line-qty" placeholder="Qty" min="1" value="1">' +
            '<input type="number" class="form-control form-control-sm of-line-price" placeholder="0.00" min="0" step="0.01" title="Item price (fetched from CP Master, editable)">' +
            '<div class="of-line-total">—</div>' +
            '<button type="button" class="btn btn-sm btn-link text-danger of-line-remove" title="Remove"><i class="fas fa-times" aria-hidden="true"></i></button>';
        line.querySelector('.of-line-sku').value = sku || '';
        line.querySelector('.of-line-qty').value = qty || 1;
        line.querySelector('.of-line-price').value = (price === null || price === undefined || price === '') ? '' : money(price);
        const priceInput = line.querySelector('.of-line-price');
        const qtyInput = line.querySelector('.of-line-qty');
        attachSkuSuggest(line.querySelector('.of-line-sku'), line.querySelector('.of-sku-suggest'), qtyInput, priceInput);
        function onLineChange() {
            // Editing an existing order: once a line changes, the total follows the lines again.
            if (editingOrder) { amountTouched = false; editingOrder = false; }
            recalcOrderAmount();
        }
        qtyInput.addEventListener('input', onLineChange);
        priceInput.addEventListener('input', onLineChange);
        line.querySelector('.of-line-sku').addEventListener('input', recalcOrderAmount);
        line.querySelector('.of-line-sku').addEventListener('change', recalcOrderAmount);
        const remove = line.querySelector('.of-line-remove');
        if (removable === false) remove.style.visibility = 'hidden';
        remove.addEventListener('click', function () {
            if (host.querySelectorAll('.of-order-line').length > 1) { line.remove(); onLineChange(); }
        });
        host.appendChild(line);
        recalcOrderAmount();
    }

    const skuSuggestUrl = @json(route('order.fulfillment.sku.suggest'));
    const skuSuggestCache = {};

    // Live SKU search against CP Master: type → matching SKUs (prefix first) with inventory and price.
    function attachSkuSuggest(input, box, qtyInput, priceInput) {
        if (!input || !box) return;
        let items = [];
        let active = -1;
        let timer = null;
        let requestSeq = 0;

        function close() {
            box.classList.remove('show');
            box.innerHTML = '';
            active = -1;
        }

        function choose(index) {
            const item = items[index];
            if (!item) return;
            input.value = item.sku;
            if (priceInput) {
                priceInput.value = (item.price === null || item.price === undefined) ? '' : money(item.price);
                recalcOrderAmount();
            }
            close();
            if (qtyInput) { qtyInput.focus(); qtyInput.select(); }
        }

        // Typed exactly (no click on a suggestion): still pull the price when the SKU matches.
        function fillPriceFromExactMatch() {
            if (!priceInput || String(priceInput.value || '').trim() !== '') return;
            const typed = String(input.value || '').trim().toLowerCase();
            if (!typed) return;
            const match = items.find(function (it) { return String(it.sku || '').toLowerCase() === typed; });
            if (match && match.price !== null && match.price !== undefined) {
                priceInput.value = money(match.price);
                recalcOrderAmount();
            }
        }

        function render() {
            if (!items.length) {
                box.innerHTML = '<div class="of-sku-empty">No CP Master SKU matches. You can still keep what you typed.</div>';
                box.classList.add('show');
                return;
            }
            box.innerHTML = items.map(function (item, i) {
                const inv = (item.inv === null || item.inv === undefined) ? '' : ('inv ' + Number(item.inv).toLocaleString());
                const price = (item.price === null || item.price === undefined) ? '' : ('$' + money(item.price));
                const parent = item.parent ? escapeHtml(item.parent) : '';
                return '<div class="of-sku-item' + (i === active ? ' active' : '') + '" data-index="' + i + '" role="option">' +
                    '<code>' + escapeHtml(item.sku) + '</code>' +
                    '<small>' + [parent, inv, price].filter(Boolean).join(' · ') + '</small>' +
                    '</div>';
            }).join('');
            box.classList.add('show');
        }

        function search(term) {
            const key = term.toLowerCase();
            if (skuSuggestCache[key]) {
                items = skuSuggestCache[key];
                active = items.length ? 0 : -1;
                render();
                return;
            }
            const seq = ++requestSeq;
            $.getJSON(skuSuggestUrl, { q: term }).done(function (res) {
                if (seq !== requestSeq) return;
                items = (res && Array.isArray(res.items)) ? res.items : [];
                skuSuggestCache[key] = items;
                active = items.length ? 0 : -1;
                if (document.activeElement === input) render();
                else fillPriceFromExactMatch();
            }).fail(function () {
                if (seq === requestSeq) close();
            });
        }

        input.addEventListener('input', function () {
            const term = String(input.value || '').trim();
            clearTimeout(timer);
            if (term.length < 1) { close(); return; }
            timer = setTimeout(function () { search(term); }, 180);
        });
        input.addEventListener('focus', function () {
            const term = String(input.value || '').trim();
            if (term) search(term);
        });
        input.addEventListener('keydown', function (ev) {
            if (!box.classList.contains('show')) return;
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                if (items.length) { active = (active + 1) % items.length; render(); }
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (items.length) { active = (active - 1 + items.length) % items.length; render(); }
            } else if (ev.key === 'Enter') {
                if (active >= 0 && items.length) { ev.preventDefault(); choose(active); }
            } else if (ev.key === 'Tab') {
                if (active >= 0 && items.length) choose(active);
            } else if (ev.key === 'Escape') {
                close();
            }
        });
        box.addEventListener('mousedown', function (ev) {
            const el = ev.target.closest('.of-sku-item');
            if (!el) return;
            ev.preventDefault();
            choose(parseInt(el.getAttribute('data-index'), 10));
        });
        input.addEventListener('blur', function () {
            fillPriceFromExactMatch();
            setTimeout(close, 150);
        });
    }

    function readOrderLines() {
        const lines = [];
        document.querySelectorAll('#of-order-lines .of-order-line').forEach(function (line) {
            const sku = String(line.querySelector('.of-line-sku')?.value || '').trim();
            const qty = parseInt(line.querySelector('.of-line-qty')?.value || '1', 10) || 1;
            const priceRaw = String(line.querySelector('.of-line-price')?.value || '').trim();
            const entry = { sku: sku, qty: qty, price: priceRaw };
            if (line.dataset.lineId) entry.id = line.dataset.lineId;
            if (sku) lines.push(entry);
        });
        return lines;
    }

    function pad2(n) { return String(n).padStart(2, '0'); }

    const orderTimezone = @json($ofTimezone ?? 'America/Los_Angeles');

    // Current wall-clock time in the grid's timezone (Pacific), not the browser's.
    function nowLocalValue() {
        try {
            const parts = new Intl.DateTimeFormat('en-US', {
                timeZone: orderTimezone, hourCycle: 'h23',
                year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit',
            }).formatToParts(new Date()).reduce(function (acc, p) { acc[p.type] = p.value; return acc; }, {});
            return parts.year + '-' + parts.month + '-' + parts.day + 'T' + parts.hour + ':' + parts.minute;
        } catch (e) {
            const d = new Date();
            return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()) + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
        }
    }

    function setOrderField(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value == null ? '' : value;
    }

    const marketplaceSelect = document.getElementById('of-order-marketplace-select');
    const marketplaceOther = document.getElementById('of-order-marketplace');

    function syncMarketplaceOther() {
        if (!marketplaceSelect || !marketplaceOther) return;
        const other = marketplaceSelect.value === '__other__';
        marketplaceOther.style.display = other ? '' : 'none';
        marketplaceOther.required = other;
        if (!other) marketplaceOther.value = '';
    }

    function findMarketplaceOption(name) {
        const key = String(name || '').trim().toLowerCase();
        if (!key || !marketplaceSelect) return null;
        return Array.from(marketplaceSelect.options).find(function (o) {
            return o.value !== '__other__' && o.value.trim().toLowerCase() === key;
        }) || null;
    }

    function addMarketplaceOption(name) {
        name = String(name || '').trim();
        if (!name || !marketplaceSelect) return null;
        const existing = findMarketplaceOption(name);
        if (existing) return existing;
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        const otherOpt = marketplaceSelect.querySelector('option[value="__other__"]');
        const before = Array.from(marketplaceSelect.options).find(function (o) {
            return o.value && o.value !== '__other__' && o.value.localeCompare(name, undefined, { sensitivity: 'base' }) > 0;
        }) || otherOpt;
        marketplaceSelect.insertBefore(opt, before || null);
        return opt;
    }

    function setMarketplaceField(name) {
        if (!marketplaceSelect) return;
        name = String(name || '').trim();
        if (!name) {
            marketplaceSelect.value = '';
        } else {
            marketplaceSelect.value = (findMarketplaceOption(name) || addMarketplaceOption(name)).value;
        }
        syncMarketplaceOther();
    }

    function marketplaceValue() {
        if (!marketplaceSelect) return '';
        if (marketplaceSelect.value === '__other__') return String(marketplaceOther?.value || '').trim();
        return marketplaceSelect.value.trim();
    }

    marketplaceSelect?.addEventListener('change', function () {
        syncMarketplaceOther();
        if (marketplaceSelect.value === '__other__') setTimeout(function () { marketplaceOther?.focus(); }, 50);
    });

    function openOrderModalForCreate() {
        document.getElementById('of-order-modal-label').textContent = 'Create order';
        document.getElementById('of-order-save').textContent = 'Create order';
        setOrderField('of-order-manual-id', '');
        setMarketplaceField('');
        ['of-order-order-id', 'of-order-amount', 'of-order-customer',
         'of-order-email', 'of-order-phone', 'of-order-address1', 'of-order-address2', 'of-order-city',
         'of-order-state', 'of-order-zip', 'of-order-notes'].forEach(function (id) { setOrderField(id, ''); });
        setOrderField('of-order-country', 'US');
        setOrderField('of-order-paid', '1');
        setOrderField('of-order-date', nowLocalValue());
        amountTouched = false;
        editingOrder = false;
        const host = document.getElementById('of-order-lines');
        if (host) host.innerHTML = '';
        addOrderLine('', 1, true, null, null);
        document.getElementById('of-order-add-line').style.display = '';
        document.getElementById('of-order-lines-help').textContent = 'One grid row is created per SKU.';
        showFormError('of-order-error', '');
        showModal('of-order-modal');
        setTimeout(function () { marketplaceSelect?.focus(); }, 200);
    }

    function openOrderModalForEdit(row) {
        document.getElementById('of-order-modal-label').textContent = 'Edit order ' + (row.order_id || '');
        document.getElementById('of-order-save').textContent = 'Save changes';
        setOrderField('of-order-manual-id', row.manual_id || '');
        setMarketplaceField(row.channel || '');
        setOrderField('of-order-order-id', row.order_id || '');
        setOrderField('of-order-date', String(row.order_date || '').replace(' ', 'T').slice(0, 16));
        setOrderField('of-order-paid', row.paid ? '1' : '0');
        setOrderField('of-order-amount', row.amount == null ? '' : row.amount);
        setOrderField('of-order-customer', row.customer_name || '');
        setOrderField('of-order-email', row.customer_email || '');
        setOrderField('of-order-phone', row.customer_phone || '');
        setOrderField('of-order-address1', row.address1 || '');
        setOrderField('of-order-address2', row.address2 || '');
        setOrderField('of-order-city', row.city || '');
        setOrderField('of-order-state', row.state || '');
        setOrderField('of-order-zip', row.zip || '');
        setOrderField('of-order-country', row.country || '');
        setOrderField('of-order-notes', row.notes || '');
        // Keep the stored order total until a line changes; then it follows the lines again.
        amountTouched = true;
        editingOrder = true;
        const host = document.getElementById('of-order-lines');
        if (host) host.innerHTML = '';
        const lines = (Array.isArray(row.lines) && row.lines.length)
            ? row.lines
            : [{ id: row.manual_id, sku: row.sku || '', qty: row.qty || 1, unit_price: row.unit_price }];
        lines.forEach(function (line) {
            addOrderLine(line.sku || '', line.qty || 1, true, line.unit_price, line.id);
        });
        setText('of-order-amount-hint', 'stored order total');
        document.getElementById('of-order-add-line').style.display = '';
        document.getElementById('of-order-lines-help').textContent = 'Add, change or remove SKUs — the whole order is saved together.';
        showFormError('of-order-error', '');
        showModal('of-order-modal');
    }

    /* ---- Read-only view of the complete order ---- */
    let viewedRow = null;

    function viewItem(label, value, options) {
        options = options || {};
        const text = (value === null || value === undefined || String(value).trim() === '') ? (options.empty || '—') : String(value);
        const muted = text === (options.empty || '—');
        return '<div class="of-view-item' + (options.wide ? ' of-view-wide' : '') + '"><div class="of-view-label">' + escapeHtml(label) + '</div>'
            + '<div class="of-view-value' + (muted ? ' of-view-muted' : '') + '">' + (options.html ? text : escapeHtml(text)) + '</div></div>';
    }

    function openOrderViewModal(row) {
        if (!row) return;
        viewedRow = row;
        const lines = (Array.isArray(row.lines) && row.lines.length)
            ? row.lines
            : [{ sku: row.sku || '', qty: row.qty || 1, unit_price: row.unit_price, line_total: null, inv: row.inv }];
        const dt = formatDateTime(row.order_date);
        const status = String(row.status || '').trim();
        const fulfilled = status.toLowerCase() === 'fulfilled';

        document.getElementById('of-view-modal-label').textContent = (row.channel || 'Order') + ' · ' + (row.order_id || '');
        document.getElementById('of-view-status-badge').innerHTML =
            '<span class="badge rounded-pill ' + (fulfilled ? 'bg-success' : 'bg-warning text-dark') + '">' + escapeHtml(status || 'Order Created') + '</span>';

        document.getElementById('of-view-order').innerHTML =
            viewItem('Marketplace', row.channel) +
            viewItem('Order ID', row.order_id) +
            viewItem('Order date (' + orderTimezone + ')', dt ? (dt.date + (dt.time ? ' ' + dt.time : '')) : '') +
            viewItem('Payment', row.paid_label || (row.paid ? 'Paid' : 'Unpaid')) +
            viewItem('Order amount', row.amount == null ? '' : '$' + money(row.amount)) +
            viewItem('Created', row.created_at ? String(row.created_at).slice(0, 16) : '');

        let units = 0;
        let total = 0;
        let priced = false;
        document.getElementById('of-view-lines').innerHTML = lines.map(function (line) {
            const qty = Number(line.qty || 1);
            const price = (line.unit_price === null || line.unit_price === undefined) ? null : Number(line.unit_price);
            const lineTotal = price === null ? null : Math.round(price * qty * 100) / 100;
            units += qty;
            if (lineTotal !== null) { total += lineTotal; priced = true; }
            return '<tr><td><code class="of-sku">' + escapeHtml(line.sku || '') + '</code></td>'
                + '<td class="text-center">' + qty + '</td>'
                + '<td class="text-end">' + (price === null ? '—' : '$' + money(price)) + '</td>'
                + '<td class="text-end">' + (lineTotal === null ? '—' : '$' + money(lineTotal)) + '</td>'
                + '<td class="text-center">' + ((line.inv === null || line.inv === undefined || line.inv === '') ? '—' : Number(line.inv).toLocaleString()) + '</td></tr>';
        }).join('');
        document.getElementById('of-view-lines-foot').innerHTML =
            '<tr><td colspan="3" class="text-end">Items total</td><td class="text-end">' + (priced ? '$' + money(total) : '—') + '</td><td></td></tr>';
        setText('of-view-items-sub', lines.length + (lines.length === 1 ? ' SKU' : ' SKUs') + ' · ' + units + (units === 1 ? ' unit' : ' units'));

        const source = String(row.tracking_source || '');
        const sourceLabel = source === '4seller' ? '4Seller' : (source === 'gofo' ? 'GOFO' : (source === 'veeqo' ? 'Veeqo' : (source === 'channel' ? 'Marketplace' : (source === 'manual' ? 'Entered manually' : ''))));
        document.getElementById('of-view-shipping').innerHTML =
            viewItem('Tracking number', row.tracking, { empty: row.tracking_checked ? 'Not found yet' : 'Looking up…' }) +
            viewItem('Carrier', row.carrier) +
            viewItem('Tracking status', row.tracking_status) +
            viewItem('Tracking source', sourceLabel) +
            viewItem('Fulfilled at', row.fulfilled_at ? String(row.fulfilled_at).slice(0, 16) : '', { empty: fulfilled ? '—' : 'Not fulfilled yet' }) +
            viewItem('Status', status);

        const address = [row.address1, row.address2, [row.city, row.state, row.zip].filter(Boolean).join(', '), row.country]
            .map(function (p) { return String(p || '').trim(); }).filter(Boolean).join('<br>');
        document.getElementById('of-view-customer').innerHTML =
            viewItem('Customer name', row.customer_name) +
            viewItem('Email', row.customer_email) +
            viewItem('Phone', row.customer_phone) +
            viewItem('Shipping address', address || '', { html: true }) +
            viewItem('Notes', row.notes, { wide: true });

        document.getElementById('of-view-footer').innerHTML =
            escapeHtml(lines.length + (lines.length === 1 ? ' SKU' : ' SKUs') + ', ' + units + (units === 1 ? ' unit' : ' units'))
            + (row.amount == null ? '' : ' &middot; <strong>Order total $' + money(row.amount) + '</strong>');
        showModal('of-view-modal');
    }

    document.getElementById('of-view-edit')?.addEventListener('click', function () {
        if (!viewedRow) return;
        const live = table.getRow(viewedRow.id);
        hideModal('of-view-modal');
        setTimeout(function () { openOrderModalForEdit(live ? live.getData() : viewedRow); }, 250);
    });

    function orderHeaderPayload() {
        return {
            marketplace: marketplaceValue(),
            order_id: document.getElementById('of-order-order-id')?.value || '',
            order_date: String(document.getElementById('of-order-date')?.value || '').replace('T', ' '),
            paid: document.getElementById('of-order-paid')?.value === '1' ? 1 : 0,
            amount: document.getElementById('of-order-amount')?.value || '',
            customer_name: document.getElementById('of-order-customer')?.value || '',
            customer_email: document.getElementById('of-order-email')?.value || '',
            customer_phone: document.getElementById('of-order-phone')?.value || '',
            address1: document.getElementById('of-order-address1')?.value || '',
            address2: document.getElementById('of-order-address2')?.value || '',
            city: document.getElementById('of-order-city')?.value || '',
            state: document.getElementById('of-order-state')?.value || '',
            zip: document.getElementById('of-order-zip')?.value || '',
            country: document.getElementById('of-order-country')?.value || '',
            notes: document.getElementById('of-order-notes')?.value || '',
        };
    }

    document.getElementById('of-create-order-btn')?.addEventListener('click', openOrderModalForCreate);
    document.getElementById('of-order-add-line')?.addEventListener('click', function () { addOrderLine('', 1, true); });

    document.getElementById('of-order-form')?.addEventListener('submit', function (ev) {
        ev.preventDefault();
        const saveBtn = document.getElementById('of-order-save');
        const manualId = document.getElementById('of-order-manual-id')?.value || '';
        const payload = orderHeaderPayload();
        const lines = readOrderLines();
        if (!payload.marketplace.trim() || !payload.order_id.trim() || !payload.order_date.trim()) {
            showFormError('of-order-error', 'Marketplace, order ID and order date are required.');
            return;
        }
        if (!lines.length) {
            showFormError('of-order-error', 'Add at least one SKU.');
            return;
        }
        showFormError('of-order-error', '');
        saveBtn.disabled = true;
        const isEdit = manualId !== '';
        payload.lines = lines;
        if (isEdit) payload._method = 'PUT';
        $.ajax({
            url: isEdit ? manualUrl(manualId) : manualStoreUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: payload,
        }).done(function (res) {
            if (!res || res.success === false) {
                showFormError('of-order-error', (res && res.message) || 'Could not save the order.');
                return;
            }
            const rows = Array.isArray(res.rows) ? res.rows : [];
            if (isEdit) {
                // The row id follows the first SKU line; drop the old row if that line was removed.
                (Array.isArray(res.removed_ids) ? res.removed_ids : []).forEach(function (id) {
                    const old = table.getRow(id);
                    if (old) old.delete();
                });
                table.updateOrAddData(rows);
            } else {
                table.addData(rows);
            }
            addMarketplaceOption(payload.marketplace);
            refreshAfterRowsChanged();
            hideModal('of-order-modal');
        }).fail(function (xhr) {
            showFormError('of-order-error', ajaxErrorMessage(xhr, 'Could not save the order.'));
        }).always(function () {
            saveBtn.disabled = false;
        });
    });

    function deleteManualOrder(row) {
        if (!row || !row.manual_id) return;
        const skus = (Array.isArray(row.lines) && row.lines.length) ? row.lines.map(function (l) { return l.sku; }).join(', ') : (row.sku || '');
        if (!window.confirm('Delete order ' + (row.order_id || '') + ' (' + skus + ')? All its SKU lines will be removed.')) return;
        $.ajax({
            url: manualUrl(row.manual_id),
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { _method: 'DELETE' },
        }).done(function (res) {
            if (res && res.success) {
                [row.id].concat(Array.isArray(res.ids) ? res.ids : []).forEach(function (id) {
                    const live = table.getRow(id);
                    if (live) live.delete();
                });
                refreshAfterRowsChanged();
            } else {
                window.alert((res && res.message) || 'Could not delete the order.');
            }
        }).fail(function (xhr) {
            window.alert(ajaxErrorMessage(xhr, 'Could not delete the order.'));
        });
    }

    let fulfillInFlight = false;

    function postFulfill(manualId, trackingNumber, onNoTracking, onDone) {
        if (fulfillInFlight) return;
        fulfillInFlight = true;
        $.ajax({
            url: manualUrl(manualId, '/fulfill'),
            type: 'POST',
            dataType: 'json',
            timeout: 60000,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { tracking_number: trackingNumber || '' },
        }).done(function (res) {
            const rows = (res && Array.isArray(res.rows)) ? res.rows : [];
            table.updateOrAddData(rows);
            (res && Array.isArray(res.updates) ? res.updates : []).forEach(applyTrackingUpdate);
            refreshAfterRowsChanged();
            if (onDone) onDone(res);
        }).fail(function (xhr) {
            const body = xhr && xhr.responseJSON;
            if (body && body.no_tracking) {
                onNoTracking(body.message || 'No tracking number found yet.');
                return;
            }
            const message = ajaxErrorMessage(xhr, 'Could not fulfill the order.');
            if (onDone) onDone(null, message); else window.alert(message);
        }).always(function () {
            fulfillInFlight = false;
        });
    }

    function fulfillManualOrder(row) {
        if (!row || !row.manual_id) return;
        postFulfill(row.manual_id, '', function (message) {
            setOrderField('of-fulfill-manual-id', row.manual_id);
            setOrderField('of-fulfill-tracking', row.tracking || '');
            const skus = (Array.isArray(row.lines) && row.lines.length) ? row.lines.map(function (l) { return l.sku; }).join(', ') : (row.sku || '');
            document.getElementById('of-fulfill-order').textContent = (row.channel || '') + ' · ' + (row.order_id || '') + ' · ' + skus;
            document.getElementById('of-fulfill-message').textContent = message;
            showFormError('of-fulfill-error', '');
            showModal('of-fulfill-modal');
            setTimeout(function () { document.getElementById('of-fulfill-tracking')?.focus(); }, 200);
        }, function (res, error) {
            if (error) window.alert(error);
        });
    }

    document.getElementById('of-fulfill-retry')?.addEventListener('click', function () {
        const manualId = document.getElementById('of-fulfill-manual-id')?.value || '';
        if (!manualId) return;
        const btn = this;
        btn.disabled = true;
        showFormError('of-fulfill-error', '');
        postFulfill(manualId, '', function (message) {
            btn.disabled = false;
            document.getElementById('of-fulfill-message').textContent = message;
        }, function (res, error) {
            btn.disabled = false;
            if (error) { showFormError('of-fulfill-error', error); return; }
            hideModal('of-fulfill-modal');
        });
    });

    document.getElementById('of-fulfill-form')?.addEventListener('submit', function (ev) {
        ev.preventDefault();
        const manualId = document.getElementById('of-fulfill-manual-id')?.value || '';
        const number = String(document.getElementById('of-fulfill-tracking')?.value || '').trim();
        if (!manualId) return;
        if (!number) {
            showFormError('of-fulfill-error', 'Enter the tracking number, or choose "Search again".');
            return;
        }
        const saveBtn = document.getElementById('of-fulfill-save');
        saveBtn.disabled = true;
        postFulfill(manualId, number, function (message) {
            saveBtn.disabled = false;
            showFormError('of-fulfill-error', message);
        }, function (res, error) {
            saveBtn.disabled = false;
            if (error) { showFormError('of-fulfill-error', error); return; }
            hideModal('of-fulfill-modal');
        });
    });
})();
</script>
@if(!empty($ofPendingOnly))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const badge = document.getElementById('of-ph-badge');
    if (!badge) return;
    const dailyBadge = document.getElementById('of-ph-badge-daily');

    const HISTORY_URL = @json(route('order.fulfillment.pending.history'));
    const HOUR_MS = 3600000;
    const TOTAL = '__total';
    const TOTAL_COLOR = '#f472b6';
    const PALETTE = ['#a78bfa', '#fb7185', '#fb923c', '#22d3ee', '#a3e635', '#facc15', '#38bdf8', '#34d399', '#f472b6', '#c084fc'];

    let data = null;
    let range = '7d';
    let tab = 'hourly';
    let selected = TOTAL;
    let chart = null;

    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const num = (n) => Number(n || 0).toLocaleString();
    const el = (id) => document.getElementById(id);

    function hexA(hex, a) {
        const h = hex.replace('#', '');
        const n = parseInt(h, 16);
        return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
    }

    function deltaChip(d) {
        if (d === null || d === undefined || d === 0) return '';
        return '<span class="of-ph-d ' + (d > 0 ? 'up' : 'down') + '">' + (d > 0 ? '▲ +' : '▼ ') + d + '</span>';
    }

    function latestSites() { return (data && data.latest && data.latest.sites) || []; }

    function siteColor(slug) {
        const i = latestSites().findIndex(function (s) { return s.slug === slug; });
        return PALETTE[(i < 0 ? 0 : i) % PALETTE.length];
    }
    function siteLabel(slug) {
        const s = latestSites().filter(function (x) { return x.slug === slug; })[0];
        return s ? s.label : ((data && data.labels && data.labels[slug]) || slug);
    }
    const selColor = () => selected === TOTAL ? TOTAL_COLOR : siteColor(selected);
    const selLabel = () => selected === TOTAL ? 'All sites' : siteLabel(selected);
    const rowValue = (r) => selected === TOTAL ? Number(r.total || 0) : Number((r.counts || {})[selected] || 0);

    // The 3:30 PM records, oldest first, limited by the 7d / 30d buttons.
    function dailyRows() {
        const all = (data && data.daily) || [];
        return all.slice(-(range === '30d' ? 30 : 7));
    }
    function currentRows() { return tab === 'hourly' ? ((data && data.hourly) || []) : dailyRows(); }

    function paintBadges() {
        const l = data && data.latest;
        el('of-ph-total').textContent = l ? num(l.total) : '—';
        const d = l && l.previous_total !== null && l.previous_total !== undefined ? l.total - l.previous_total : null;
        const chip = el('of-ph-delta');
        chip.textContent = d ? (d > 0 ? '▲ +' + d : '▼ ' + d) : '';
        chip.title = d ? 'Change since the previous hourly snapshot' : '';
        if (dailyBadge) {
            const all = (data && data.daily) || [];
            const last = all[all.length - 1];
            el('of-ph-daily-total').textContent = last ? num(last.total) : '—';
            dailyBadge.title = last ? 'Pending orders at 3:30 PM Ohio time on ' + last.label + '. Click for the day-by-day graph.' : 'The first 3:30 PM Ohio record is saved at 3:30 PM. Click to open.';
        }
    }

    function paintHeader() {
        const l = data && data.latest;
        el('of-ph-updated').textContent = l
            ? 'Updated ' + l.label + ' (Ohio time) · refreshes every hour · daily record saved at ' + (data.daily_time || '3:30 PM') + ' Ohio time'
            : (data && data.message) || 'No data yet';
        el('of-ph-s-now').textContent = l ? num(l.total) : '—';
        const d = l && l.previous_total !== null && l.previous_total !== undefined ? l.total - l.previous_total : null;
        el('of-ph-s-delta').textContent = d === null ? '—' : (d > 0 ? '+' + num(d) : num(d));
        const all = (data && data.daily) || [];
        const last = all[all.length - 1];
        el('of-ph-s-daily').textContent = last ? num(last.total) : '—';
        el('of-ph-s-daily-label').textContent = last ? '3:30 PM record · ' + last.label : '3:30 PM record (Ohio)';
        el('of-ph-s-sites').textContent = l ? num(l.sites.length) : '—';
    }

    function paintSites() {
        const sites = latestSites();
        const l = data.latest;
        const max = Math.max.apply(null, [1].concat(sites.map(function (s) { return s.orders; })));
        const rows = ['<button type="button" class="of-ph-site' + (selected === TOTAL ? ' is-sel' : '') + '" data-key="' + TOTAL + '">' +
            '<span class="of-ph-site-name"><span class="of-ph-dot" style="background:' + TOTAL_COLOR + ';color:' + TOTAL_COLOR + '"></span>All sites (total)</span>' +
            '<span class="of-ph-bar"><i style="width:100%;background:' + TOTAL_COLOR + '"></i></span>' +
            '<span class="of-ph-site-num">' + num(l.total) + '</span><span class="of-ph-site-d"></span></button>'];
        sites.forEach(function (s) {
            const c = siteColor(s.slug);
            rows.push('<button type="button" class="of-ph-site' + (selected === s.slug ? ' is-sel' : '') + '" data-key="' + esc(s.slug) + '">' +
                '<span class="of-ph-site-name"><span class="of-ph-dot" style="background:' + c + ';color:' + c + '"></span>' + esc(s.label) + '</span>' +
                '<span class="of-ph-bar"><i style="width:' + Math.max(3, Math.round(s.orders / max * 100)) + '%;background:' + c + '"></i></span>' +
                '<span class="of-ph-site-num">' + num(s.orders) + '</span><span class="of-ph-site-d">' + deltaChip(s.delta) + '</span></button>');
        });
        el('of-ph-sites').innerHTML = rows.join('');
        el('of-ph-picker-title').textContent = tab === 'hourly'
            ? 'Pending right now by site: click one to graph it'
            : 'Click a site to see its 3:30 PM number for each day';
    }

    function paintDailyTable() {
        const box = el('of-ph-daily-table');
        const all = ((data && data.daily) || []).slice().reverse();
        if (tab !== 'daily' || !all.length) { box.innerHTML = ''; return; }
        const body = all.map(function (r, i) {
            const older = all[i + 1];
            const d = older ? r.total - older.total : null;
            const top = Object.keys(r.counts || {}).map(function (k) { return { k: k, n: r.counts[k] }; })
                .sort(function (a, b) { return b.n - a.n; }).slice(0, 3)
                .map(function (x) { return esc((data.labels && data.labels[x.k]) || x.k) + ' ' + num(x.n); }).join(' · ');
            return '<tr><td>' + esc(r.label) + '</td><td>' + esc(r.recorded_at) + '</td><td><b>' + num(r.total) + '</b> ' + deltaChip(d) + '</td><td>' + (top || '—') + '</td></tr>';
        }).join('');
        box.innerHTML = '<table class="of-ph-table"><thead><tr><th>Day</th><th>Recorded (Ohio)</th><th>Pending at 3:30 PM</th><th>Top sites</th></tr></thead><tbody>' + body + '</tbody></table>';
    }

    // Draws the number above (some of) the points so the graph can be read without hovering.
    const valueLabels = {
        id: 'ofPhValueLabels',
        afterDatasetsDraw: function (c) {
            const meta = c.getDatasetMeta(0);
            const vals = c.data.datasets[0].data;
            const n = vals.length;
            const step = Math.max(1, Math.ceil(n / 12));
            const ctx = c.ctx;
            ctx.save();
            ctx.font = '800 13px system-ui, sans-serif';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            meta.data.forEach(function (p, i) {
                if (i % step !== 0 && i !== n - 1) return;
                ctx.fillText(num(vals[i]), p.x, p.y - 12);
            });
            ctx.restore();
        },
    };

    function renderChart() {
        if (chart) { chart.destroy(); chart = null; }
        const rows = currentRows();
        if (!window.Chart || !rows.length) return;

        const canvas = el('of-ph-canvas');
        const color = selColor();
        const ctx = canvas.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, canvas.clientHeight || 300);
        grad.addColorStop(0, hexA(color, 0.5));
        grad.addColorStop(1, hexA(color, 0.02));
        const isDaily = tab === 'daily';
        const tick = 'rgba(233,234,255,0.85)';

        chart = new Chart(canvas, {
            type: 'line',
            data: {
                labels: rows.map(function (r) { return r.label; }),
                datasets: [{
                    label: selLabel() + ': pending orders',
                    data: rows.map(rowValue),
                    borderColor: color,
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.3,
                    borderWidth: 4,
                    pointRadius: rows.length > 48 ? 0 : 6,
                    pointHoverRadius: 9,
                    pointBackgroundColor: '#0f1020',
                    pointBorderColor: color,
                    pointBorderWidth: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 22, right: 18 } },
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.06)' }, ticks: { color: tick, maxRotation: 0, autoSkip: true, maxTicksLimit: isDaily ? 12 : 8, font: { size: 11, weight: '600' } } },
                    y: {
                        beginAtZero: true,
                        grace: '18%',
                        grid: { color: 'rgba(255,255,255,0.14)' },
                        ticks: { color: tick, precision: 0, font: { size: 12, weight: '700' } },
                        title: { display: true, text: 'Pending orders', color: tick, font: { size: 12, weight: '700' } },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,16,32,0.97)',
                        borderColor: 'rgba(255,255,255,0.2)',
                        borderWidth: 1,
                        padding: 12,
                        titleFont: { size: 13, weight: '800' },
                        bodyFont: { size: 13, weight: '700' },
                        footerFont: { size: 12, weight: '600' },
                        callbacks: {
                            title: function (items) {
                                const r = rows[items[0].dataIndex];
                                return isDaily ? r.label + ' · saved ' + r.recorded_at + ' (Ohio)' : r.label + ' (Ohio)';
                            },
                            label: function (item) { return ' ' + selLabel() + ': ' + num(item.parsed.y) + ' pending'; },
                            footer: function (items) {
                                if (selected !== TOTAL) return '';
                                const r = rows[items[0].dataIndex];
                                return Object.keys(r.counts || {}).map(function (k) { return { k: k, n: r.counts[k] }; })
                                    .sort(function (a, b) { return b.n - a.n; }).slice(0, 5)
                                    .map(function (x) { return siteLabel(x.k) + ': ' + num(x.n); });
                            },
                        },
                    },
                },
            },
            plugins: [valueLabels],
        });
    }

    function paint() {
        paintBadges();
        paintHeader();
        const has = !!(data && data.latest);
        el('of-ph-content').style.display = has ? '' : 'none';
        const empty = el('of-ph-empty');
        empty.style.display = has ? 'none' : '';
        if (!has) {
            empty.innerHTML = '<big>📊</big><div><b>' + esc((data && data.message) || 'No snapshot yet.') + '</b></div>' +
                '<div class="mt-2 small">Or run <code>php artisan order-fulfillment:snapshot-pending</code> once.</div>';
            return;
        }

        const rows = currentRows();
        const noRows = rows.length === 0;
        el('of-ph-chartbox').style.display = noRows ? 'none' : '';
        const tabEmpty = el('of-ph-tab-empty');
        tabEmpty.style.display = noRows ? '' : 'none';
        if (noRows) {
            tabEmpty.innerHTML = '<big>🕞</big><div><b>No 3:30 PM record yet.</b></div>' +
                '<div class="mt-1 small">The number is saved every day at exactly 3:30 PM Ohio time. The first point appears right after the next 3:30 PM, and the graph only changes then.</div>';
        }

        let hint;
        if (tab === 'hourly') {
            hint = rows.length === 1
                ? 'Only one hourly snapshot so far: the line grows by one point every hour.'
                : 'One point per hourly refresh (Ohio time). Hover a point for the site breakdown.';
        } else {
            hint = 'One point per day: the pending orders at 3:30 PM Ohio time. It only changes after the next 3:30 PM.';
        }
        el('of-ph-hint').textContent = hint;
        el('of-ph-hint').style.display = noRows ? 'none' : '';
        el('of-ph-picker').style.display = noRows ? 'none' : '';
        paintSites();
        paintDailyTable();
        renderChart();
        // 24h only makes sense for the hourly graph.
        document.querySelectorAll('[data-ph-range="24h"]').forEach(function (b) { b.style.display = tab === 'daily' ? 'none' : ''; });
    }

    function load() {
        return fetch(HISTORY_URL + '?range=' + encodeURIComponent(range === '24h' && tab === 'daily' ? '7d' : range), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) throw new Error((json && json.message) || 'failed');
                data = json;
                paint();
            })
            .catch(function () {
                el('of-ph-total').textContent = '—';
                el('of-ph-updated').textContent = 'Could not load the history. Try the reload button.';
            });
    }

    function setActive(attr, value) {
        document.querySelectorAll('[' + attr + ']').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute(attr) === value); });
    }

    function openModal(openTab) {
        tab = openTab;
        setActive('data-ph-tab', tab);
        const modal = el('of-ph-modal');
        if (window.bootstrap && bootstrap.Modal && modal) bootstrap.Modal.getOrCreateInstance(modal).show();
        if (data) paint();
        load();
    }

    badge.addEventListener('click', function () { openModal('hourly'); });
    dailyBadge?.addEventListener('click', function () { openModal('daily'); });
    el('of-ph-modal')?.addEventListener('click', function (e) {
        const t = e.target.closest('[data-ph-tab]');
        if (t) { tab = t.getAttribute('data-ph-tab'); setActive('data-ph-tab', tab); paint(); return; }
        const r = e.target.closest('[data-ph-range]');
        if (r) { range = r.getAttribute('data-ph-range'); setActive('data-ph-range', range); load(); return; }
        const site = e.target.closest('.of-ph-site');
        if (site) { selected = site.getAttribute('data-key') || TOTAL; paintSites(); renderChart(); }
    });
    el('of-ph-reload')?.addEventListener('click', load);
    // Re-render once the modal has finished opening so Chart.js measures the real size.
    el('of-ph-modal')?.addEventListener('shown.bs.modal', function () { renderChart(); });

    load();
    // Snapshots are saved every hour, so re-read them every hour (badges and the open graph).
    setInterval(load, HOUR_MS);
})();
</script>
@endif
@endsection
