@extends('layouts.vertical', ['title' => 'Purchase'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
@include('layouts.shared.page-title', ['page_title' => 'Purchase', 'sub_title' => 'Purchase'])

@if (Session::has('flash_message'))
    <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
        style="background-color: #169e28 !important; color: #fff !important;">
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
                        <input type="text" id="purchase-search" class="form-control border-start-0" placeholder="Search VO Number, Supplier name...">
                    </div>
                    <div class="input-group" style="max-width: 320px;">
                        <input type="date" id="po-date-filter" class="form-control" placeholder="Filter by Purchase Date">
                    </div>
                    <button class="btn btn-danger d-none" id="delete-selected-btn">
                        <i class="fas fa-trash-alt me-1"></i> Delete Selected
                    </button>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createPurchaseModal">
                            <i class="fas fa-plus-circle me-1"></i> Create Purchase
                        </button>
                    </div>
                </div>
                <div id="purchase-table"></div>
            </div>
        </div>
    </div>
</div>

{{-- purchase add modal --}}
<div class="modal fade" id="createPurchaseModal" tabindex="-1" aria-labelledby="createPurchaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createPurchaseModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Create Purchase
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="purchaseForm" method="POST" action="{{ route('purchase.store') }}" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <input type="hidden" id="purchase_id" name="purchase_id">
                <div class="modal-body">
                    {{-- Purchase Header Section --}}
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Voucher Number</label>
                            <input type="text" class="form-control" name="vo_number" value="{{ $voNumber }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select" name="supplier" id="supplierSelect" required>
                                <option value="" disabled selected>Select Supplier</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Warehouse <span class="text-danger">*</span></label>
                            <select class="form-select" name="warehouse" required>
                                <option value="" disabled selected>Select Warehouse</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">

                    {{-- Product Table --}}
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle text-nowrap" id="productTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%;">Parent</th>
                                    <th style="width: 30%;">SKU</th>
                                    <th style="width: 10%;">QTY</th>
                                    <th style="width: 12%;">Rate</th>
                                    <th style="width: 13%;">Amount</th>
                                    <th style="width: 15%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="productRowsWrapper">
                                <tr class="default-row">
                                    <td><input type="text" class="form-control" name="parent[]" readonly></td>
                                    <td>
                                        <select class="form-control sku-select" name="sku[]" required style="width: 100%;">
                                            <option value="">Search SKU...</option>
                                        </select>
                                    </td>
                                    <td><input type="number" class="form-control qty-input" name="qty[]" min="0"></td>
                                    <td><input type="number" class="form-control rate-input" name="rate[]" min="0" step="any"></td>
                                    <td><input type="number" class="form-control amount-input" name="amount[]" readonly></td>
                                    <td class="text-center text-muted">—</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>Total</th>
                                    <td colspan="1"></td>
                                    <th><span id="qtyTotal">0</span></th>
                                    <td></td>
                                    <th><span id="amountTotal">0.00</span></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="addProductRowBtn">
                            <i class="fas fa-plus-circle me-1"></i> Add Purchase Row
                        </button>
                    </div>

                    <div class="row g-2 mt-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" name="description" rows="2" placeholder="Enter description or notes..."></textarea>
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

{{-- purchase items modal --}}
<!-- Items Modal -->
<div class="modal fade" id="itemsModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content shadow">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Voucher Item Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center" id="modal-items-table">
            <thead class="table-light">
              <tr>
                <th>S.No</th>
                <th>Parent</th>
                <th>SKU</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = new Tabulator("#purchase-table", {
        ajaxURL: "/purchase-data/list",
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
            { 
                title: "Voucher Number",
                field:"vo_number",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            { 
                title: "Purchase Date",
                field:"purchase_date",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            { 
                title: "Supplier Name",
                field:"supplier_name",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            { 
                title: "Warehouse Name",
                field:"warehouse_name",
                hozAlign: "center",
                headerHozAlign: "center",
            },
            {
                title: "Items",
                formatter: function() {
                    return "<button class='btn btn-sm btn-primary'><i class='fas fa-eye me-1'></i>View</button>";
                },
                cellClick: function(e, cell) {
                    let rowData = cell.getRow().getData();
                    showItemsModal(rowData.items);
                },
                hozAlign: "center",
                headerHozAlign: "center",
            },
            {
                title: "Action",
                hozAlign: "center",
                formatter: function(cell){
                    return `
                        <button class="btn btn-sm btn-info edit-btn">
                            <i class="fa fa-edit"></i>
                        </button>
                    `;
                },
                cellClick: function(e, cell){
                    let rowData = cell.getRow().getData();
                    openEditModal(rowData);
                }
            }
        ]
    });
    
    table.on("rowSelectionChanged", function(data, rows){
        if(data.length > 0){
            $('#delete-selected-btn').removeClass('d-none');
        } else {
            $('#delete-selected-btn').addClass('d-none');
        }
    });

    document.getElementById("purchase-search").addEventListener("input", function (e) {
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
            url: '/purchase/delete',
            type: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                ids: ids
            },
            success: function(response) {
                table.deleteRow(ids);
                updateApprovalPendingCount();
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            }
        });
    });

    // Date filter
    document.getElementById("po-date-filter").addEventListener("change", function (e) {
        const selectedDate = e.target.value;
        if (selectedDate) {
            table.setFilter("purchase_date", "=", selectedDate);
        } else {
            table.clearFilter(true); // clear all filters
        }
    });

    const productTableBody = document.getElementById('productRowsWrapper');
    const addRowBtn = document.getElementById('addProductRowBtn');
    const qtyTotalEl = document.getElementById('qtyTotal');
    const amountTotalEl = document.getElementById('amountTotal');
    const supplierSelect = document.getElementById('supplierSelect');

    // Function to calculate amount for a row
    function calculateRowAmount(row) {
        const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
        const rate = parseFloat(row.querySelector('.rate-input')?.value) || 0;
        row.querySelector('.amount-input').value = (qty * rate).toFixed(2);
    }

    // Function to update total QTY and Amount
    function updateTotals() {
        let totalQty = 0;
        let totalAmount = 0;

        document.querySelectorAll('#productRowsWrapper tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
            const amount = parseFloat(row.querySelector('.amount-input')?.value) || 0;
            totalQty += qty;
            totalAmount += amount;
        });

        qtyTotalEl.textContent = totalQty;
        amountTotalEl.textContent = totalAmount.toFixed(2);
    }

    // Function to initialize Select2 for SKU dropdowns
    function initializeSkuSelect(element) {
        $(element).select2({
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
            dropdownParent: $('#createPurchaseModal')
        });

        // When SKU is selected, fetch parent
        $(element).on('select2:select', function (e) {
            const sku = e.params.data.id;
            const parent = e.params.data.parent || '';
            const row = $(this).closest('tr');
            row.find('input[name="parent[]"]').val(parent);
        });
    }

    // Function to create new product row
    function createProductRow() {
        return `
            <tr>
                <td><input type="text" class="form-control" name="parent[]" readonly></td>
                <td>
                    <select class="form-control sku-select" name="sku[]" required style="width: 100%;">
                        <option value="">Search SKU...</option>
                    </select>
                </td>
                <td><input type="number" class="form-control qty-input" name="qty[]" min="0"></td>
                <td><input type="number" class="form-control rate-input" name="rate[]" min="0" step="any"></td>
                <td><input type="number" class="form-control amount-input" name="amount[]" readonly></td>
                <td class="text-center">
                    <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor: pointer;"></i>
                </td>
            </tr>
        `;
    }

    // Function to create a row from item object (for supplier load)
    function createRowFromItem(item) {
        return `
            <tr>
                <td><input type="text" class="form-control" name="parent[]" value="${item.parent ?? ''}" readonly></td>
                <td>
                    <select class="form-control sku-select" name="sku[]" required style="width: 100%;">
                        <option value="${item.sku ?? ''}" selected>${item.sku ?? ''}</option>
                    </select>
                </td>
                <td><input type="number" class="form-control qty-input" name="qty[]" value="${item.qty ?? 0}" min="0"></td>
                <td><input type="number" class="form-control rate-input" name="rate[]" value="${item.price ?? 0}" min="0" step="any"></td>
                <td><input type="number" class="form-control amount-input" name="amount[]" value="${(item.qty * item.price).toFixed(2)}" readonly></td>
                <td class="text-center">
                    <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor: pointer;"></i>
                </td>
            </tr>
        `;
    }

    // On supplier change, fetch existing items
    supplierSelect.addEventListener('change', function () {
        const supplierId = this.value;
        if (!supplierId) return;

        fetch(`/purchase-orders/items-by-supplier/${supplierId}`)
            .then(response => response.json())
            .then(data => {
                productTableBody.innerHTML = ''; // Clear existing rows

                if (data.length === 0) {
                    productTableBody.innerHTML = createProductRow();
                    initializeSkuSelect($('#productRowsWrapper .sku-select').last());
                } else {
                    data.forEach(item => {
                        productTableBody.insertAdjacentHTML('beforeend', createRowFromItem(item));
                        const newRow = productTableBody.lastElementChild;
                        initializeSkuSelect($(newRow).find('.sku-select'));
                        calculateRowAmount(newRow);
                    });
                    updateTotals(); 
                }
                updateTotals();
            })
            .catch(err => {
                console.error('Error loading supplier items:', err);
                productTableBody.innerHTML = createProductRow(); // fallback
                initializeSkuSelect($('#productRowsWrapper .sku-select').last());
            });
    });

    // Auto calculate on input
    productTableBody.addEventListener('input', function (e) {
        const target = e.target;
        const row = target.closest('tr');

        // Qty/Rate change: recalculate
        if (target.classList.contains('qty-input') || target.classList.contains('rate-input')) {
            calculateRowAmount(row);
            updateTotals();
        }
    });


    // Add new row
    addRowBtn.addEventListener('click', function () {
        const rowHTML = createProductRow();
        productTableBody.insertAdjacentHTML('beforeend', rowHTML);

        const newRow = productTableBody.lastElementChild;
        initializeSkuSelect($(newRow).find('.sku-select'));
        calculateRowAmount(newRow);   
        updateTotals();               
    });


    // Delete row (except default)
    productTableBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-product-row-btn')) {
            const row = e.target.closest('tr');
            if (!row.classList.contains('default-row')) {
                row.remove();
                updateTotals();
            }
        }
    });

    function showItemsModal(itemsJson) {
        const items = JSON.parse(itemsJson);
        const tbody = document.querySelector('#modal-items-table tbody');
        tbody.innerHTML = '';

        let fetchPromises = items.map((item, index) => {
            return fetch(`/product-master/get-parent/${item.sku}`)
                .then(res => res.json())
                .then(data => {
                    const parent = data.parent ?? '';
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${parent}</td>
                            <td>${item.sku}</td>
                            <td>${item.qty ?? ''}</td>
                            <td>${Number(item.price || 0).toFixed(2)}</td>
                            <td>${Number(item.amount || 0).toFixed(2)}</td>
                        </tr>
                    `);
                })
                .catch(() => {
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td>${index + 1}</td>
                            <td></td>
                            <td>${item.sku}</td>
                            <td>${item.qty ?? ''}</td>
                            <td>${Number(item.price || 0).toFixed(2)}</td>
                            <td>${Number(item.amount || 0).toFixed(2)}</td>
                        </tr>
                    `);
                });
        });

        Promise.all(fetchPromises).then(() => {
            $('#itemsModal').modal('show');
        });                                          
    }


    function openEditModal(rowData) {

        //-- Fill header section
        $("#purchase_id").val(rowData.id ?? "");

        // supplier select
        $('select[name="supplier"]').val(rowData.supplier_id ?? "").trigger("change");

        // warehouse select
        $('select[name="warehouse"]').val(rowData.warehouse_id ?? "").trigger("change");

        $('input[name="vo_number"]').val(rowData.vo_number ?? "");
        $('input[name="purchase_date"]').val(rowData.purchase_date ?? "");
        $('textarea[name="description"]').val(rowData.description ?? "");

        //-- Parse ITEMS
        let items = rowData.items;

        try {
            if (typeof items === "string") {
                items = JSON.parse(items);
            }
        } catch (err) {
            console.error("Items JSON parsing error:", err);
            items = [];
        }

        if (!Array.isArray(items)) {
            items = [];
        }

        console.log("Final items:", items);

        $("#productRowsWrapper").html("");

        //-- If items available → add rows
        if (items.length > 0) {

            items.forEach(item => {
                const parent = item.parent ?? item.parent_name ?? item.parentSku ?? "";

                const sku     = item.sku ?? "";
                const qty     = parseFloat(item.qty ?? 0) || 0;
                const rate    = parseFloat(item.price ?? item.rate ?? 0) || 0;
                const amount  = parseFloat(item.amount ?? qty * rate) || 0;

                $("#productRowsWrapper").append(`
                    <tr>
                        <td>
                            <input type="text" class="form-control" name="parent[]" value="${parent}" readonly>
                        </td>
                        <td>
                            <select class="form-control sku-select" name="sku[]" required style="width: 100%;">
                                <option value="${sku}" selected>${sku}</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control qty-input" name="qty[]" value="${qty}">
                        </td>
                        <td>
                            <input type="number" class="form-control rate-input" name="rate[]" value="${rate}">
                        </td>
                        <td>
                            <input type="number" class="form-control amount-input" name="amount[]" value="${amount.toFixed(2)}" readonly>
                        </td>

                        <td class="text-center">
                            <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor:pointer;"></i>
                        </td>
                    </tr>
                `);
                initializeSkuSelect($("#productRowsWrapper tr:last-child .sku-select"));
            });

        } else {
            $("#productRowsWrapper").append(createProductRow());
        }

        updateTotals();
        $("#createPurchaseModal").modal("show");
    }

    // Initialize Select2 on default row
    initializeSkuSelect($('.sku-select'));

    // Initial totals
    updateTotals();
});
</script>
@endsection
