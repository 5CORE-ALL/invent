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
        }
        #of-toolbar .of-summary-badge {
            font-size: 0.72rem !important;
            padding: 0.2rem 0.45rem !important;
            line-height: 1.15;
            font-weight: 600 !important;
            white-space: nowrap;
        }
        #of-channels-badge {
            cursor: pointer;
            border: 0;
        }
        #of-channels-badge:hover { filter: brightness(1.12); }
        .of-status-filter { position: relative; }
        .of-status-menu {
            position: absolute;
            z-index: 30;
            top: calc(100% + 4px);
            left: 0;
            min-width: 220px;
            max-height: 280px;
            overflow: auto;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
            padding: 0.35rem 0.5rem;
        }
        .of-status-menu label {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin: 0;
            padding: 0.15rem 0;
            font-size: 0.78rem;
            cursor: pointer;
            white-space: nowrap;
        }
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
                        <div class="of-status-filter">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="of-status-btn">Status</button>
                            <div class="of-status-menu" id="of-status-menu" hidden>
                                <label><input type="checkbox" id="of-status-all" checked> All</label>
                                <div id="of-status-options"></div>
                            </div>
                        </div>
                        <select id="of-paid-filter" class="form-select form-select-sm" style="width:130px;" title="Paid or unpaid">
                            <option value="">Paid / Unpaid</option>
                            <option value="Paid">Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                        <input type="text" id="of-search" class="form-control form-control-sm" style="min-width:180px; flex:1;" placeholder="Search order id…" autocomplete="off" title="Filter by order id">
                    </div>
                    <p class="small text-muted mb-2">@if(!empty($ofCreateOrders))Orders entered by hand for marketplaces without an API. They also appear on the Orders page under the marketplace name. A new order shows <strong>Order Created</strong>; use <i class="fas fa-check-circle" aria-hidden="true"></i> Fulfill once the label is bought — the tracking number is fetched from Veeqo / 4Seller at that moment and the status becomes Fulfilled.@elseif(!empty($ofDeliveredOnly))Orders from 15 Sep 2026 whose marketplace status or carrier tracking status is Delivered. The date filter defaults to the last 30 days.@elseif(!empty($ofTransitOnly))Orders from 15 Sep 2026 that are shipped or in transit and not yet delivered. The date filter defaults to the last 30 days.@elseif(!empty($ofScanPendingOnly))Orders from 15 Sep 2026 that have a tracking number and no carrier scan yet. The date filter defaults to the last 30 days.@elseif(!empty($ofUnpaidOnly))Unpaid orders from 15 Sep 2026. The date filter defaults to the last 30 days.@elseif(!empty($ofPendingOnly))Orders from 15 Sep 2026 that are still waiting to ship. The date filter defaults to the last 30 days.@else Orders from 15 Sep 2026. The date filter defaults to the last 30 days. Search matches the order id. Carrier and tracking status come from the tracking number (USPS, GOFO, FedEx, UPS).@endif</p>
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
        setNavCount(pageKey, rows.length);
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
        const boxes = document.querySelectorAll('#of-status-options input[type="checkbox"]');
        const all = document.getElementById('of-status-all');
        if (!boxes.length || (all && all.checked)) return null;
        const picked = [];
        boxes.forEach(function (box) {
            if (box.checked) picked.push(box.value);
        });
        return picked;
    }

    function refreshStatusButton() {
        const btn = document.getElementById('of-status-btn');
        const picked = selectedStatuses();
        if (!btn) return;
        btn.textContent = picked === null ? 'Status' : ('Status (' + picked.length + ')');
    }

    function renderStatusOptions(rows) {
        const host = document.getElementById('of-status-options');
        const all = document.getElementById('of-status-all');
        if (!host) return;
        const seen = {};
        (rows || []).forEach(function (row) {
            seen[statusLabel(row)] = true;
        });
        const labels = Object.keys(seen).sort(function (a, b) {
            return a.localeCompare(b);
        });
        const saved = readSavedStatuses();
        host.innerHTML = labels.map(function (label) {
            const checked = saved === null || saved.indexOf(label) !== -1;
            return '<label><input type="checkbox" value="' + escapeHtml(label) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(label) + '</label>';
        }).join('');
        if (all) all.checked = saved === null || labels.every(function (label) { return saved.indexOf(label) !== -1; });
        refreshStatusButton();
    }

    function applyFilters(table) {
        const q = String(document.getElementById('of-search')?.value || '').trim().toLowerCase();
        const channel = String(document.getElementById('of-channel-filter')?.value || '').trim().toLowerCase();
        const paid = String(document.getElementById('of-paid-filter')?.value || '').trim();
        const statuses = selectedStatuses();
        if (!q && !channel && !paid && statuses === null) {
            table.clearFilter(true);
            countsFromRows(table.getData());
            return;
        }
        table.setFilter(function (data) {
            if (statuses && statuses.indexOf(statusLabel(data)) === -1) return false;
            if (paid && String(data.paid_label || '') !== paid) return false;
            if (channel && !String(data.channel || '').toLowerCase().includes(channel)) return false;
            if (!q) return true;
            return String(data.order_id || '').toLowerCase().includes(q);
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
        initialSort: [{ column: 'order_date', dir: 'asc' }],
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
            table.setSort([{ column: 'order_date', dir: 'asc' }]);
            renderStatusOptions(table.getData());
            applyFilters(table);
            setTimeout(function () {
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
                    return html + '</span>';
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
                    const value = cell.getValue();
                    if (value === null || value === undefined || value === '') return '—';
                    return Number(value).toLocaleString();
                },
            },
            {
                title: 'Edit',
                field: 'id',
                width: createOrdersPage ? 150 : 100,
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

    document.getElementById('of-status-btn')?.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        const menu = document.getElementById('of-status-menu');
        if (!menu) return;
        menu.hidden = !menu.hidden;
    });
    document.getElementById('of-status-menu')?.addEventListener('change', function (ev) {
        const target = ev.target;
        if (!target || target.type !== 'checkbox') return;
        const boxes = document.querySelectorAll('#of-status-options input[type="checkbox"]');
        const all = document.getElementById('of-status-all');
        if (target.id === 'of-status-all') {
            boxes.forEach(function (box) { box.checked = target.checked; });
        } else if (all) {
            all.checked = Array.from(boxes).every(function (box) { return box.checked; });
        }
        const picked = [];
        boxes.forEach(function (box) { if (box.checked) picked.push(box.value); });
        writeSavedStatuses(!!(all && all.checked), picked);
        refreshStatusButton();
        applyFilters(table);
    });
    document.addEventListener('click', function (ev) {
        const menu = document.getElementById('of-status-menu');
        const wrap = document.querySelector('.of-status-filter');
        if (!menu || menu.hidden || !wrap || wrap.contains(ev.target)) return;
        menu.hidden = true;
    });

    ['of-search', 'of-channel-filter', 'of-paid-filter'].forEach(function (id) {
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
        row.update({
            tracking: update.tracking || '',
            tracking_source: update.tracking_source || '',
            tracking_checked: true,
            carrier: update.carrier || '',
            tracking_status: update.tracking_status || '',
            tracking_status_checked: !!update.tracking_status_checked,
        });
    }

    function applyStatusUpdate(update) {
        const number = String(update.tracking || '').replace(/\s+/g, '').toUpperCase();
        if (!number) return;
        table.getRows().forEach(function (row) {
            const data = row.getData();
            const current = String(data.tracking || '').replace(/\s+/g, '').toUpperCase();
            if (current !== number) return;
            row.update({
                carrier: update.carrier || data.carrier || '',
                tracking_status: update.tracking_status || '',
                tracking_status_checked: true,
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

    function fillTrackingStatus(attempt) {
        if (statusLookupRunning || attempt > 3) return;
        const seen = {};
        const numbers = [];
        table.getData().forEach(function (row) {
            const number = String(row.tracking || '').trim();
            const key = number.replace(/\s+/g, '').toUpperCase();
            if (!key || row.tracking_status_checked || seen[key]) return;
            seen[key] = true;
            numbers.push(number);
        });
        const batch = numbers.slice(0, 10);
        if (!batch.length) return;
        statusLookupRunning = true;
        $.ajax({
            url: statusUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { numbers: batch },
        }).done(function (res) {
            const updates = (res && Array.isArray(res.updates)) ? res.updates : [];
            updates.forEach(applyStatusUpdate);
            statusLookupRunning = false;
            if (updates.length && numbers.length > batch.length && attempt < 3) {
                fillTrackingStatus(attempt + 1);
            }
        }).fail(function () {
            statusLookupRunning = false;
        });
    }

    function openTrackingModal(row) {
        document.getElementById('of-track-id').value = row.id || '';
        document.getElementById('of-track-slug').value = row.mm_slug || '';
        document.getElementById('of-track-channel').textContent = row.channel || '—';
        document.getElementById('of-track-order').textContent = row.order_id || '—';
        document.getElementById('of-track-sku').textContent = row.sku || '—';
        document.getElementById('of-track-number').value = row.tracking || '';
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
        $.ajax({
            url: saveUrl,
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: {
                id: id,
                mm_slug: document.getElementById('of-track-slug')?.value || '',
                order_id: document.getElementById('of-track-order')?.textContent === '—' ? '' : document.getElementById('of-track-order')?.textContent,
                sku: document.getElementById('of-track-sku')?.textContent === '—' ? '' : document.getElementById('of-track-sku')?.textContent,
                tracking_number: number,
            },
        }).done(function (res) {
            if (!res || res.success === false) {
                err.textContent = (res && res.message) || 'Could not save the tracking number.';
                err.style.display = 'block';
                return;
            }
            applyTrackingUpdate(res);
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
        setTimeout(function () { fillTracking(0); fillTrackingStatus(0); }, 200);
    }

    // Order amount follows the item lines until the user types their own figure.
    let amountTouched = false;
    let amountAutoMode = 'sum'; // 'sum' = live total of the lines; 'server' = blank → recalculated on save (edit mode)

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
            if (amountAutoMode === 'server') {
                amount.value = '';
                amount.placeholder = 'on save';
                setText('of-order-amount-hint', 'auto: re-totalled from all items on save');
            } else {
                amount.value = priced ? money(sum) : '';
                amount.placeholder = '0.00';
                setText('of-order-amount-hint', 'auto: items × qty');
            }
        }
        updateFooterSummary();
    }

    document.getElementById('of-order-amount')?.addEventListener('input', function () {
        amountTouched = true;
        setText('of-order-amount-hint', 'entered manually');
        updateFooterSummary();
    });

    function addOrderLine(sku, qty, removable, price) {
        const host = document.getElementById('of-order-lines');
        if (!host) return;
        const line = document.createElement('div');
        line.className = 'of-order-line';
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
            // Editing a line of an existing order: let the server re-total all its lines.
            if (amountAutoMode === 'server') amountTouched = false;
            recalcOrderAmount();
        }
        qtyInput.addEventListener('input', onLineChange);
        priceInput.addEventListener('input', onLineChange);
        line.querySelector('.of-line-sku').addEventListener('input', recalcOrderAmount);
        line.querySelector('.of-line-sku').addEventListener('change', recalcOrderAmount);
        const remove = line.querySelector('.of-line-remove');
        if (removable === false) remove.style.visibility = 'hidden';
        remove.addEventListener('click', function () {
            if (host.querySelectorAll('.of-order-line').length > 1) { line.remove(); recalcOrderAmount(); }
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
            if (sku) lines.push({ sku: sku, qty: qty, price: priceRaw });
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
        amountAutoMode = 'sum';
        const host = document.getElementById('of-order-lines');
        if (host) host.innerHTML = '';
        addOrderLine('', 1, true, null);
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
        // Keep the stored order total until a line changes; then the server re-totals every line.
        amountTouched = true;
        amountAutoMode = 'server';
        setText('of-order-amount-hint', 'stored order total');
        const host = document.getElementById('of-order-lines');
        if (host) host.innerHTML = '';
        addOrderLine(row.sku || '', row.qty || 1, false, row.unit_price);
        document.getElementById('of-order-add-line').style.display = 'none';
        document.getElementById('of-order-lines-help').textContent = 'Header changes apply to every SKU of this order; SKU, qty and price here apply to this row only.';
        showFormError('of-order-error', '');
        showModal('of-order-modal');
    }

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
        if (isEdit) {
            payload.sku = lines[0].sku;
            payload.qty = lines[0].qty;
            payload.price = lines[0].price;
            payload._method = 'PUT';
        } else {
            payload.lines = lines;
        }
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
        if (!window.confirm('Delete order ' + (row.order_id || '') + ' (' + (row.sku || '') + ')?')) return;
        $.ajax({
            url: manualUrl(row.manual_id),
            type: 'POST',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            data: { _method: 'DELETE' },
        }).done(function (res) {
            if (res && res.success) {
                const live = table.getRow(row.id);
                if (live) live.delete();
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
            document.getElementById('of-fulfill-order').textContent = (row.channel || '') + ' · ' + (row.order_id || '') + ' · ' + (row.sku || '');
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
@endsection
