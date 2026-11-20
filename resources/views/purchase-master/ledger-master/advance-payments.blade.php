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

    /* PO number link */
    #advance-payment-table a.po-link { color: #0d6efd; font-weight: 700; text-decoration: none; }
    #advance-payment-table a.po-link:hover { text-decoration: underline; }

    /* Actions column: icon-only buttons, no background */
    .action-icon-btn { background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 2px 6px; font-size: 1rem; }
    .action-icon-btn.edit-payment-btn { color: #3fbfc4 !important; }
    .action-icon-btn.archive-payment-btn { color: #dc3545 !important; }
    .action-icon-btn:hover { background: rgba(0,0,0,0.06) !important; }
    /* Stage markers (same look as /forecast.analysis) */
    .stage-dot-cell { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 3px; min-height: 36px; }
    .stage-dot-cell .stage-status-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(0,0,0,0.12); }
    .stage-dot-cell .stage-transit-icon { font-size: 0.95rem; line-height: 1; display: inline-block; }
    .stage-dot-cell .stage-mip-icon { font-size: 0.9rem; line-height: 1; color: #2563eb; }
    .stage-empty-dash { color: #94a3b8; font-weight: 700; }
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
                    <select id="stage-filter" class="form-select form-select-sm" style="max-width: 160px;" aria-label="Stage filter" autocomplete="off">
                        <option value="" selected>All Stages</option>
                        <option value="to_order_analysis">Order</option>
                        <option value="mip">MIP</option>
                        <option value="r2s">R2S</option>
                        <option value="transit">Transit</option>
                        <option value="all_good">All Good</option>
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

<div id="image-preview-popup" 
     style="display:none; position:absolute; z-index:99999; border:1px solid #ccc; background:#fff; padding:5px; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
    <img src="" style="height:150px;" id="preview-popup-img">
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const roundFormatter = function(cell) {
                const num = parseFloat(cell.getValue());
                return isNaN(num) ? '' : '$ ' + Math.round(num);
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
                                ? '<a href="/purchase-orders/' + rowData.purchase_contract_id + '" target="_blank" rel="noopener" class="po-link" title="Open Purchase Order">' + po + '</a>'
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
                    { title: "Amount", field:"amount", hozAlign: "center", formatter: roundFormatter },
                    { title: "Advance Amount", field:"advance_amount", hozAlign: "center", formatter: roundFormatter },
                    { title: "Balance", field:"balance", hozAlign: "center", headerHozAlign: "center", formatter: roundFormatter },
                    {
                        title: "Stage",
                        field: "stages",
                        hozAlign: "center",
                        headerHozAlign: "center",
                        headerSort: false,
                        width: 90,
                        formatter: function(cell) {
                            const stages = cell.getValue();
                            if (!Array.isArray(stages) || stages.length === 0) {
                                return '<span class="stage-empty-dash">-</span>';
                            }
                            const labels = {
                                appr_req: 'Appr Req', to_order_analysis: 'Order', mip: 'MIP',
                                r2s: 'R2S', transit: 'Transit', all_good: 'All Good'
                            };
                            const colors = {
                                appr_req: '#facc15', to_order_analysis: '#c2410c',
                                r2s: '#16a34a', all_good: '#0ea5e9'
                            };
                            const html = stages.map(function(s) {
                                const title = labels[s] || s;
                                if (s === 'transit') return '<span class="stage-transit-icon" title="' + title + '">🚢</span>';
                                if (s === 'mip') return '<i class="fas fa-hammer stage-mip-icon" title="' + title + '"></i>';
                                return '<span class="stage-status-dot" title="' + title + '" style="background-color:' + (colors[s] || '#94a3b8') + ';"></span>';
                            }).join('');
                            return '<div class="stage-dot-cell">' + html + '</div>';
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
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm action-icon-btn archive-payment-btn"
                                        data-id="${rowData.id}"
                                        title="Archive">
                                        <i class="fas fa-archive"></i>
                                    </button>`;
                        },
                        width: 120
                    },
                ]
            });

            // Does a row pass the current search + stage filters? (shared by the table filter and the badges)
            function rowMatches(row) {
                const keyword = document.getElementById("advance-amount-search").value.trim().toLowerCase();
                const stage = document.getElementById("stage-filter").value;

                if (keyword) {
                    const hay = (String(row.vo_number || '') + ' ' + String(row.supplier_name || '')).toLowerCase();
                    if (hay.indexOf(keyword) === -1) return false;
                }
                if (stage) {
                    const stages = Array.isArray(row.stages) ? row.stages : [];
                    if (stage === 'none') return stages.length === 0;
                    return stages.indexOf(stage) !== -1;
                }
                return true;
            }

            // Totals badges: summed from the full data set using the same filter rules,
            // so they are correct on first load ("All Stages") and after every filter change.
            function updateTotalBadges(allData) {
                const source = Array.isArray(allData) ? allData : table.getData();
                let amount = 0, advance = 0, balance = 0;
                const matched = source.filter(rowMatches);
                document.getElementById('badge-rows').textContent = matched.length.toLocaleString('en-US');
                matched.forEach(function(r) {
                    amount  += parseFloat(r.amount) || 0;
                    advance += parseFloat(r.advance_amount) || 0;
                    balance += parseFloat(r.balance) || 0;
                });
                const fmt = function(n) { return '$ ' + Math.round(n).toLocaleString('en-US'); };
                document.getElementById('badge-amount').textContent = fmt(amount);
                document.getElementById('badge-advance').textContent = fmt(advance);
                document.getElementById('badge-balance').textContent = fmt(balance);
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

            // Archive a row (red archive icon)
            $(document).on('click', '.archive-payment-btn', function() {
                const id = $(this).data('id');
                if (!confirm('Archive this payment voucher?')) return;

                $.ajax({
                    url: '/advance-payments/archive',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id
                    },
                    success: function() {
                        table.deleteRow(id);
                        updateTotalBadges();
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        alert('Could not archive this row.');
                    }
                });
            });

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
                        table.replaceData("/advance-and-payments/data");
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