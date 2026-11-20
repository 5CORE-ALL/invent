@extends('layouts.vertical', ['title' => 'Advance & Payments'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Center all headers and cell content */
    #advance-payment-table .tabulator-col .tabulator-col-title { text-align: center; }
    #advance-payment-table .tabulator-cell { text-align: center; }

    /* Paste-snippet box */
    .paste-box { border: 2px dashed #adb5bd; border-radius: 6px; min-height: 70px; display: flex; align-items: center; justify-content: center; text-align: center; padding: 6px; cursor: pointer; background: #f8f9fa; color: #6c757d; font-size: 0.8rem; outline: none; }
    .paste-box:focus { border-color: #0d6efd; background: #eef4ff; color: #0d6efd; }
    .paste-box-preview { max-width: 100%; max-height: 140px; border-radius: 4px; }

    /* Bank dot (same look as /supplier.list): green = bank details on file, red = missing */
    .supplier-data-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: middle; }
    .supplier-data-dot--ok { background-color: #198754; }
    .supplier-data-dot--missing { background-color: #dc3545; }
    .bank-dot-btn { border: 0 !important; background: transparent !important; box-shadow: none !important; line-height: 1; padding: 0; }

    /* Advance-required toggle: mustard = advance still required, green = proof image attached */
    .adv-toggle { position: relative; display: inline-block; width: 38px; height: 20px; border-radius: 20px; background: #d4a017; vertical-align: middle; cursor: default; transition: background .2s; }
    .adv-toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.3); transition: left .2s; }
    .adv-toggle.is-done { background: #198754; }
    .adv-toggle.is-done::after { left: 20px; }
    #mustard-filter-btn { border-color: #d4a017; color: #8a6a00; }
    #mustard-filter-btn:hover { background: rgba(212,160,23,.15); }
    #mustard-filter-btn.active { background: #d4a017; color: #fff; border-color: #d4a017; }

    /* PO number link */
    #advance-payment-table a.po-link { color: #0d6efd; font-weight: 700; text-decoration: none; }
    #advance-payment-table a.po-link:hover { text-decoration: underline; }

    /* Actions column: icon-only buttons, no background */
    .action-icon-btn { background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 2px 6px; font-size: 1rem; }
    .action-icon-btn.edit-payment-btn { color: #3fbfc4 !important; }
    .action-icon-btn.archive-payment-btn { color: #dc3545 !important; }
    .action-icon-btn.restore-payment-btn { color: #198754 !important; }
    .action-icon-btn:hover { background: rgba(0,0,0,0.06) !important; }
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
    
    /* Remarks tooltip styling */
    .remarks-tooltip {
        position: relative;
        cursor: help;
        display: inline-block;
    }
    
    .remarks-tooltip:hover::after {
        content: attr(data-remarks);
        position: fixed;
        bottom: auto;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #333;
        color: #fff;
        padding: 12px 16px;
        border-radius: 6px;
        white-space: pre-wrap;
        max-width: 400px;
        min-width: 200px;
        z-index: 99999;
        font-size: 13px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        word-wrap: break-word;
        pointer-events: none;
        line-height: 1.5;
    }
    
    .remarks-tooltip:hover::before {
        content: '';
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        border: 8px solid transparent;
        border-top-color: #333;
        z-index: 99998;
        margin-top: -180px;
        pointer-events: none;
    }
    
    /* Alternative: Simple tooltip that appears above */
    .remarks-tooltip-simple {
        position: relative;
        cursor: help;
        display: inline-block;
    }
    
    .remarks-tooltip-simple:hover::after {
        content: attr(data-remarks);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background-color: #1f2937;
        color: #fff;
        padding: 10px 14px;
        border-radius: 6px;
        white-space: pre-wrap;
        max-width: 350px;
        min-width: 200px;
        z-index: 10000;
        font-size: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.25);
        margin-bottom: 8px;
        word-wrap: break-word;
        pointer-events: none;
        line-height: 1.5;
        text-align: left;
    }
    
    .remarks-tooltip-simple:hover::before {
        content: '';
        position: absolute;
        bottom: 92%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: #1f2937;
        z-index: 10001;
        pointer-events: none;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Advance & Payments', 'sub_title' => 'Advance & Payments'])

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
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'advance_payments'])
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="advance-amount-search" class="form-control border-start-0" placeholder="Search VO Number, Supplier name...">
                    </div>
                    <button type="button" id="mustard-filter-btn" class="btn btn-sm" title="Show only rows where the advance is still required (no Adv Img)">
                        <span class="adv-toggle" style="width:28px;height:15px;vertical-align:-3px;"></span> Adv Required
                    </button>
                    <select id="archive-filter" class="form-select form-select-sm" style="max-width: 140px;" aria-label="Status filter" title="Archived here = archived on Purchase Contracts too" autocomplete="off">
                        <option value="active" selected>Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All</option>
                    </select>
                    <select id="stage-filter" class="form-select form-select-sm" style="max-width: 180px;" aria-label="PO Stage filter" autocomplete="off">
                        <option value="" selected>All PO Stages</option>
                        @foreach(config('po_stages.stages', []) as $stageKey => $stageMeta)
                            <option value="{{ $stageKey }}">{{ $stageMeta['label'] }}</option>
                        @endforeach
                        <option value="none">No Stage</option>
                    </select>
                    <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createPaymentModal">
                            <i class="fas fa-plus-circle me-1"></i> Create Payment Voucher
                        </button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-secondary fs-6 fw-semibold px-3 py-2">Rows: <span id="badge-rows">0</span></span>
                    <span class="badge bg-primary fs-6 fw-semibold px-3 py-2">Amount: <span id="badge-amount">$ 0</span></span>
                    <span class="badge bg-success fs-6 fw-semibold px-3 py-2">Advance: <span id="badge-advance">$ 0</span></span>
                    <span class="badge bg-warning text-dark fs-6 fw-semibold px-3 py-2">Balance: <span id="badge-balance">$ 0</span></span>
                </div>
                <div id="advance-payment-table"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createPaymentModal" tabindex="-1" aria-labelledby="createPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createPaymentModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Create Payment Voucher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('advance.payments.save') }}" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Voucher Number (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Voucher Number</label>
                            <input type="text" class="form-control" name="vo_number" value="{{ $voNumber }}" readonly>
                        </div>

                        {{-- Supplier (hidden, auto-filled from the selected Purchase Contract) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Supplier</label>
                            <select class="form-select" name="supplier_id" id="supplierSelect">
                                <option value="" disabled selected>Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Purchase Contract --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Purchase Contract <span class="text-danger">*</span></label>
                            <select class="form-select" name="purchase_contract_id" id="purchaseContractSelect" required>
                                <option value="" disabled selected>Select Purchase Contract</option>
                                @foreach($purchaseOrders as $order)
                                    <option value="{{ $order->id }}" data-amount="{{ $order->total_amount }}" data-advance="{{ $order->advance_amount }}" data-supplier="{{ $order->supplier_id }}">{{ $order->po_number }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Total Amount (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Total Amount</label>
                            <input type="number" name="amount" id="tota_amount" class="form-control" readonly>
                        </div>

                        {{-- Advance Amount (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Advance Amount</label>
                            <input type="number" name="advance_amount" id="advance_amount" class="form-control" readonly>
                        </div>

                        {{-- Balance (Auto calc) (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Balance</label>
                            <input type="number" id="balanceInput" class="form-control" readonly>
                        </div>

                        {{-- Payment Image --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Image</label>
                            <input type="file" name="image" id="create_image" class="form-control" accept="image/*">
                            <div class="paste-box mt-2" tabindex="0" data-target="#create_image" title="Click here, then press Ctrl/Cmd + V to paste a screenshot">
                                <span class="paste-box-hint"><i class="fas fa-paste me-1"></i> Click &amp; paste snippet here (Ctrl/Cmd + V)</span>
                                <img class="paste-box-preview d-none" alt="Pasted snippet">
                            </div>
                        </div>

                        {{-- Balance Image --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Balance Image</label>
                            <input type="file" name="balance_image" id="create_balance_image" class="form-control" accept="image/*">
                            <div class="paste-box mt-2" tabindex="0" data-target="#create_balance_image" title="Click here, then press Ctrl/Cmd + V to paste a screenshot">
                                <span class="paste-box-hint"><i class="fas fa-paste me-1"></i> Click &amp; paste snippet here (Ctrl/Cmd + V)</span>
                                <img class="paste-box-preview d-none" alt="Pasted snippet">
                            </div>
                        </div>

                        {{-- Remarks --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title fw-bold" id="editPaymentModalLabel">
                    <i class="fas fa-edit me-2"></i> Edit Payment Voucher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form method="POST" action="{{ route('advance.payments.update') }}" enctype="multipart/form-data" autocomplete="off" id="editPaymentForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="payment_id" id="edit_payment_id">
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- Voucher Number (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Voucher Number</label>
                            <input type="text" class="form-control" name="vo_number" id="edit_vo_number" readonly>
                        </div>

                        {{-- Supplier (hidden, auto-filled from the selected Purchase Contract) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Supplier</label>
                            <select class="form-select" name="supplier_id" id="edit_supplier_id">
                                <option value="" disabled>Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Purchase Contract (hidden in edit, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Purchase Contract</label>
                            <select class="form-select" name="purchase_contract_id" id="edit_purchase_contract_id">
                                <option value="" disabled>Select Purchase Contract</option>
                                @foreach($purchaseOrders as $order)
                                    <option value="{{ $order->id }}" data-amount="{{ $order->total_amount }}" data-advance="{{ $order->advance_amount }}" data-supplier="{{ $order->supplier_id }}">{{ $order->po_number }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Total Amount (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Total Amount</label>
                            <input type="number" name="amount" id="edit_amount" class="form-control" readonly>
                        </div>

                        {{-- Advance Amount (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Advance Amount</label>
                            <input type="number" name="advance_amount" id="edit_advance_amount" class="form-control" readonly>
                        </div>

                        {{-- Balance (Auto calc) (hidden, still submitted) --}}
                        <div class="col-md-4 d-none">
                            <label class="form-label fw-semibold">Balance</label>
                            <input type="number" id="edit_balance" class="form-control" readonly>
                        </div>

                        {{-- Payment Image --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Image</label>
                            <input type="file" name="image" id="edit_image" class="form-control" accept="image/*">
                            <div class="paste-box mt-2" tabindex="0" data-target="#edit_image" title="Click here, then press Ctrl/Cmd + V to paste a screenshot">
                                <span class="paste-box-hint"><i class="fas fa-paste me-1"></i> Click &amp; paste snippet here (Ctrl/Cmd + V)</span>
                                <img class="paste-box-preview d-none" alt="Pasted snippet">
                            </div>
                            <div id="edit_image_preview" class="mt-2"></div>
                        </div>

                        {{-- Balance Image --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Balance Image</label>
                            <input type="file" name="balance_image" id="edit_balance_image" class="form-control" accept="image/*">
                            <div class="paste-box mt-2" tabindex="0" data-target="#edit_balance_image" title="Click here, then press Ctrl/Cmd + V to paste a screenshot">
                                <span class="paste-box-hint"><i class="fas fa-paste me-1"></i> Click &amp; paste snippet here (Ctrl/Cmd + V)</span>
                                <img class="paste-box-preview d-none" alt="Pasted snippet">
                            </div>
                            <div id="edit_balance_image_preview" class="mt-2"></div>
                        </div>

                        {{-- Remarks --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="submit" class="btn btn-warning" id="edit_submit-btn">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

{{-- Read-only bank details (data from /supplier.list) --}}
<div class="modal fade" id="bankViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-university me-2"></i> Bank Details &mdash; <span id="bankViewSupplierName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bankViewBody"></div>
        </div>
    </div>
</div>

<div id="image-preview-popup" 
     style="display:none; position:absolute; z-index:99999; border:1px solid #ccc; background:#fff; padding:5px; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
    <img src="" style="height:150px;" id="preview-popup-img">
</div>

@endsection
@section('script')
@include('purchase-master.partials.po-stage')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            // Advance required = mustard until an Adv Img is attached (then green)
            let mustardOnly = false;
            const hasAdvImage = function(row) { return !!(row && row.image && String(row.image).trim() !== ''); };
            const hasBalImage = function(row) { return !!(row && row.balance_image && String(row.balance_image).trim() !== ''); };

            // Currency follows the PO: USD -> "$", RMB (Yuan) -> "¥"
            const CURRENCY_SYMBOLS = { USD: '$', RMB: '¥' };
            const currencySymbol = function(code) { return CURRENCY_SYMBOLS[code] || CURRENCY_SYMBOLS.USD; };
            const roundFormatter = function(cell) {
                const num = parseFloat(cell.getValue());
                if (isNaN(num)) return '';
                return currencySymbol(cell.getRow().getData().currency) + ' ' + Math.round(num);
            };

            let table = new Tabulator("#advance-payment-table", {
                ajaxURL: "/advance-and-payments/data",
                ajaxConfig: "GET",
                layout: "fitColumns",
                columnDefaults: {
                    hozAlign: "center",
                    headerHozAlign: "center",
                    vertAlign: "middle",
                },
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "500px",
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "S.No",
                        formatter: "rownum",
                        hozAlign: "center",
                        width: 80,
                        headerHozAlign: "center",
                        visible: false,
                    },
                    { title: "VO Number", field: "vo_number", hozAlign: "center", visible: false },
                    {
                        title: "PO",
                        field: "purchase_contract",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        formatter: function(cell) {
                            const po = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const date = rowData.po_date;
                            if (!po) return '<span class="text-muted">-</span>';
                            const link = rowData.purchase_contract_id
                                ? '<a href="/purchase-order/' + rowData.purchase_contract_id + '/generate-pdf" target="_blank" rel="noopener" class="po-link" title="Open Purchase Order">' + po + '</a>'
                                : po;
                            return '<div style="line-height:1.25;">' + link +
                                (date ? '<div class="text-muted" style="font-size:0.72rem;font-weight:500;">' + date + '</div>' : '') +
                                '</div>';
                        }
                    },
                    {
                        title: "Supplier Name",
                        field: "supplier_name",
                        hozAlign: "center",
                        width: 72,
                        minWidth: 56,
                        maxWidth: 96,
                        widthGrow: 0,
                        formatter: function(cell) {
                            const value = String(cell.getValue() || '').trim();
                            if (!value) return '-';
                            const first = value.split(/\s+/).filter(Boolean)[0] || value;
                            const esc = function(s) {
                                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                            };
                            return '<span title="' + esc(value) + '" style="font-weight:700;font-size:0.72rem;white-space:nowrap;">' + esc(first) + '</span>';
                        }
                    },
                    {
                        title: "Bank",
                        field: "bank_count",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: true,
                        width: 80,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const count = parseInt(row.bank_count) || 0;
                            if (!row.supplier_id) return '<span class="text-muted">-</span>';
                            const esc = function(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };
                            return '<button type="button" class="btn btn-link bank-dot-btn bank-view-btn"' +
                                ' data-supplier-id="' + row.supplier_id + '"' +
                                ' data-supplier-name="' + esc(row.supplier_name || '') + '"' +
                                ' title="' + (count > 0 ? count + ' bank account(s)' : 'No bank details') + '">' +
                                '<span class="supplier-data-dot ' + (count > 0 ? 'supplier-data-dot--ok' : 'supplier-data-dot--missing') + '"></span>' +
                                '</button>';
                        }
                    },
                    { title: "Amount", field:"amount", hozAlign: "center", formatter: roundFormatter },
                    { title: "Adv Amt", field:"advance_amount", hozAlign: "center", formatter: roundFormatter },
                    {
                        title: "Adv Req",
                        field: "image",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: false,
                        width: 90,
                        formatter: function(cell) {
                            const done = hasAdvImage(cell.getRow().getData());
                            return '<span class="adv-toggle ' + (done ? 'is-done' : '') + '" title="' + (done ? 'Advance proof attached' : 'Advance required - no image yet') + '"></span>';
                        }
                    },
                    { 
                        title: "Adv Img", 
                        field: "image",
                        formatter: function (cell) {
                            const url = cell.getValue();
                            if (!url) return '';
                            return `<img src="${url}" 
                                        class="hover-thumbnail" 
                                        style="height:40px; cursor:pointer;" 
                                        data-preview-url="${url}">`;
                        }
                    },
                    { title: "Balance", field:"balance", hozAlign: "center", headerHozAlign: "center", formatter: roundFormatter },
                    {
                        title: "Bal Req",
                        field: "balance_image",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: false,
                        width: 90,
                        formatter: function(cell) {
                            const done = hasBalImage(cell.getRow().getData());
                            return '<span class="adv-toggle ' + (done ? 'is-done' : '') + '" title="' + (done ? 'Balance proof attached' : 'Balance required - no image yet') + '"></span>';
                        }
                    },
                    {
                        title: "Bal Img",
                        field: "balance_image",
                        formatter: function (cell) {
                            const url = cell.getValue();
                            if (!url) return '';
                            return `<img src="${url}"
                                        class="hover-thumbnail"
                                        style="height:40px; cursor:pointer;"
                                        data-preview-url="${url}">`;
                        }
                    },
                    {
                        title: "PO Stage",
                        field: "po_stage",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: true,
                        width: 170,
                        formatter: function(cell) {
                            return poStageSelectHtml(cell.getRow().getData().purchase_contract_id, cell.getValue());
                        }
                    },
                    { 
                        title: "Remarks", 
                        field:"remarks", 
                        hozAlign: "center",
                        formatter: function(cell) {
                            const remarks = cell.getValue();
                            if (!remarks || remarks.trim() === '') {
                                return '<span class="text-muted">-</span>';
                            }
                            // Escape HTML and special characters for tooltip
                            const escapedRemarks = remarks
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;')
                                .replace(/\n/g, '<br>');
                            
                            // Truncate if too long for display
                            const displayText = remarks.length > 30 ? remarks.substring(0, 30) + '...' : remarks;
                            const escapedDisplayText = displayText
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                            
                            return `<span class="remarks-tooltip-simple" 
                                        data-remarks="${escapedRemarks}" 
                                        title="${escapedRemarks}"
                                        style="cursor: help; text-decoration: underline dotted;">
                                        ${escapedDisplayText}
                                    </span>`;
                        }
                    },
                    {
                        title: "Actions",
                        field: "actions",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            return `<button class="btn btn-sm action-icon-btn edit-payment-btn" 
                                        data-id="${rowData.id}"
                                        data-vo-number="${rowData.vo_number}"
                                        data-supplier-id="${rowData.supplier_id || ''}"
                                        data-purchase-contract-id="${rowData.purchase_contract_id || ''}"
                                        data-amount="${rowData.amount || ''}"
                                        data-advance-amount="${rowData.advance_amount || ''}"
                                        data-remarks="${(rowData.remarks || '').replace(/"/g, '&quot;')}"
                                        data-image="${rowData.image || ''}"
                                        data-balance-image="${rowData.balance_image || ''}"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    ${rowData.is_archived
                                        ? `<button class="btn btn-sm action-icon-btn restore-payment-btn"
                                            data-id="${rowData.id}"
                                            title="Restore (also restores the purchase contract)">
                                            <i class="fas fa-undo"></i>
                                        </button>`
                                        : `<button class="btn btn-sm action-icon-btn archive-payment-btn"
                                            data-id="${rowData.id}"
                                            title="Archive (also archives the purchase contract)">
                                            <i class="fas fa-archive"></i>
                                        </button>`}`;
                        },
                        width: 120
                    },
                ]
            });

            // Does a row pass the current search + PO stage filters? (shared by the table filter and the badges)
            function rowMatches(row) {
                const keyword = document.getElementById("advance-amount-search").value.trim().toLowerCase();
                const stage = document.getElementById("stage-filter").value;

                if (mustardOnly && hasAdvImage(row)) return false;
                if (keyword) {
                    const hay = (String(row.vo_number || '') + ' ' + String(row.supplier_name || '')).toLowerCase();
                    if (hay.indexOf(keyword) === -1) return false;
                }
                if (stage) {
                    if (stage === 'none') return !row.po_stage;
                    return row.po_stage === stage;
                }
                return true;
            }

            // Totals badges: summed from the full data set using the same filter rules,
            // so they are correct on first load ("All Stages") and after every filter change.
            function updateTotalBadges(allData) {
                const source = Array.isArray(allData) ? allData : table.getData();
                // Totals are kept per currency (USD and RMB are never added together).
                const totals = {
                    USD: { amount: 0, advance: 0, balance: 0 },
                    RMB: { amount: 0, advance: 0, balance: 0 }
                };
                const matched = source.filter(rowMatches);
                document.getElementById('badge-rows').textContent = matched.length.toLocaleString('en-US');
                matched.forEach(function(r) {
                    const t = totals[r.currency === 'RMB' ? 'RMB' : 'USD'];
                    t.amount  += parseFloat(r.amount) || 0;
                    t.advance += parseFloat(r.advance_amount) || 0;
                    t.balance += parseFloat(r.balance) || 0;
                });
                // Always show USD; add the Yuan total only when RMB rows are in the current view.
                const hasRmb = matched.some(function(r) { return r.currency === 'RMB'; });
                const fmt = function(key) {
                    const f = function(cur) { return currencySymbol(cur) + ' ' + Math.round(totals[cur][key]).toLocaleString('en-US'); };
                    return hasRmb ? f('USD') + '  |  ' + f('RMB') : f('USD');
                };
                document.getElementById('badge-amount').textContent = fmt('amount');
                document.getElementById('badge-advance').textContent = fmt('advance');
                document.getElementById('badge-balance').textContent = fmt('balance');
            }
            table.on("dataLoaded", function(data) { updateTotalBadges(data); });
            table.on("tableBuilt", function() { updateTotalBadges(); });
            table.on("rowDeleted", function() { updateTotalBadges(); });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            // Combined filter: search keyword AND stage.
            function applyFilters() {
                table.setFilter(rowMatches);
                updateTotalBadges();
            }
            document.getElementById("advance-amount-search").addEventListener("input", applyFilters);
            document.getElementById("stage-filter").addEventListener("change", applyFilters);
            // PO stage changed in a row (the PO is shared with Purchase Contracts): update every row of that PO.
            document.addEventListener("po-stage-changed", function(e) {
                table.getRows().forEach(function(r) {
                    if (String(r.getData().purchase_contract_id) === String(e.detail.poId)) {
                        r.update({ po_stage: e.detail.stage });
                    }
                });
                if (document.getElementById("stage-filter").value) applyFilters();
            });
            document.getElementById("mustard-filter-btn").addEventListener("click", function() {
                mustardOnly = !mustardOnly;
                this.classList.toggle("active", mustardOnly);
                applyFilters();
            });

            $('#delete-selected-btn').on('click', function() {
                const selectedData = table.getSelectedData();

                if (selectedData.length === 0) {
                    alert('Please select at least one record to delete.');
                    return;
                }

                if (!confirm(`Are you sure you want to delete ${selectedData.length} selected records?`)) {
                    return;
                }

                const ids = selectedData.map(row => row.id);

                $.ajax({
                    url: '/advance-payments/delete',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ids: ids
                    },
                    success: function(response) {
                        table.deleteRow(ids);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            const purchaseSelect = document.getElementById("purchaseContractSelect");
            const totalAmountInput = document.getElementById("tota_amount");
            const advanceInput = document.getElementById("advance_amount");
            const balanceInput = document.getElementById("balanceInput");

            purchaseSelect.addEventListener("change", function() {
                let selectedOption = this.options[this.selectedIndex];

                let amount = parseFloat(selectedOption.getAttribute("data-amount")) || 0;
                let advance = parseFloat(selectedOption.getAttribute("data-advance")) || 0;
                let balance = amount - advance;

                const supplierId = selectedOption.getAttribute("data-supplier");
                if (supplierId) document.getElementById("supplierSelect").value = supplierId;

                totalAmountInput.value = amount;
                advanceInput.value = advance;
                balanceInput.value = balance;
            });


            imageHover();
            function imageHover(){
                const imagePreviewPopup = document.createElement('div');
                imagePreviewPopup.id = 'image-preview-popup';
                imagePreviewPopup.style.display = 'none';
                imagePreviewPopup.style.position = 'fixed'; // fixed use kiya
                imagePreviewPopup.style.zIndex = '99999';
                imagePreviewPopup.style.border = '1px solid #ccc';
                imagePreviewPopup.style.background = '#fff';
                imagePreviewPopup.style.padding = '5px';
                imagePreviewPopup.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
                imagePreviewPopup.style.top = '50%';   // vertical center
                imagePreviewPopup.style.left = '50%';  // horizontal center
                imagePreviewPopup.style.transform = 'translate(-50%, -50%)'; // perfect center

                const previewImg = document.createElement('img');
                previewImg.style.height = '250px';
                previewImg.id = 'preview-popup-img';

                imagePreviewPopup.appendChild(previewImg);
                document.body.appendChild(imagePreviewPopup);

                document.addEventListener('mouseover', function (e) {
                    if (e.target.classList.contains('hover-thumbnail')) {
                        const imgUrl = e.target.getAttribute('data-preview-url');
                        previewImg.src = imgUrl;
                        imagePreviewPopup.style.display = 'block';
                    }
                });

                document.addEventListener('mouseout', function (e) {
                    if (e.target.classList.contains('hover-thumbnail')) {
                        imagePreviewPopup.style.display = 'none';
                    }
                });
            }

            // Paste a screenshot/snippet into the box -> becomes the Payment Image file
            function setBoxPreview(box, file) {
                const img = box.querySelector('.paste-box-preview');
                const hint = box.querySelector('.paste-box-hint');
                if (file) {
                    img.src = URL.createObjectURL(file);
                    img.classList.remove('d-none');
                    hint.classList.add('d-none');
                } else {
                    img.removeAttribute('src');
                    img.classList.add('d-none');
                    hint.classList.remove('d-none');
                }
            }
            document.querySelectorAll('.paste-box').forEach(function(box) {
                box.addEventListener('paste', function(e) {
                    const items = (e.clipboardData && e.clipboardData.items) || [];
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') === 0) {
                            const blob = items[i].getAsFile();
                            if (!blob) continue;
                            const ext = blob.type === 'image/jpeg' ? 'jpg' : 'png';
                            const file = new File([blob], 'pasted-snippet-' + Date.now() + '.' + ext, { type: blob.type });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            document.querySelector(box.getAttribute('data-target')).files = dt.files;
                            setBoxPreview(box, file);
                            e.preventDefault();
                            return;
                        }
                    }
                    alert('No image found in the clipboard. Copy a screenshot first.');
                    e.preventDefault();
                });
                // Choosing a file normally clears the pasted preview
                document.querySelector(box.getAttribute('data-target')).addEventListener('change', function() {
                    setBoxPreview(box, this.files && this.files[0] ? this.files[0] : null);
                });
            });
            // Reset previews when a modal closes
            ['createPaymentModal', 'editPaymentModal'].forEach(function(id) {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('hidden.bs.modal', function() {
                    el.querySelectorAll('.paste-box').forEach(function(box) { setBoxPreview(box, null); });
                });
            });

            // Bank dot -> read-only bank accounts of the supplier
            $(document).on('click', '.bank-view-btn', function() {
                const supplierId = $(this).data('supplier-id');
                const supplierName = $(this).data('supplier-name') || '';
                const esc = function(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };
                const labels = [
                    ['supplier_name', 'Beneficiary'], ['nick_name', 'Nick Name'], ['company_name', 'Company'],
                    ['swift', 'SWIFT'], ['address', 'Address'], ['city', 'City'], ['province', 'Province'],
                    ['country', 'Country'], ['account_number', 'Account No.'], ['acc_type', 'Account Type']
                ];

                $('#bankViewSupplierName').text(supplierName);
                $('#bankViewBody').html('<div class="text-center text-muted py-4">Loading...</div>');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('bankViewModal')).show();

                $.getJSON('/supplier/' + supplierId + '/bank-accounts')
                    .done(function(res) {
                        const accounts = (res && res.accounts) || [];
                        if (!accounts.length) {
                            $('#bankViewBody').html('<div class="text-center text-muted py-4"><i class="fas fa-university me-1"></i> No bank details on file</div>');
                            return;
                        }
                        const html = accounts.map(function(acc, i) {
                            const fields = labels.filter(function(l) { return acc[l[0]]; }).map(function(l) {
                                return '<div class="col-md-6"><div class="small text-muted">' + l[1] + '</div><div class="fw-semibold">' + esc(acc[l[0]]) + '</div></div>';
                            }).join('');
                            return '<div class="border rounded-3 p-3 mb-3"><div class="fw-bold mb-2">Account ' + (i + 1) + '</div><div class="row g-2">' + fields + '</div></div>';
                        }).join('');
                        $('#bankViewBody').html(html);
                    })
                    .fail(function() {
                        $('#bankViewBody').html('<div class="text-center text-danger py-4">Could not load bank details.</div>');
                    });
            });

            // Archive a row (red archive icon)
            $(document).on('click', '.archive-payment-btn', function() {
                const id = $(this).data('id');
                if (!confirm('Archive this payment voucher? Its purchase contract will be archived too.')) return;

                $.ajax({
                    url: '/advance-payments/archive',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id
                    },
                    success: function() {
                        reloadPayments();
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Could not archive this row.');
                    }
                });
            });

            // Restore an archived row (also restores its purchase contract)
            $(document).on('click', '.restore-payment-btn', function() {
                const id = $(this).data('id');
                if (!confirm('Restore this payment voucher? Its purchase contract will be restored too.')) return;

                $.ajax({
                    url: '/advance-payments/restore',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id
                    },
                    success: function() {
                        reloadPayments();
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Could not restore this row.');
                    }
                });
            });

            // Reload rows from the server for the selected Status (Active / Archived / All)
            function reloadPayments() {
                table.setData("/advance-and-payments/data", { filter: document.getElementById("archive-filter").value });
            }
            document.getElementById("archive-filter").addEventListener("change", reloadPayments);

            // Handle edit button click
            $(document).on('click', '.edit-payment-btn', function() {
                const btn = $(this);
                const paymentId = btn.data('id');
                const voNumber = btn.data('vo-number');
                const supplierId = btn.data('supplier-id');
                const purchaseContractId = btn.data('purchase-contract-id');
                const amount = btn.data('amount');
                const advanceAmount = btn.data('advance-amount');
                const remarks = btn.data('remarks');
                const image = btn.data('image');
                const balanceImage = btn.data('balance-image');

                // Populate edit modal
                $('#edit_payment_id').val(paymentId);
                $('#edit_vo_number').val(voNumber);
                $('#edit_supplier_id').val(supplierId).trigger('change');
                $('#edit_purchase_contract_id').val(purchaseContractId).trigger('change');
                $('#edit_amount').val(amount);
                $('#edit_advance_amount').val(advanceAmount);
                $('#edit_balance').val((parseFloat(amount) || 0) - (parseFloat(advanceAmount) || 0));
                $('#edit_remarks').val(remarks || '');

                // Show image preview if exists
                const imagePreview = $('#edit_image_preview');
                if (image) {
                    imagePreview.html(`<img src="${image}" style="max-height: 100px; border: 1px solid #ddd; border-radius: 4px;" alt="Current Image">`);
                } else {
                    imagePreview.html('');
                }

                const balanceImagePreview = $('#edit_balance_image_preview');
                if (balanceImage) {
                    balanceImagePreview.html(`<img src="${balanceImage}" style="max-height: 100px; border: 1px solid #ddd; border-radius: 4px;" alt="Current Balance Image">`);
                } else {
                    balanceImagePreview.html('');
                }

                // Open edit modal
                $('#editPaymentModal').modal('show');
            });

            // Handle purchase contract change in edit modal
            $('#edit_purchase_contract_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const amount = parseFloat(selectedOption.data('amount')) || 0;
                const advance = parseFloat(selectedOption.data('advance')) || 0;
                const balance = amount - advance;

                const poSupplier = selectedOption.data('supplier');
                if (poSupplier) $('#edit_supplier_id').val(String(poSupplier));

                $('#edit_amount').val(amount);
                $('#edit_advance_amount').val(advance);
                $('#edit_balance').val(balance);
            });

            // Handle edit form submission
            $('#editPaymentForm').on('submit', function(e) {
                e.preventDefault();
                const form = $(this);
                const formData = new FormData(form[0]);

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#editPaymentModal').modal('hide');
                        // Refresh table data
                        table.replaceData("/advance-and-payments/data", { filter: document.getElementById("archive-filter").value });
                        // Show success message
                        alert('Payment updated successfully!');
                    },
                    error: function(xhr) {
                        console.error('Error updating payment:', xhr.responseText);
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            alert('Error: ' + xhr.responseJSON.message);
                        } else {
                            alert('Error updating payment. Please try again.');
                        }
                    }
                });
            });

        });
    </script>


@endsection
