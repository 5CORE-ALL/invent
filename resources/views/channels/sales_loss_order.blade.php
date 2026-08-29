@extends('layouts.vertical', ['title' => 'Sales Loss Order', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .slo-channel-name {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
        }
        .slo-channel-name.has-link { color: #0d6efd; }
        .slo-channel-name.has-link:hover { text-decoration: underline; }

        #sales-loss-order-table.tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col {
            background-color: #e6e6e6;
            height: 80px !important;
            overflow: visible;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: black !important;
            overflow: visible;
            text-overflow: clip;
            pointer-events: none;
        }
        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #sales-loss-order-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-order-id-wrap),
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-text-dot-wrap),
        #sales-loss-order-table .tabulator-row .tabulator-cell:has(.slo-tracking-cell) {
            overflow: visible !important;
        }
        #sales-loss-order-table .tabulator-row:has(.slo-tracking-cell:hover) {
            z-index: 8;
        }

        #slo-toolbar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #slo-toolbar-row1,
        #slo-toolbar-row2 {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 6px;
            min-width: 0;
            overflow-x: auto;
        }
        #slo-toolbar .slo-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }
        #slo-gpft-low-badge,
        #slo-groi-low-badge {
            cursor: pointer;
            user-select: none;
        }
        #slo-gpft-low-badge:hover,
        #slo-groi-low-badge:hover {
            filter: brightness(0.97);
        }
        #slo-gpft-low-badge.is-active,
        #slo-groi-low-badge.is-active {
            box-shadow: inset 0 0 0 2px #842029;
        }
        #slo-toolbar .form-control-sm,
        #slo-toolbar .form-select-sm,
        #slo-toolbar .input-group-sm > .form-control,
        #slo-toolbar .input-group-sm > .input-group-text {
            min-height: 28px;
            height: 28px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            font-size: 0.78rem;
        }
        #slo-toolbar .slo-filter-field { flex-shrink: 0; }
        #slo-toolbar #slo-order-search {
            min-width: 180px;
            flex: 1 1 180px;
        }
        #slo-date-filter-hint {
            font-size: 0.7rem;
            white-space: nowrap;
            color: #64748b;
            margin-left: auto;
            flex-shrink: 0;
        }
        .slo-oc-missing { color: #adb5bd; }
        .slo-status-badge {
            display: inline-block;
            background: #f8d7da;
            color: #842029;
            font-weight: 500;
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 50rem;
            border: 1px solid #f5c2c7;
            line-height: 1.2;
            white-space: nowrap;
        }
        .slo-order-id-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-order-id-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #0ab39c;
            box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.22);
            cursor: default;
        }
        .slo-order-id-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 320px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .slo-order-id-wrap:hover .slo-order-id-popover { display: flex; }
        .slo-order-id-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .slo-order-id-text a { color: #0d6efd; text-decoration: none; }
        .slo-order-id-text a:hover { text-decoration: underline; }
        .slo-order-id-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
        }
        .slo-order-id-copy:hover { background: #e2e8f0; color: #0f172a; }
        .slo-order-id-copy.copied { background: #d1e7dd; color: #0f5132; }
        .slo-text-dot-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-text-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #6c8cff;
            box-shadow: 0 0 0 2px rgba(108, 140, 255, 0.22);
        }
        .slo-text-dot-box {
            display: none;
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 40;
            min-width: 160px;
            max-width: 360px;
            background: #fff;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 0.8rem;
            font-weight: 500;
            line-height: 1.35;
            white-space: normal;
            text-align: left;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .slo-text-dot-wrap:hover .slo-text-dot-box { display: block; }
        .slo-sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
        }
        .slo-sku-cell code {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slo-sku-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            font-size: 11px;
        }
        .slo-tracking-cell {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .slo-ch-orders-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            cursor: default;
        }
        .slo-ch-orders-dot.red { background: #f06548; box-shadow: 0 0 0 2px rgba(240, 101, 72, 0.2); }
        .slo-ch-orders-dot.green { background: #0ab39c; box-shadow: 0 0 0 2px rgba(10, 179, 156, 0.2); }
        .slo-tracking-cell:hover .slo-ch-orders-dot.green {
            box-shadow: 0 0 0 3px rgba(10, 179, 156, 0.35);
        }
        .slo-tracking-popover {
            display: none;
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            z-index: 50;
            min-width: 180px;
            max-width: 360px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
            align-items: center;
            gap: 8px;
        }
        .slo-tracking-cell:hover .slo-tracking-popover { display: flex; }
        .slo-tracking-popover::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 0;
            bottom: 0;
            width: 12px;
        }
        .slo-tracking-num {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slo-tracking-copy {
            border: none;
            background: #f1f5f9;
            color: #475569;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            padding: 0;
            font-size: 0.75rem;
        }
        .slo-tracking-copy:hover { background: #e2e8f0; color: #0f172a; }
        .slo-tracking-copy.copied { background: #d1e7dd; color: #0f5132; }
        .slo-carrier-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            line-height: 1.2;
            padding: 0.2rem 0.45rem;
            border-radius: 0.35rem;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            color: #475569;
            white-space: nowrap;
        }
        .slo-dt-cell {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            line-height: 1.15;
            font-weight: 700;
            color: #0f172a;
        }
        .slo-dt-date {
            white-space: nowrap;
        }
        .slo-dt-time {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
        .slo-dil { font-weight: 600; }
        .slo-dil-red { color: #a00211; }
        .slo-dil-yellow { color: #ffc107; }
        .slo-dil-green { color: #28a745; }
        .slo-dil-pink { color: #e83e8c; }
        .slo-dil-zero { color: #6c757d; }

        #sales-loss-order-table.tabulator .tabulator-header .tabulator-col[tabulator-field="cost_ship"] .tabulator-col-title {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            font-size: 14px;
        }
        .slo-cs-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 8px;
            background: linear-gradient(180deg, #eef4ff 0%, #dbe7ff 100%);
            color: #3b6fd6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 1px 0 rgba(59, 111, 214, 0.15);
        }
        .slo-cs-btn:hover {
            background: linear-gradient(180deg, #dbe7ff 0%, #c7d8ff 100%);
            color: #1d4ed8;
        }
        .slo-cs-modal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.18);
        }
        .slo-cs-modal .modal-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #2563eb 140%);
            color: #fff;
            border: 0;
            padding: 1rem 1.25rem;
        }
        .slo-cs-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.01em;
        }
        .slo-cs-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
        }
        .slo-cs-modal .modal-body {
            padding: 1.15rem 1.2rem 1.25rem;
            background: #f8fafc;
        }
        .slo-cs-hero {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }
        .slo-cs-hero img,
        .slo-cs-hero .slo-cs-hero-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            border-radius: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .slo-cs-hero .slo-cs-hero-img.is-missing {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 20px;
        }
        .slo-cs-hero-meta { min-width: 0; }
        .slo-cs-hero-sku {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f766e;
            word-break: break-word;
        }
        .slo-cs-hero-title {
            font-size: 0.82rem;
            color: #64748b;
            line-height: 1.35;
            margin-top: 2px;
        }
        .slo-cs-hero-sub {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .slo-cs-chip {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
        }
        .slo-cs-section-label {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 8px 2px;
        }
        .slo-cs-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .slo-cs-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 11px 11px;
            min-height: 78px;
        }
        .slo-cs-card .slo-cs-kicker {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .slo-cs-card .slo-cs-kicker i {
            width: 18px;
            height: 18px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #fff;
        }
        .slo-cs-card .slo-cs-value {
            margin-top: 6px;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
            word-break: break-word;
        }
        .slo-cs-card .slo-cs-value.is-empty { color: #94a3b8; font-weight: 600; }
        .slo-cs-ico-cp { background: #0d9488; }
        .slo-cs-ico-frg { background: #d97706; }
        .slo-cs-ico-lp { background: #4f46e5; }
        .slo-cs-ico-dim { background: #334155; }
        .slo-cs-ico-wta { background: #16a34a; }
        .slo-cs-ico-wtd { background: #e11d48; }
        .slo-cs-ico-ship { background: #2563eb; }
        .slo-cs-ico-temu { background: #ea580c; }
        .slo-cs-ico-bb { background: #7c3aed; }
        @media (max-width: 576px) {
            .slo-cs-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Sales Loss Order',
        'sub_title'  => 'All Data',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <div id="slo-toolbar" class="mb-2">
                        <div id="slo-toolbar-row1" role="group" aria-label="Summary metrics">
                            <span class="badge slo-summary-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="All marketplace orders">
                                Orders: <span id="slo-order-count">0</span>
                            </span>
                            <span class="badge bg-primary slo-summary-badge" style="color: white;" title="Channels with at least one order">
                                Channels: <span id="slo-channel-count">0</span>
                            </span>
                            <span class="badge slo-summary-badge" style="background:#fff3cd; color:#856404; border:1px solid #ffe69c;" title="Sum of order amounts">
                                Amount: <span id="slo-amount-total">0.00</span>
                            </span>
                            <span class="badge slo-summary-badge" id="slo-gpft-low-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Click to filter GPFT% under 10%.">
                                GPFT% &lt; 10: <span id="slo-gpft-low-count">0</span>
                            </span>
                            <span class="badge slo-summary-badge" id="slo-groi-low-badge" style="background:#f8d7da; color:#842029; border:1px solid #f5c2c7;" title="Click to filter GROI% under 50%.">
                                GROI % &lt; 50%: <span id="slo-groi-low-count">0</span>
                            </span>
                            <a href="{{ route('sales.order.fulfillment') }}" class="btn btn-sm btn-outline-secondary ms-1" title="Open Sales Order Fulfillment">
                                <i class="ri-truck-line me-1"></i>Fulfillment
                            </a>
                        </div>
                        <div id="slo-toolbar-row2">
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-date-from">From</label>
                                <input type="date" id="slo-date-from" class="form-control form-control-sm" style="width:140px;" title="From date" value="{{ $sloDateFrom ?? '' }}">
                            </div>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-date-to">To</label>
                                <input type="date" id="slo-date-to" class="form-control form-control-sm" style="width:140px;" title="To date" value="{{ $sloDateTo ?? '' }}">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="slo-apply-dates" title="Apply date filter">Apply</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="slo-clear-dates" title="Reset to last 30 days">Last 30 days</button>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-channel-filter">Channels</label>
                                <div class="input-group input-group-sm" style="width:150px;">
                                    <span class="input-group-text" title="Filter by channel"><i class="fas fa-search"></i></span>
                                    <input type="text" id="slo-channel-filter" class="form-control"
                                           list="slo-channel-datalist"
                                           placeholder="Channels…"
                                           autocomplete="off"
                                           title="Filter by channel">
                                </div>
                                <datalist id="slo-channel-datalist">
                                    @foreach(($sloChannels ?? []) as $chOpt)
                                        @if(($chOpt['slug'] ?? '') !== '')
                                            <option value="{{ $chOpt['label'] }}" data-slug="{{ $chOpt['slug'] }}"></option>
                                        @endif
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="slo-filter-field">
                                <label class="visually-hidden" for="slo-status-filter">Status</label>
                                <select id="slo-status-filter" class="form-select form-select-sm" style="width:150px;" title="Filter by status">
                                    <option value="">All statuses</option>
                                </select>
                            </div>
                            <div class="slo-filter-field flex-grow-1">
                                <label class="visually-hidden" for="slo-order-search">Search</label>
                                <input type="text" id="slo-order-search" class="form-control form-control-sm"
                                       placeholder="Search Channel, Order ID, SKU, Status…"
                                       autocomplete="off"
                                       title="Filter orders by Channel, Order ID, SKU, Status">
                            </div>
                            <div id="slo-date-filter-hint">Dates shown as 1 Apr · time EDT only</div>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">All marketplace orders — every status. Defaults to the last 30 days, same as Sales Order Fulfillment → All Order.</p>
                    <div id="sales-loss-order-table" style="height: calc(100vh - 320px);"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade slo-cs-modal" id="sloCostShipModal" tabindex="-1" aria-labelledby="sloCostShipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sloCostShipModalLabel">
                        <i class="fas fa-magnifying-glass me-2"></i>Cost, freight &amp; shipping
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="slo-cs-hero">
                        <img id="slo-cs-img" class="d-none" alt="SKU">
                        <div id="slo-cs-img-missing" class="slo-cs-hero-img is-missing"><i class="fas fa-box-open"></i></div>
                        <div class="slo-cs-hero-meta">
                            <div class="slo-cs-hero-sku" id="slo-cs-sku">—</div>
                            <div class="slo-cs-hero-title" id="slo-cs-title">—</div>
                            <div class="slo-cs-hero-sub">
                                <span class="slo-cs-chip" id="slo-cs-channel">—</span>
                                <span class="slo-cs-chip" id="slo-cs-order">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Cost</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-tag slo-cs-ico-cp"></i>CP</div>
                            <div class="slo-cs-value" id="slo-cs-cp">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-truck-fast slo-cs-ico-frg"></i>Freight</div>
                            <div class="slo-cs-value" id="slo-cs-frght">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-dollar-sign slo-cs-ico-lp"></i>LP</div>
                            <div class="slo-cs-value" id="slo-cs-lp">—</div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Package</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-cube slo-cs-ico-dim"></i>Dimensions</div>
                            <div class="slo-cs-value" id="slo-cs-dims">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-weight-hanging slo-cs-ico-wta"></i>Weight Act</div>
                            <div class="slo-cs-value" id="slo-cs-wt-act">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-scale-balanced slo-cs-ico-wtd"></i>Weight Decl</div>
                            <div class="slo-cs-value" id="slo-cs-wt-decl">—</div>
                        </div>
                    </div>
                    <div class="slo-cs-section-label">Shipping</div>
                    <div class="slo-cs-grid">
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-ship slo-cs-ico-ship"></i>Ship</div>
                            <div class="slo-cs-value" id="slo-cs-ship">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-store slo-cs-ico-temu"></i>Ship Temu</div>
                            <div class="slo-cs-value" id="slo-cs-ship-temu">—</div>
                        </div>
                        <div class="slo-cs-card">
                            <div class="slo-cs-kicker"><i class="fas fa-basket-shopping slo-cs-ico-bb"></i>Ship BB</div>
                            <div class="slo-cs-value" id="slo-cs-ship-bb">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const SLO_TZ = 'America/New_York';
    const sloChannelSlugByLabel = {};
    @foreach(($sloChannels ?? []) as $chOpt)
        @if(($chOpt['slug'] ?? '') !== '' && ($chOpt['label'] ?? '') !== '')
            sloChannelSlugByLabel[@json(strtolower($chOpt['label']))] = @json($chOpt['slug']);
        @endif
    @endforeach

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function copyTextToClipboard(text, btn) {
        const done = function () {
            if (!btn) return;
            const prev = btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            setTimeout(function () {
                btn.classList.remove('copied');
                btn.innerHTML = prev;
            }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                window.prompt('Copy:', text);
            });
        } else {
            window.prompt('Copy:', text);
            done();
        }
    }

    function sloDateParams() {
        const fromEl = document.getElementById('slo-date-from');
        const toEl = document.getElementById('slo-date-to');
        return {
            date_from: fromEl ? (fromEl.value || '') : '',
            date_to: toEl ? (toEl.value || '') : '',
            tz: SLO_TZ,
        };
    }

    function sloUpdateDateHint() {
        const p = sloDateParams();
        const hint = document.getElementById('slo-date-filter-hint');
        if (!hint) return;
        if (!p.date_from && !p.date_to) {
            hint.textContent = 'Last 30 days · display EDT';
        } else {
            hint.textContent = 'Filter: ' + (p.date_from || '…') + ' → ' + (p.date_to || 'today') + ' · display EDT';
        }
    }

    function sloChannelFilterValue() {
        const el = document.getElementById('slo-channel-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function sloResolvedChannelSlug() {
        const label = sloChannelFilterValue().toLowerCase();
        return sloChannelSlugByLabel[label] || '';
    }

    function sloStringSorter(a, b) {
        return String(a || '').localeCompare(String(b || ''), undefined, { numeric: true, sensitivity: 'base' });
    }

    function sloDateSorter(a, b) {
        const as = String(a || '').trim();
        const bs = String(b || '').trim();
        if (!as && !bs) return 0;
        if (!as) return -1;
        if (!bs) return 1;
        return as.localeCompare(bs);
    }

    function sloApplyLatestFirstSort(tbl) {
        if (!tbl || tbl._sloSorting) return;
        tbl._sloSorting = true;
        try {
            tbl.setSort([{ column: 'order_date', dir: 'desc' }]);
        } catch (e) {
            /* table not ready */
        } finally {
            tbl._sloSorting = false;
        }
    }

    /** Source values are naive America/Los_Angeles wall clocks (same as fulfillment). */
    const SLO_SOURCE_TZ = 'America/Los_Angeles';
    const SLO_DISPLAY_TZ = 'America/New_York';

    function sloWallClockToUtcMs(raw, timeZone) {
        const m = String(raw || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) {
            const d = new Date(String(raw || '').replace(' ', 'T'));
            return isNaN(d.getTime()) ? null : d.getTime();
        }
        const y = Number(m[1]);
        const mo = Number(m[2]);
        const d = Number(m[3]);
        const h = Number(m[4] || 0);
        const mi = Number(m[5] || 0);
        const s = Number(m[6] || 0);
        const desired = Date.UTC(y, mo - 1, d, h, mi, s);
        const dtf = new Intl.DateTimeFormat('en-US', {
            timeZone: timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        const wallAsUtc = function (instant) {
            const parts = dtf.formatToParts(new Date(instant));
            const get = function (type) {
                const p = parts.find(function (x) { return x.type === type; });
                return p ? Number(p.value) : 0;
            };
            return Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'), get('second'));
        };
        let instant = desired;
        for (let i = 0; i < 3; i++) {
            instant = desired - (wallAsUtc(instant) - desired);
        }
        return instant;
    }

    function sloFormatEstParts(raw) {
        const s = String(raw || '').trim();
        if (!s) return null;
        const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(s);
        const ms = sloWallClockToUtcMs(s, dateOnly ? SLO_DISPLAY_TZ : SLO_SOURCE_TZ);
        if (ms == null) return null;
        const d = new Date(ms);
        const dateParts = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            day: 'numeric',
            month: 'short',
        }).formatToParts(d);
        const day = (dateParts.find(function (p) { return p.type === 'day'; }) || {}).value || '';
        const month = (dateParts.find(function (p) { return p.type === 'month'; }) || {}).value || '';
        const dateLabel = (day + ' ' + month).trim();
        if (dateOnly) {
            return { date: dateLabel, time: '', title: dateLabel + ' EDT' };
        }
        const time = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        }).format(d);
        const title = new Intl.DateTimeFormat('en-US', {
            timeZone: SLO_DISPLAY_TZ,
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
        }).format(d) + ' EDT';
        return { date: dateLabel, time: time + ' EDT', title: title };
    }

    function sloFormatEstDateCell(cell) {
        const raw = cell.getValue();
        if (!raw) return '—';
        const parts = sloFormatEstParts(raw);
        if (!parts) return escapeHtml(raw);
        const wrap = document.createElement('span');
        wrap.className = 'slo-dt-cell';
        wrap.title = parts.title;
        const dateEl = document.createElement('span');
        dateEl.className = 'slo-dt-date';
        dateEl.textContent = parts.date;
        wrap.appendChild(dateEl);
        if (parts.time) {
            const timeEl = document.createElement('span');
            timeEl.className = 'slo-dt-time';
            timeEl.textContent = parts.time;
            wrap.appendChild(timeEl);
        }
        return wrap;
    }

    function formatMoney(v) {
        if (v === null || v === undefined || v === '') return '—';
        return Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function sloFormatUsd(v) {
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (!Number.isFinite(n)) return '';
        return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function sloFormatWt(v) {
        if (v === null || v === undefined || v === '') return '';
        const n = Number(v);
        if (!Number.isFinite(n)) return '';
        return n.toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' lb';
    }

    function sloFormatDims(row) {
        const parts = [row.l, row.w, row.h].map(function (v) {
            if (v === null || v === undefined || v === '') return null;
            const n = Number(v);
            return Number.isFinite(n) ? n : null;
        });
        if (parts.every(function (p) { return p === null; })) return '';
        return parts.map(function (p) {
            if (p === null) return '—';
            return Number.isInteger(p) ? String(p) : String(p);
        }).join(' × ') + ' in';
    }

    function sloSetCsValue(id, text) {
        const el = document.getElementById(id);
        if (!el) return;
        const has = !!(text && String(text).trim());
        el.textContent = has ? text : '—';
        el.classList.toggle('is-empty', !has);
    }

    function sloOpenCostShipModal(row) {
        const sku = String(row.sku || '').trim();
        const title = String(row.display_title || '').trim();
        const channel = String(row.channel_label || row.mm_slug || '').trim();
        const orderId = String(row.order_id || row.show_id || '').trim();
        document.getElementById('slo-cs-sku').textContent = sku || '—';
        document.getElementById('slo-cs-title').textContent = title || 'No product title';
        document.getElementById('slo-cs-channel').textContent = channel || 'Channel';
        document.getElementById('slo-cs-order').textContent = orderId ? ('Order ' + orderId) : 'No order ID';

        const img = document.getElementById('slo-cs-img');
        const missing = document.getElementById('slo-cs-img-missing');
        const src = String(row.sku_image || '').trim();
        if (img && missing) {
            if (src) {
                img.src = src;
                img.classList.remove('d-none');
                missing.classList.add('d-none');
            } else {
                img.removeAttribute('src');
                img.classList.add('d-none');
                missing.classList.remove('d-none');
            }
        }

        sloSetCsValue('slo-cs-cp', sloFormatUsd(row.cp));
        sloSetCsValue('slo-cs-frght', sloFormatUsd(row.frght));
        sloSetCsValue('slo-cs-lp', sloFormatUsd(row.lp));
        sloSetCsValue('slo-cs-dims', sloFormatDims(row));
        sloSetCsValue('slo-cs-wt-act', sloFormatWt(row.wt_act));
        sloSetCsValue('slo-cs-wt-decl', sloFormatWt(row.wt_decl));
        sloSetCsValue('slo-cs-ship', sloFormatUsd(row.ship));
        sloSetCsValue('slo-cs-ship-temu', sloFormatUsd(row.ship_temu));
        sloSetCsValue('slo-cs-ship-bb', sloFormatUsd(row.ship_bb));

        const modalEl = document.getElementById('sloCostShipModal');
        if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    const sloAnalyticsPctBands = {
        groi: { redBelow: 50, purpleAbove: 100 },
        nroi: { redBelow: 40, purpleAbove: 75 },
        gpft: { redBelow: 17, purpleAbove: 33 },
        npft: { redBelow: 10, purpleAbove: 27 },
    };

    function sloFormatPctCell(cell, metric) {
        const v = cell.getValue();
        if (v === null || v === undefined || v === '') return '—';
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        const band = sloAnalyticsPctBands[metric] || sloAnalyticsPctBands.groi;
        let color = '#006400';
        if (n < band.redBelow) {
            color = '#c00000';
        } else if (n > band.purpleAbove) {
            color = '#7030a0';
        }
        return '<span style="color:' + color + ';font-weight:700">' + n.toFixed(1) + '%</span>';
    }

    function sloStatusFilterValue() {
        const el = document.getElementById('slo-status-filter');
        return el ? String(el.value || '').trim() : '';
    }

    function sloRebuildStatusOptions(rows) {
        const sel = document.getElementById('slo-status-filter');
        if (!sel) return;
        const prev = sel.value;
        const counts = {};
        (rows || []).forEach(function (row) {
            const label = String(row.status_label || row.status || '').trim();
            if (!label || label === '—') return;
            counts[label] = (counts[label] || 0) + 1;
        });
        const labels = Object.keys(counts).sort(function (a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });
        sel.innerHTML = '';
        const all = document.createElement('option');
        all.value = '';
        all.textContent = 'All statuses';
        sel.appendChild(all);
        labels.forEach(function (label) {
            const opt = document.createElement('option');
            opt.value = label;
            opt.textContent = label + ' (' + counts[label].toLocaleString() + ')';
            sel.appendChild(opt);
        });
        if (prev && counts[prev]) {
            sel.value = prev;
        }
    }

    let sloGpftLowFilter = false;
    let sloGroiLowFilter = false;

    function sloIsGpftLow(row) {
        const n = Number(row && row.gpft_pct);
        return Number.isFinite(n) && n < 10;
    }

    function sloIsGroiLow(row) {
        const n = Number(row && row.groi_pct);
        return Number.isFinite(n) && n < 50;
    }

    function sloUpdateGpftLowBadge(rows) {
        const count = (rows || []).filter(sloIsGpftLow).length;
        const countEl = document.getElementById('slo-gpft-low-count');
        if (countEl) countEl.textContent = count.toLocaleString();
        const badge = document.getElementById('slo-gpft-low-badge');
        if (badge) badge.classList.toggle('is-active', sloGpftLowFilter);
    }

    function sloUpdateGroiLowBadge(rows) {
        const count = (rows || []).filter(sloIsGroiLow).length;
        const countEl = document.getElementById('slo-groi-low-count');
        if (countEl) countEl.textContent = count.toLocaleString();
        const badge = document.getElementById('slo-groi-low-badge');
        if (badge) badge.classList.toggle('is-active', sloGroiLowFilter);
    }

    function applyClientFilters(table) {
        if (!table) return;
        const q = ($('#slo-order-search').val() || '').trim().toLowerCase();
        const channel = sloChannelFilterValue();
        const slug = sloResolvedChannelSlug();
        const status = sloStatusFilterValue();

        if (!q && !channel && !status && !sloGpftLowFilter && !sloGroiLowFilter) {
            table.clearFilter(true);
            sloApplyLatestFirstSort(table);
            return;
        }

        table.setFilter(function (data) {
            const searchOk = !q || (
                String(data.channel_label || '').toLowerCase().includes(q)
                || String(data.order_id || '').toLowerCase().includes(q)
                || String(data.sku || '').toLowerCase().includes(q)
                || String(data.status_label || data.status || '').toLowerCase().includes(q)
                || String(data.tracking_number || '').toLowerCase().includes(q)
            );
            if (!searchOk) return false;
            if (status) {
                const rowStatus = String(data.status_label || data.status || '').trim();
                if (rowStatus.toLowerCase() !== status.toLowerCase()) return false;
            }
            if (sloGpftLowFilter && !sloIsGpftLow(data)) return false;
            if (sloGroiLowFilter && !sloIsGroiLow(data)) return false;
            if (!channel) return true;
            if (slug) {
                return String(data.mm_slug || '').toLowerCase() === slug;
            }
            const cq = channel.toLowerCase();
            return String(data.channel_label || '').toLowerCase().includes(cq)
                || String(data.mm_slug || '').toLowerCase().includes(cq);
        });
        sloApplyLatestFirstSort(table);
    }

    const table = new Tabulator('#sales-loss-order-table', {
        pagination: true,
        paginationMode: 'local',
        sortMode: 'local',
        filterMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 500, true],
        movableColumns: false,
        headerSortClickElement: 'header',
        layout: 'fitColumns',
        placeholder: 'Loading sales loss orders…',
        initialSort: [{ column: 'order_date', dir: 'desc' }],
        ajaxURL: '{{ route("sales.loss.order.data") }}',
        ajaxConfig: 'GET',
        ajaxParams: sloDateParams,
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: Object.assign({}, params || {}, sloDateParams()),
                    timeout: 0,
                    success: resolve,
                    error: reject,
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];
            const count = (response && response.count != null) ? Number(response.count) : rows.length;
            const channels = (response && response.channel_count != null) ? Number(response.channel_count) : 0;
            const amount = (response && response.amount_total != null) ? Number(response.amount_total) : 0;
            const countEl = document.getElementById('slo-order-count');
            const chEl = document.getElementById('slo-channel-count');
            const amtEl = document.getElementById('slo-amount-total');
            if (countEl) countEl.textContent = count.toLocaleString();
            if (chEl) chEl.textContent = channels.toLocaleString();
            if (amtEl) amtEl.textContent = amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (response && response.success === false) {
                this.options.placeholder = response.message || 'Failed to load sales loss orders.';
            }
            sloRebuildStatusOptions(rows);
            sloUpdateGpftLowBadge(rows);
            sloUpdateGroiLowBadge(rows);
            return rows;
        },
        dataLoaded: function () {
            applyClientFilters(table);
            sloApplyLatestFirstSort(table);
        },
        columns: [
            {
                title: 'Channel',
                field: 'channel_label',
                minWidth: 100,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const label = escapeHtml(cell.getValue() || '');
                    const url = (row.orders_url || '').trim();
                    if (url) {
                        return `<a href="${escapeHtml(url)}" target="_blank" class="slo-channel-name has-link">${label}</a>`;
                    }
                    return `<span class="slo-channel-name">${label}</span>`;
                },
            },
            {
                title: 'Order ID',
                field: 'order_id',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const orderId = (cell.getValue() || '').toString().trim();
                    if (!orderId) {
                        return '<span class="slo-oc-missing">—</span>';
                    }
                    const url = (row.order_url || '').trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-order-id-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'slo-order-id-dot';
                    wrap.appendChild(dot);
                    const pop = document.createElement('span');
                    pop.className = 'slo-order-id-popover';
                    const text = document.createElement('span');
                    text.className = 'slo-order-id-text';
                    if (url) {
                        const a = document.createElement('a');
                        a.href = url;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.textContent = orderId;
                        a.addEventListener('click', function (ev) { ev.stopPropagation(); });
                        text.appendChild(a);
                    } else {
                        text.textContent = orderId;
                    }
                    pop.appendChild(text);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'slo-order-id-copy';
                    copyBtn.title = 'Copy Order ID';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(orderId, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
            {
                title: 'Date',
                field: 'order_date',
                minWidth: 88,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloDateSorter,
                headerTooltip: 'Latest orders on top · 1 Apr · time EDT only',
                formatter: sloFormatEstDateCell,
            },
            {
                title: 'Tracking',
                field: 'tracking_number',
                minWidth: 70,
                width: 78,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                headerTooltip: 'Hover the dot to see the tracking number and copy it',
                formatter: function (cell) {
                    const tracking = (cell.getValue() || '').toString().trim();
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-tracking-cell';
                    const dot = document.createElement('span');
                    dot.className = 'slo-ch-orders-dot ' + (tracking ? 'green' : 'red');
                    dot.setAttribute('aria-label', tracking ? 'Tracking available' : 'Tracking missing');
                    wrap.appendChild(dot);
                    if (!tracking) {
                        return wrap;
                    }
                    const pop = document.createElement('span');
                    pop.className = 'slo-tracking-popover';
                    const num = document.createElement('span');
                    num.className = 'slo-tracking-num';
                    num.textContent = tracking;
                    pop.appendChild(num);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'slo-tracking-copy';
                    copyBtn.title = 'Copy tracking number';
                    copyBtn.setAttribute('aria-label', 'Copy tracking number');
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(tracking, copyBtn);
                    });
                    pop.appendChild(copyBtn);
                    wrap.appendChild(pop);
                    return wrap;
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 220,
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                formatter: function (cell) {
                    const sku = (cell.getValue() || '').toString().trim();
                    if (!sku) return '—';
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-sku-cell';
                    const code = document.createElement('code');
                    code.textContent = sku;
                    wrap.appendChild(code);
                    const copyBtn = document.createElement('button');
                    copyBtn.type = 'button';
                    copyBtn.className = 'slo-sku-copy';
                    copyBtn.title = 'Copy SKU';
                    copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';
                    copyBtn.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        copyTextToClipboard(sku, copyBtn);
                    });
                    wrap.appendChild(copyBtn);
                    return wrap;
                },
            },
            {
                title: 'Product',
                field: 'display_title',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const title = (cell.getValue() || '').toString().trim();
                    if (!title) return '<span class="slo-oc-missing">—</span>';
                    const wrap = document.createElement('span');
                    wrap.className = 'slo-text-dot-wrap';
                    const dot = document.createElement('span');
                    dot.className = 'slo-text-dot';
                    wrap.appendChild(dot);
                    const box = document.createElement('span');
                    box.className = 'slo-text-dot-box';
                    box.textContent = title;
                    wrap.appendChild(box);
                    return wrap;
                },
            },
            {
                title: 'Qty',
                field: 'quantity',
                minWidth: 60,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
            },
            {
                title: 'Amount',
                field: 'amount',
                minWidth: 80,
                hozAlign: 'right',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) { return formatMoney(cell.getValue()); },
            },
            {
                title: 'Info',
                field: 'cost_ship',
                minWidth: 48,
                width: 52,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                headerTooltip: 'CP, freight, dimensions, weights, LP & shipping',
                titleFormatter: function () {
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-magnifying-glass';
                    icon.setAttribute('aria-hidden', 'true');
                    return icon;
                },
                formatter: function () {
                    return '<button type="button" class="slo-cs-btn" title="View cost & shipping"><i class="fas fa-magnifying-glass"></i></button>';
                },
                cellClick: function (e, cell) {
                    e.preventDefault();
                    e.stopPropagation();
                    sloOpenCostShipModal(cell.getRow().getData() || {});
                },
            },
            {
                title: 'Groi$',
                field: 'groi_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU GROI% from this marketplace Analytics page. Red <50%, dark green 50–100%, purple >100%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'groi'); },
            },
            {
                title: 'Nroi%',
                field: 'nroi_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU NROI% from this marketplace Analytics page. Red <40%, dark green 40–75%, purple >75%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'nroi'); },
            },
            {
                title: 'Gpft%',
                field: 'gpft_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU GPFT% from this marketplace Analytics page. Red <17%, dark green 17–33%, purple >33%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'gpft'); },
            },
            {
                title: 'Npft%',
                field: 'npft_pct',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'SKU NPFT% from this marketplace Analytics page. Red <10%, dark green 10–27%, purple >27%.',
                formatter: function (cell) { return sloFormatPctCell(cell, 'npft'); },
            },
            {
                title: 'INV',
                field: 'INV',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const raw = cell.getValue();
                    const n = (raw === null || raw === undefined || raw === '') ? 0 : Number(raw);
                    const label = Number.isFinite(n) ? n.toLocaleString() : '0';
                    if (!Number.isFinite(n) || n === 0) {
                        return '<span class="text-danger fw-semibold">' + label + '</span>';
                    }
                    return label;
                },
            },
            {
                title: 'ovl30',
                field: 'l30',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'OV L30 — same as /amazon-tabulator-view (shopify quantity)',
                formatter: function (cell) {
                    const n = Math.round(parseFloat(cell.getValue()) || 0);
                    return String(n);
                },
            },
            {
                title: 'DIL',
                field: 'dil',
                minWidth: 70,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                headerTooltip: 'Dil% = OV L30 / INV × 100 — same slabs as /amazon-tabulator-view',
                formatter: function (cell) {
                    const row = cell.getRow().getData() || {};
                    const inv = parseFloat(row.INV) || 0;
                    const ovl30 = parseFloat(row.l30) || 0;
                    if (inv === 0) {
                        return '<span class="slo-dil slo-dil-zero">0%</span>';
                    }
                    const dil = (ovl30 / inv) * 100;
                    let cls = 'slo-dil-pink';
                    if (dil < 16.66) cls = 'slo-dil-red';
                    else if (dil < 25) cls = 'slo-dil-yellow';
                    else if (dil < 50) cls = 'slo-dil-green';
                    return '<span class="slo-dil ' + cls + '">' + Math.round(dil) + '%</span>';
                },
            },
            {
                title: 'Status',
                field: 'status_label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                sorter: sloStringSorter,
                headerTooltip: 'Original marketplace status',
                formatter: function (cell) {
                    const label = escapeHtml(cell.getValue() || cell.getRow().getData().status || '—');
                    return `<span class="slo-status-badge">${label}</span>`;
                },
            },
            {
                title: 'Label',
                field: 'label',
                minWidth: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    const v = (cell.getValue() || '').toString().trim();
                    return v ? escapeHtml(v) : '<span class="slo-oc-missing">—</span>';
                },
            },
        ],
    });

    table.on('dataSorted', function (sorters) {
        if (table._sloSorting) return;
        const first = sorters && sorters[0];
        const ok = first && (first.field === 'order_date' || first.column === 'order_date') && first.dir === 'desc';
        if (!ok) {
            sloApplyLatestFirstSort(table);
        }
    });

    let searchTimer = null;
    $('#slo-order-search, #slo-channel-filter').on('input keyup', function () {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { applyClientFilters(table); }, 150);
    });
    $('#slo-status-filter').on('change', function () {
        applyClientFilters(table);
    });
    document.getElementById('slo-gpft-low-badge')?.addEventListener('click', function () {
        sloGpftLowFilter = !sloGpftLowFilter;
        this.classList.toggle('is-active', sloGpftLowFilter);
        this.title = sloGpftLowFilter
            ? 'Showing GPFT% under 10%. Click to show all.'
            : 'Showing all. Click to filter GPFT% under 10%.';
        applyClientFilters(table);
    });
    document.getElementById('slo-groi-low-badge')?.addEventListener('click', function () {
        sloGroiLowFilter = !sloGroiLowFilter;
        this.classList.toggle('is-active', sloGroiLowFilter);
        this.title = sloGroiLowFilter
            ? 'Showing GROI% under 50%. Click to show all.'
            : 'Click to filter GROI% under 50%.';
        applyClientFilters(table);
    });

    document.getElementById('slo-apply-dates')?.addEventListener('click', function () {
        sloUpdateDateHint();
        table.setData();
    });
    document.getElementById('slo-clear-dates')?.addEventListener('click', function () {
        const fromEl = document.getElementById('slo-date-from');
        const toEl = document.getElementById('slo-date-to');
        if (fromEl) fromEl.value = @json($sloDateFrom ?? '');
        if (toEl) toEl.value = @json($sloDateTo ?? '');
        sloUpdateDateHint();
        table.setData();
    });
    ['slo-date-from', 'slo-date-to'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', sloUpdateDateHint);
    });
    sloUpdateDateHint();
})();
</script>
@endsection
