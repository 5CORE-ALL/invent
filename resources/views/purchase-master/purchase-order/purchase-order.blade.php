@extends('layouts.vertical', ['title' => 'Purchase Contract', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #1a2942;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
    }

    .tabulator .tabulator-header .tabulator-col {
        text-align: center;
        background: #1a2942;
        border-right: 1px solid #ffffff;
        color: #fff;
        font-weight: bold;
        padding: 16px 16px;
        font-size: 1.08rem;
        letter-spacing: 0.02em;
        transition: background 0.2s;
    }

    .tabulator .tabulator-header .tabulator-col:hover {
        background: #e0eaff;
        color: #1a2942;
    }

    .tabulator-row {
        background-color: #fff !important;
        transition: background 0.18s;
    }

    .tabulator-row:nth-child(even) {
        background-color: #f8fafc !important;
    }

    .tabulator .tabulator-cell {
        text-align: center;
        padding: 14px 10px;
        border-right: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
        font-size: 1rem;
        font-weight: bolder;
        color: #000000;
        vertical-align: middle;
        max-width: 300px;
        transition: background 0.18s, color 0.18s;
    }


    .tabulator .tabulator-cell input,
    .tabulator .tabulator-cell select,
    .tabulator .tabulator-cell .form-select,
    .tabulator .tabulator-cell .form-control {
        font-weight: bold !important;
        color: #000000 !important;
    }

    .tabulator .tabulator-cell:focus {
        outline: 2px solid #2563eb;
        background: #e0eaff;
    }

    .tabulator-row:hover {
        background-color: #dbeafe !important;
    }

    .parent-row {
        background-color: #e0eaff !important;
        font-weight: 700;
    }

    #account-health-master .tabulator {
        border-radius: 18px;
        box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }

    .tabulator .tabulator-row .tabulator-cell:last-child,
    .tabulator .tabulator-header .tabulator-col:last-child {
        border-right: none;
    }

    .tabulator .tabulator-footer {
        background: #f4f7fa;
        border-top: 1px solid #e5e7eb;
        font-size: 1rem;
        color: #4b5563;
        padding: 5px;
        height: 100px;
    }

    .tabulator .tabulator-footer:hover {
        background: #e0eaff;
    }

    @media (max-width: 768px) {

        .tabulator .tabulator-header .tabulator-col,
        .tabulator .tabulator-cell {
            padding: 8px 2px;
            font-size: 0.95rem;
        }
    }

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
    /* Wrapper for photo image */
    .img-hover-photo {
        position: relative;
        display: inline-block;
    }

    .photo-img {
        width: 55px;
        height: 55px;
        object-fit: cover;
        border-radius: 4px;
        transition: transform 0.3s ease;
        z-index: 1;
    }

    /* Zoomed photo on hover */
    .zoomed-photo {
        position: absolute;
        display: none;
        z-index: 9999;
        top: -10px;
        left: 65px;
        background: #fff;
        border: 1px solid #ccc;
        padding: 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .zoomed-photo img {
        width: 200px;
        height: auto;
        object-fit: cover;
        border-radius: 4px;
    }

    .img-hover-photo:hover .zoomed-photo {
        display: block;
    }


    /* Wrapper for barcode image */
    .img-hover-barcode {
        position: relative;
        display: inline-block;
    }

    .barcode-img {
        width: 55px;
        height: 55px;
        object-fit: contain;
        border-radius: 4px;
        transition: transform 0.3s ease;
        z-index: 1;
    }

    /* Zoomed barcode (above image) */
    .zoomed-barcode {
        position: absolute;
        display: none;
        z-index: 9999;
        bottom: 65px;
        left: 50%;
        /* transform: translateX(-50%); */
        background: #fff;
        border: 1px solid #ccc;
        padding: 4px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .zoomed-barcode img {
        width: 160px;
        height: auto;
        object-fit: contain;
    }

    .img-hover-barcode:hover .zoomed-barcode {
        display: block;
    }

        
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Purchase Contract', 'sub_title' => 'Purchase Contract'])

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
                        <input type="text" id="purchase-order-search" class="form-control border-start-0" placeholder="Search PO Number, Supplier name...">
                    </div>
                    <div class="input-group" style="max-width: 320px;">
                        <input type="date" id="po-date-filter" class="form-control" placeholder="Filter by PO Date">
                    </div>
                    <button class="btn btn-sm btn-danger" id="delete-selected-btn" style="display:none;">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createPurchaseOrderModal">
                            <i class="fas fa-plus-circle me-1"></i> Create Purchase Contract
                        </button>
                    </div>
                </div>
                <div class="row" id="po-cards-container"></div>
                <div class="text-center mt-3" id="po-pagination"></div>

                <!-- Modal for Items -->
                <div class="modal fade" id="poItemsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Purchase Contract Items</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body" id="po-items-modal-content">
                                <!-- Filled dynamically -->
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- add purchase modal --}}
<div class="modal fade" id="createPurchaseOrderModal" tabindex="-1" aria-labelledby="createPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createPurchaseOrderModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Create Purchase Contract
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="purchaseOrderForm" method="POST" action="{{ route('purchase-orders.store') }}" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body">
                    {{-- PO Header Section --}}
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PO Number</label>
                            <input type="text" class="form-control" name="po_number" value="{{ $poNumber }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select" name="supplier" required>
                                <option value="" disabled selected>Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Advance Amount <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="advance_amount" placeholder="enter supplier advance amount" step="any">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PO Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="po_date" required>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Product Section --}}
                    <div>
                        <h5 class="fw-semibold mb-2 text-primary">
                            <i class="fas fa-boxes-stacked me-1"></i> Product Details
                        </h5>
                        <div id="productRowsWrapper">
                            <div class="row g-2 product-row border rounded p-2 mt-2 position-relative">
                                <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2 ">
                                    <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor: pointer; font-size: 1.2rem; margin-top:-10px;"></i>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">5Core SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="sku[]" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Supplier SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="supplier_sku[]" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Product Photo</label>
                                    <input type="file" class="form-control" name="photo[]" accept="image/*">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Barcode</label>
                                    <input type="file" class="form-control" name="barcode[]" accept="image/*">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Tech</label>
                                    <textarea class="form-control" name="tech[]"></textarea>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Qty</label>
                                    <input type="number" class="form-control" name="qty[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Currency</label>
                                    <select class="form-select" name="currency[]">
                                        <option value="USD">USD</option>
                                        <option value="RMB">RMB</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Price</label>
                                    <input type="number" class="form-control" name="price[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Price Type</label>
                                    <select class="form-select" name="price_type[]">
                                        <option value="EXW">EXW</option>
                                        <option value="FOB">FOB</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">NW /per pcs (KG)</label>
                                    <input type="number" class="form-control" name="nw[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">GW /per pcs (KG)</label>
                                    <input type="number" class="form-control" name="gw[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">CBM</label>
                                    <input type="number" class="form-control" name="cbm[]" step="any">
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addProductRowBtn">
                                <i class="fas fa-plus-circle me-1"></i> Add Product Row
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Purchase Order Modal --}}
<div class="modal fade" id="editPurchaseOrderModal" tabindex="-1" aria-labelledby="editPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="editPurchaseOrderModalLabel">
                    <i class="fas fa-edit me-2"></i> Edit Purchase Contract
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editPurchaseOrderForm" method="POST" action="" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="modal-body">
                    {{-- PO Header Section --}}
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PO Number</label>
                            <input type="text" class="form-control" name="po_number" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Supplier</label>
                            <select class="form-select" name="supplier" required>
                                <option value="" disabled>Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Advance Amount</label>
                            <input type="number" class="form-control" name="advance_amount" step="any">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PO Date</label>
                            <input type="date" class="form-control" name="po_date">
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Product Section --}}
                    <div>
                        <h5 class="fw-semibold mb-2 text-warning">
                            <i class="fas fa-boxes-stacked me-1"></i> Product Details
                        </h5>
                        <div id="editProductRowsWrapper"></div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-warning btn-sm" id="addEditProductRowBtn">
                                <i class="fas fa-plus-circle me-1"></i> Add Product Row
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    let currentPage = 1;
    const itemsPerPage = 12;
    let allPurchaseOrders = [];

    document.body.style.zoom = "90%";
    document.addEventListener("DOMContentLoaded", function () {
        getPurchaseOrderData();

        document.getElementById("purchase-order-search").addEventListener("input", applyFilters);
        document.getElementById("po-date-filter").addEventListener("change", applyFilters);
        document.addEventListener('click', function(e) {
            if (e.target.closest('.generate-pdf-btn')) {
                const orderId = e.target.closest('.generate-pdf-btn').dataset.orderId;
                window.location.href = `/purchase-order/${orderId}/generate-pdf`; //Open in same tab
            }
        });

        document.addEventListener("click", function(e) {
            
            if (e.target.closest(".edit-order-btn")) {

                let btn = e.target.closest(".edit-order-btn");
                let order = JSON.parse(btn.getAttribute("data-order"));
                let items = JSON.parse(btn.getAttribute("data-items"));
                
                //Set form action dynamically
                let form = document.getElementById("editPurchaseOrderForm");
                form.action = `/purchase-orders/${order.id}`;

                form.querySelector("[name='po_number']").value = order.po_number ?? "";
                
                // Supplier
                let supplierSelect = form.querySelector("[name='supplier']");
                if (supplierSelect) {
                    Array.from(supplierSelect.options).forEach(opt => {
                        opt.selected = (String(opt.value) === String(order.supplier_id));
                    });
                }


                // Advance Amount
                form.querySelector("[name='advance_amount']").value = order.advance_amount ?? 0;


                // PO Date
                form.querySelector("[name='po_date']").value = order.po_date ?? "";

                //Clear old product rows
                let wrapper = document.getElementById("editProductRowsWrapper");
                wrapper.innerHTML = "";

                //Render each item row
                items.forEach(item => {
                    wrapper.insertAdjacentHTML("beforeend", `
                        <div class="row g-2 product-row border rounded p-2 mt-2 position-relative">
                            <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2">
                                <i class="fas fa-trash-alt text-danger delete-product-row-btn" 
                                style="cursor:pointer; font-size:1.2rem; margin-top:-10px;"></i>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">5Core SKU</label>
                                <input type="text" class="form-control" name="sku[]" value="${item.sku ?? ''}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Supplier SKU</label>
                                <input type="text" class="form-control" name="supplier_sku[]" value="${item.supplier_sku ?? ''}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Product Photo</label>
                                <input type="file" class="form-control" name="photo[]" accept="image/*">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Barcode</label>
                                <input type="file" class="form-control" name="barcode[]" accept="image/*">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Tech</label>
                                <textarea class="form-control" name="tech[]">${item.tech ?? ''}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Qty</label>
                                <input type="number" class="form-control" name="qty[]" value="${item.qty ?? 0}" step="any">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Currency</label>
                                <select class="form-select" name="currency[]">
                                    <option value="USD" ${item.currency=="USD"?"selected":""}>USD</option>
                                    <option value="RMB" ${item.currency=="RMB"?"selected":""}>RMB</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Price</label>
                                <input type="number" class="form-control" name="price[]" value="${item.price ?? 0}" step="any">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Price Type</label>
                                <select class="form-select" name="price_type[]">
                                    <option value="EXW" ${item.price_type=="EXW"?"selected":""}>EXW</option>
                                    <option value="FOB" ${item.price_type=="FOB"?"selected":""}>FOB</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">NW /per pcs (KG)</label>
                                <input type="number" class="form-control" name="nw[]" value="${item.nw ?? 0}" step="any">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">GW /per pcs (KG)</label>
                                <input type="number" class="form-control" name="gw[]" value="${item.gw ?? 0}" step="any">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">CBM</label>
                                <input type="number" class="form-control" name="cbm[]" value="${item.cbm ?? 0}" step="any">
                            </div>
                        </div>
                    `);
                });

                //Finally show modal
                let editModal = new bootstrap.Modal(document.getElementById("editPurchaseOrderModal"));
                editModal.show();
            }
        });

        document.getElementById("addEditProductRowBtn").addEventListener("click", function() {
            let wrapper = document.getElementById("editProductRowsWrapper");

            wrapper.insertAdjacentHTML("beforeend", `
                <div class="row g-2 product-row border rounded p-2 mt-2 position-relative">
                    <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2">
                        <i class="fas fa-trash-alt text-danger delete-product-row-btn" 
                        style="cursor:pointer; font-size:1.2rem; margin-top:-10px;"></i>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold">5Core SKU</label>
                        <input type="text" class="form-control" name="sku[]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Supplier SKU</label>
                        <input type="text" class="form-control" name="supplier_sku[]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Product Photo</label>
                        <input type="file" class="form-control" name="photo[]" accept="image/*">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Barcode</label>
                        <input type="file" class="form-control" name="barcode[]" accept="image/*">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tech</label>
                        <textarea class="form-control" name="tech[]"></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Qty</label>
                        <input type="number" class="form-control" name="qty[]" value="0" step="any">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Currency</label>
                        <select class="form-select" name="currency[]">
                            <option value="USD" selected>USD</option>
                            <option value="RMB">RMB</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Price</label>
                        <input type="number" class="form-control" name="price[]" value="0" step="any">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Price Type</label>
                        <select class="form-select" name="price_type[]">
                            <option value="EXW" selected>EXW</option>
                            <option value="FOB">FOB</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">NW /per pcs (KG)</label>
                        <input type="number" class="form-control" name="nw[]" value="0" step="any">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">GW /per pcs (KG)</label>
                        <input type="number" class="form-control" name="gw[]" value="0" step="any">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">CBM</label>
                        <input type="number" class="form-control" name="cbm[]" value="0" step="any">
                    </div>
                </div>
            `);
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("delete-product-row-btn")) {
                const row = e.target.closest(".product-row");
                const rows = document.querySelectorAll(".product-row");
                if (rows.length > 1) {
                    row.remove();
                } else {
                    row.querySelectorAll("input, select, textarea").forEach(input => input.value = "");
                    alert("At least one row must remain.");
                }
            }
        });


    });

    function getPurchaseOrderData(){
        fetch('/purchase-orders/list')
        .then(res => res.json())
        .then(data => {
            allPurchaseOrders = data;
            renderPurchaseOrderCards();
            renderPaginationControls();
        });
    }

    function renderPurchaseOrderCards(data = allPurchaseOrders) {
        const container = document.getElementById("po-cards-container");
        container.innerHTML = "";

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const currentItems = data.slice(startIndex, endIndex);

        currentItems.forEach(order => {
            const card = document.createElement("div");
            card.className = "col-md-6 col-lg-3";

            const items = JSON.parse(order.items_json || '[]');

            card.innerHTML = `
                <div class="card shadow-sm border-1 rounded-3 h-80" style="border-color: #3BBFC2;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between">
                            <h6 class="fw-semibold text-primary mb-2">PO Number: ${order.po_number}</h6>
                            <input type="checkbox" class="order-checkbox" data-order-id="${order.id}"/>
                        </div>
                        <div class="small text-muted mb-1">
                            <strong>PO Date:</strong> ${order.po_date}
                        </div>
                        <div class="small text-muted mb-3">
                            <strong>Supplier:</strong> ${order.supplier_name}
                        </div>
                        <!-- Button Row -->
                        <div class="d-flex justify-content-between gap-2">
                            <button class="btn btn-sm btn-warning edit-order-btn" 
                                    data-order='${JSON.stringify(order)}'
                                    data-items='${JSON.stringify(items)}'
                                    title="Edit Order">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-primary view-items-btn" 
                                    data-items='${JSON.stringify(items)}' 
                                    title="View Items">
                                <i class="fas fa-box-open"></i>
                            </button>
                            <button class="btn btn-sm btn-info export-items-btn" 
                                data-order='${JSON.stringify(order)}'
                                data-items='${JSON.stringify(items)}'
                                title="Export Items">
                                <i class="fas fa-file-excel"></i>
                            </button>

                            <button class="btn btn-sm btn-success generate-pdf-btn" 
                                    data-order-id="${order.id}" 
                                    title="Generate PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(card);
        });

        attachItemModalListeners();
        attachExportExcelListeners();
    }

    function renderPaginationControls() {
        const totalPages = Math.ceil(allPurchaseOrders.length / itemsPerPage);
        const paginationContainer = document.getElementById("po-pagination");
        paginationContainer.innerHTML = "";

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement("button");
            btn.className = `btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-primary'} mx-1`;
            btn.innerText = i;
            btn.addEventListener("click", function () {
                currentPage = i;
                renderPurchaseOrderCards();
                renderPaginationControls();
            });
            paginationContainer.appendChild(btn);
        }
    }

    function attachItemModalListeners() {
        document.querySelectorAll(".view-items-btn").forEach(button => {
            button.addEventListener("click", function () {
                const items = JSON.parse(this.getAttribute("data-items"));

                let html = `
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-sm align-middle">
                            <thead class="table-light text-center align-middle">
                                <tr>
                                    <th>Photo</th>
                                    <th>5 Core SKU</th>
                                    <th>Barcode</th>
                                    <th>Supplier SKU</th>
                                    <th>Tech</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                    <th>Currency</th>
                                    <th>Price Type</th>
                                    <th>NW</th>
                                    <th>GW</th>
                                    <th>CBM</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                items.forEach(item => {
                    const total = (item.qty || 0) * (item.price || 0);
                    html += `
                        <tr class="text-center align-middle">
                            <td>
                                ${item.photo 
                                    ? `<div class="img-hover-photo">
                                            <img src="/storage/${item.photo}" alt="Photo" class="photo-img">
                                            <div class="zoomed-photo">
                                                <img src="/storage/${item.photo}" alt="Zoomed Photo">
                                            </div>
                                        </div>`
                                    : '<span class="text-muted">No Image</span>'}
                            </td>
                            <td><span class="fw-semibold">${item.sku || '-'}</span></td>
                            <td>
                                ${item.barcode 
                                    ? `<div class="img-hover-barcode">
                                            <img src="/storage/${item.barcode}" alt="Barcode" class="barcode-img">
                                            <div class="zoomed-barcode">
                                                <img src="/storage/${item.barcode}" alt="Zoomed Barcode">
                                            </div>
                                        </div>`
                                    : '<span class="text-muted">No Image</span>'}
                            </td>
                            <td>${item.supplier_sku || '-'}</td>
                            <td>${item.tech || '-'}</td>
                            <td><span class="badge bg-primary-subtle text-dark">${item.qty || 0}</span></td>
                            <td>${item.price || 0}</td>
                            <td><span class="fw-bold text-success">${total.toFixed(2)}</span></td>
                            <td>${item.currency || '-'}</td>
                            <td>${item.price_type || '-'}</td>
                            <td>${item.nw || '-'}</td>
                            <td>${item.gw || '-'}</td>
                            <td>${item.cbm || '-'}</td>

                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                document.getElementById("po-items-modal-content").innerHTML = html;
                new bootstrap.Modal(document.getElementById("poItemsModal")).show();
            });
        });
    }

    function attachExportExcelListeners() {
        document.querySelectorAll(".export-items-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const items = JSON.parse(this.getAttribute("data-items") || "[]");
                const order = JSON.parse(this.getAttribute("data-order") || "{}");

                exportItemsToExcelXLSX(items, order);
            });
        });
    }


    function exportItemsToExcelXLSX(items, order) {
        if (!items || !items.length) {
            alert("No items found");
            return;
        }

        const worksheet = XLSX.utils.json_to_sheet(items);

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Items");

        let fileName = "po_items.xlsx";
        if (order?.po_number) {
            fileName = `${order.po_number}.xlsx`;
        }

        XLSX.writeFile(workbook, fileName);
    }




    function applyFilters() {
        const searchValue = document.getElementById("purchase-order-search").value.toLowerCase();
        const selectedDate = document.getElementById("po-date-filter").value;

        currentPage = 1;

        const filtered = allPurchaseOrders.filter(order => {
            const matchesSearch = (
                (order.po_number && order.po_number.toLowerCase().includes(searchValue)) ||
                (order.supplier_name && order.supplier_name.toLowerCase().includes(searchValue))
            );

            const matchesDate = selectedDate ? order.po_date === selectedDate : true;

            return matchesSearch && matchesDate;
        });

        renderPurchaseOrderCards(filtered);
        renderPaginationControls(filtered);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const addBtn = document.getElementById('addProductRowBtn');
        const wrapper = document.getElementById('productRowsWrapper');

        addBtn.addEventListener('click', function () {
            const firstRow = wrapper.querySelector('.product-row');
            const clone = firstRow.cloneNode(true);

            // Clear all inputs and selects
            clone.querySelectorAll('input, select').forEach(input => {
                if (input.type === 'file') {
                    input.value = null;
                } else {
                    input.value = '';
                }
            });

            wrapper.appendChild(clone);
        });

        wrapper.addEventListener('click', function (e) {
            if (e.target.classList.contains('delete-product-row-btn')) {
                const row = e.target.closest('.product-row');
                if (wrapper.querySelectorAll('.product-row').length > 1) {
                    row.remove();
                } else {
                    alert("At least one row is required.");
                }
            }
        });

        // Listen for checkbox changes
        document.addEventListener("change", function (e) {
            if (e.target.classList.contains("order-checkbox")) {
                const anyChecked = document.querySelectorAll(".order-checkbox:checked").length > 0;
                document.getElementById("delete-selected-btn").style.display = anyChecked ? "inline-block" : "none";
            }
        });

        // Handle delete button click
        document.getElementById("delete-selected-btn").addEventListener("click", function () {
            const checkedBoxes = document.querySelectorAll(".order-checkbox:checked");
            if (checkedBoxes.length === 0) return;

            // if (!confirm(`Delete ${checkedBoxes.length} selected order(s)?`)) return;

            // Get all selected order IDs
            const ids = Array.from(checkedBoxes).map(cb => cb.dataset.orderId);

            // Remove cards from UI
            checkedBoxes.forEach(cb => cb.closest(".col-md-6").remove());

            // Send delete request to Laravel
            fetch("/purchase-orders/delete", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ ids })
            })
            .then(res => res.json())
            .then(data => {
                console.log("deleted successfully...");
            })
            .catch(err => console.error(err));
        });


    });

</script>
@endsection

