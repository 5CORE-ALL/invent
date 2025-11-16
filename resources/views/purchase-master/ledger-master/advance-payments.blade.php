@extends('layouts.vertical', ['title' => 'Advance & Payments'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
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
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="advance-amount-search" class="form-control border-start-0" placeholder="Search VO Number, Supplier name...">
                    </div>
                    <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createPaymentModal">
                            <i class="fas fa-plus-circle me-1"></i> Create Payment Voucher
                        </button>
                    </div>
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
                        {{-- Voucher Number --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Voucher Number</label>
                            <input type="text" class="form-control" name="vo_number" value="{{ $voNumber }}" readonly>
                        </div>

                        {{-- Supplier --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select" name="supplier_id" required>
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
                                    <option value="{{ $order->id }}" data-amount="{{ $order->total_amount }}" data-advance="{{ $order->advance_amount }}">{{ $order->po_number }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dr Amount --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Amount</label>
                            <input type="number" name="amount" id="tota_amount" class="form-control" readonly>
                        </div>

                        {{-- Advance Amount --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Advance Amount</label>
                            <input type="number" name="advance_amount" id="advance_amount" class="form-control" readonly>
                        </div>

                        {{-- Balance (Auto calc) --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Balance</label>
                            <input type="number" id="balanceInput" class="form-control" readonly>
                        </div>

                        {{-- Payment Image --}}
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Payment Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>

                        {{-- Remarks --}}
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <i class="fas fa-save me-1"></i> Save
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

            const table = new Tabulator("#advance-payment-table", {
                ajaxURL: "/advance-and-payments/data",
                ajaxConfig: "GET",
                layout: "fitColumns",
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
                    },
                    { title: "VO Number", field: "vo_number", hozAlign: "center" },
                    { title: "Purchase Contract", field:"purchase_contract", hozAlign: "center" },
                    { title: "Supplier Name", field:"supplier_name", hozAlign: "center" },
                    { title: "Amount", field:"amount", hozAlign: "center" },
                    { title: "Advance Amount", field:"advance_amount", hozAlign: "center" },
                    { title: "Balance", field:"balance", hozAlign: "center", headerHozAlign: "center" },
                    { 
                        title: "Image", 
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
                    { title: "Remarks", field:"remarks", hozAlign: "center" },
                ]
            });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            document.getElementById("advance-amount-search").addEventListener("input", function (e) {
                const keyword = e.target.value.toLowerCase();

                table.setFilter([
                    [
                        { field: "vo_number", type: "like", value: keyword },
                        { field: "supplier_name", type: "like", value: keyword },
                    ]
                ]);
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

        });
    </script>


@endsection