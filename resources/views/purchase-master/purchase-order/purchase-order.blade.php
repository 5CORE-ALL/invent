@extends('layouts.vertical', ['title' => 'Purchase Contract', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<style>
    #createPurchaseOrderModal .select2-container--bootstrap-5 .select2-selection,
    #editPurchaseOrderModal .select2-container--bootstrap-5 .select2-selection {
        min-height: 38px;
    }
    #createPurchaseOrderModal .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field,
    #editPurchaseOrderModal .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        padding: 6px 10px;
    }
    #createPurchaseOrderModal .po-sku-select2 + .select2-container,
    #editPurchaseOrderModal .po-sku-select2 + .select2-container {
        width: 100% !important;
        flex: 1 1 auto;
    }
    .po-summary-cell {
        max-width: 320px;
        vertical-align: middle !important;
    }
    .po-summary-sku-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
        justify-content: flex-start;
    }
    .po-summary-sku-tag {
        display: inline-block;
        max-width: 140px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #e8eaf6;
        border: 1px solid #c5cae9;
        color: #283593;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .po-summary-sku-more {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 999px;
        background: #eceff1;
        color: #546e7a;
        font-size: 0.7rem;
        font-weight: 700;
        cursor: default;
    }
    .po-supplier-badge {
        display: inline-block;
        max-width: 96px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }
    .po-sku-field-wrap {
        display: flex;
        align-items: stretch;
        gap: 6px;
    }
    .po-sku-field-wrap .po-sku-select-wrap {
        flex: 1 1 auto;
        min-width: 0;
    }
    .po-add-sku-btn {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #75c0c1;
        border: 1px solid #75c0c1;
        color: #fff;
        border-radius: 6px;
    }
    .po-add-sku-btn:hover {
        background: #5aa9aa;
        border-color: #5aa9aa;
        color: #fff;
    }
    /*
     * Modal stacking — keep above layout chrome (floating task z-index 1050).
     * Do NOT put zoom on body; it breaks Bootstrap backdrop size/z-index.
     */
    #createPurchaseOrderModal,
    #editPurchaseOrderModal,
    #poItemsModal {
        z-index: 2100 !important;
    }
    #addProductModal {
        z-index: 2200 !important;
    }
    body.modal-open > .modal-backdrop {
        z-index: 2090 !important;
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
    }
    body.po-nested-product-modal > .modal-backdrop:last-of-type {
        z-index: 2190 !important;
    }
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

    #po-table {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    #po-table thead th {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        color: #1a2942;
        font-weight: 700;
        border-color: #e5e7eb;
        padding: 12px 10px;
    }
    #po-table tbody tr:hover {
        background-color: #f0f9ff;
    }
    #po-table .btn-group-actions .btn {
        padding: 4px 8px;
    }

    .po-number-dot-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        min-width: 32px;
    }
    .po-number-dot-wrap::before {
        content: "";
        position: absolute;
        inset: -6px -10px -42px -10px;
        z-index: 0;
    }
    .po-status-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        display: inline-block;
        background: #22c55e;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 1;
        cursor: default;
    }
    .po-number-hover-card {
        display: none;
        position: absolute;
        z-index: 20;
        top: calc(100% + 2px);
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
        padding: 4px 8px;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        font-size: 0.875rem;
        font-weight: 600;
        color: #212529;
    }
    .po-number-dot-wrap:hover .po-number-hover-card {
        display: inline-flex;
    }
    .po-number-hover-card .po-copy-number-btn {
        line-height: 1;
        color: #2563eb;
        text-decoration: none !important;
    }
    .po-number-hover-card .po-copy-number-btn:hover {
        color: #1d4ed8;
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
                    @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'purchase_contract'])

                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="purchase-order-search" class="form-control border-start-0" placeholder="Search Supplier name...">
                    </div>
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-box-open text-muted"></i>
                        </span>
                        <input type="text" id="search-items" class="form-control border-start-0" placeholder="Search items...">
                    </div>
                    <div class="input-group" style="max-width: 200px;">
                        <label class="input-group-text bg-white border-end-0">Status</label>
                        <select id="archive-filter" class="form-select">
                            <option value="active">Active</option>
                            <option value="archived">Archived</option>
                            <option value="all">All</option>
                        </select>
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
                <div class="mb-2 d-flex flex-wrap gap-2 align-items-center" id="po-summary-badges">
                    <span class="badge bg-info fs-6 px-3 py-2">O Amount: <span id="badge-sum-oamount">0</span></span>
                    <span class="badge bg-primary fs-6 px-3 py-2">Advance: <span id="badge-sum-advance">0</span></span>
                    <span class="badge bg-success fs-6 px-3 py-2">Balance: <span id="badge-sum-balance">0</span></span>
                    <span class="badge bg-secondary fs-6 px-3 py-2">CBM: <span id="badge-sum-cbm">0</span></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" id="po-table">
                        <thead class="table-light text-center align-middle">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="select-all-po" title="Select all"></th>
                                <th class="po-sortable" data-sort="po_number" style="cursor: pointer; user-select: none;">PO <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="po_date" style="cursor: pointer; user-select: none;">Date <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="supplier_name" style="cursor: pointer; user-select: none;">Supplier <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="sku_list" style="cursor: pointer; user-select: none;">Summary <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="total_amount" style="cursor: pointer; user-select: none;">O Amount <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="advance_amount" style="cursor: pointer; user-select: none;">Advance <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="balance" style="cursor: pointer; user-select: none;">Balance <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th class="po-sortable" data-sort="total_cbm" style="cursor: pointer; user-select: none;">CBM <i class="fas fa-sort ms-1 sort-icon"></i></th>
                                <th style="min-width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="po-table-body">
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2 px-1" id="po-table-footer">
                    <div class="text-muted fw-medium" id="po-row-count">Showing 0 rows</div>
                    <div id="po-pagination" class="d-flex flex-wrap justify-content-end"></div>
                </div>

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
    <div class="modal-dialog modal-md modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createPurchaseOrderModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Create Purchase Contract
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="purchaseOrderForm" method="POST" action="{{ route('purchase-orders.store') }}" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12 d-none">
                            <label class="form-label fw-semibold">PO Number</label>
                            <input type="text" class="form-control" name="po_number" value="{{ $poNumber }}" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select po-supplier-select2" name="supplier" required data-placeholder="Search supplier…">
                                <option value=""></option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-none">
                            <label class="form-label fw-semibold">Advance Amount</label>
                            <input type="number" class="form-control" name="advance_amount" value="0" step="any">
                        </div>
                        <div class="col-12 d-none">
                            <label class="form-label fw-semibold">PO Date</label>
                            <input type="date" class="form-control" name="po_date" id="createPoDate" value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
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
    <div class="modal-dialog modal-md modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold" id="editPurchaseOrderModalLabel">
                    <i class="fas fa-edit me-2"></i> Edit Purchase Contract
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="editPurchaseOrderForm" method="POST" action="" autocomplete="off">
                @csrf
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12 d-none">
                            <label class="form-label fw-semibold">PO Number</label>
                            <input type="text" class="form-control" name="po_number" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Supplier</label>
                            <select class="form-select po-supplier-select2" name="supplier" required data-placeholder="Search supplier…">
                                <option value=""></option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">PO Date</label>
                            <input type="date" class="form-control" name="po_date">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const poSkuSearchUrl = @json(url('/purchase/search-sku'));

    function initPoSupplierSelect2($modal) {
        if (!$modal || !$modal.length) return;
        $modal.find('select.po-supplier-select2').each(function () {
            const $sel = $(this);
            if ($sel.hasClass('select2-hidden-accessible')) {
                return;
            }
            $sel.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $modal,
                placeholder: $sel.data('placeholder') || 'Search supplier…',
                allowClear: true,
                minimumResultsForSearch: 0,
            });
        });
    }

    function fillTechFromSkuSelect($sel) {
        const sku = String($sel.val() || '').trim();
        const row = $sel.closest('.product-row')[0];
        if (!row || !sku) return;
        const techField = row.querySelector('textarea[name="tech[]"]');
        if (!techField) return;
        // Only auto-fill when Tech is empty (do not overwrite manual edits).
        if ((techField.value || '').trim() !== '') return;
        fetch('/purchase-orders/tech-from-comparison?sku=' + encodeURIComponent(sku))
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success || !(data.tech || '').trim()) return;
                if ((techField.value || '').trim() !== '') return;
                techField.value = data.tech;
            })
            .catch(() => {});
    }

    function initPoSkuSelect2($modal, $scope) {
        if (!$modal || !$modal.length) return;
        const $root = $scope && $scope.length ? $scope : $modal;
        $root.find('select.po-sku-select2').each(function () {
            const $sel = $(this);
            if ($sel.hasClass('select2-hidden-accessible')) {
                return;
            }
            $sel.select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownParent: $modal,
                placeholder: $sel.data('placeholder') || 'Search 5Core SKU…',
                allowClear: true,
                // No free typing of custom SKUs — select from Product Master only.
                tags: false,
                minimumInputLength: 1,
                ajax: {
                    url: poSkuSearchUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                        };
                    },
                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        const results = data.results || data.items || [];
                        return {
                            results: results,
                            pagination: {
                                more: !!(data.pagination && data.pagination.more) || !!data.has_more,
                            },
                        };
                    },
                    cache: true,
                },
            });
            $sel.off('select2:select.poSkuTech').on('select2:select.poSkuTech', function () {
                fillTechFromSkuSelect($sel);
            });
        });
    }

    function poSkuSelectHtml(selectedSku) {
        const sku = String(selectedSku || '').trim();
        const selectedOpt = sku
            ? `<option value="${sku.replace(/"/g, '&quot;')}" selected>${sku.replace(/</g, '&lt;')}</option>`
            : '<option value=""></option>';
        return `
            <div class="po-sku-field-wrap">
                <div class="po-sku-select-wrap">
                    <select class="form-select po-sku-select2" name="sku[]" data-placeholder="Search 5Core SKU…">
                        ${selectedOpt}
                    </select>
                </div>
                <button type="button" class="btn po-add-sku-btn" title="Add product (Product Master)" aria-label="Add product">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        `;
    }

    $(function () {
        ['#createPurchaseOrderModal', '#editPurchaseOrderModal'].forEach(function (id) {
            const $modal = $(id);
            $modal.on('shown.bs.modal', function () {
                setTimeout(function () {
                    initPoSupplierSelect2($modal);
                    initPoSkuSelect2($modal);
                }, 50);
            });
        });

        // Autogenerate PO date (today) whenever Create modal opens.
        $('#createPurchaseOrderModal').on('show.bs.modal', function () {
            const el = document.getElementById('createPoDate');
            if (!el) return;
            const d = new Date();
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            el.value = yyyy + '-' + mm + '-' + dd;
        });

        // Product Master "+" next to 5Core SKU — same modal / store as Product Master page.
        let poAddSkuTargetSelect = null;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        function resetPoAddProductForm() {
            const form = document.getElementById('addProductForm');
            if (form) form.reset();
            document.getElementById('imagePreview') && (document.getElementById('imagePreview').innerHTML = '');
            document.getElementById('form-errors') && (document.getElementById('form-errors').innerHTML = '');
            document.querySelectorAll('#addProductForm .is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            document.querySelectorAll('#addProductForm .invalid-feedback').forEach((el) => { el.textContent = ''; });
            const saveBtn = document.getElementById('saveProductBtn');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.removeAttribute('data-original-sku');
            }
        }

        function showPoAddFieldError(field, message) {
            if (!field) return;
            field.classList.add('is-invalid');
            let feedback = field.parentNode.querySelector('.invalid-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                field.parentNode.appendChild(feedback);
            }
            feedback.textContent = message || 'Invalid';
        }

        function validatePoAddProductForm() {
            let ok = true;
            const skuEl = document.getElementById('sku');
            const unitEl = document.getElementById('unit');
            [skuEl, unitEl].forEach((field) => {
                if (!field) return;
                const val = (field.value || '').trim();
                if (!val) {
                    showPoAddFieldError(field, field.id === 'unit' ? 'Please select a unit' : 'This field is required');
                    ok = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            return ok;
        }

        function buildPoAddProductFormData() {
            const formElement = document.getElementById('addProductForm');
            const formData = new FormData(formElement);
            formData.append('parent', document.getElementById('parent')?.value || '');
            formData.append('sku', document.getElementById('sku')?.value || '');
            formData.append('unit', document.getElementById('unit')?.value || '');
            formData.append('operation', 'create');

            const values = {
                lp: document.getElementById('lp')?.value || null,
                cp: document.getElementById('cp')?.value || null,
                lps: document.getElementById('lps')?.value || null,
                wt_act: document.getElementById('wtAct')?.value || null,
                dc: document.getElementById('dc')?.value || null,
                l2_url: document.getElementById('l2Url')?.value || null,
                b: document.getElementById('b')?.value || null,
                h1: document.getElementById('h1')?.value || null,
                weight: document.getElementById('weight')?.value || null,
                msrp: document.getElementById('msrp')?.value || null,
                map: document.getElementById('map')?.value || null,
                status: document.getElementById('status')?.value || null,
                unit: document.getElementById('unit')?.value || null,
                upc: document.getElementById('upc')?.value || null,
            };
            formData.append('Values', JSON.stringify(values));
            return formData;
        }

        function applySkuToPoSelect($sel, sku) {
            if (!$sel || !$sel.length || !sku) return;
            const exists = $sel.find('option').filter(function () { return this.value === sku; }).length > 0;
            if (!exists) {
                $sel.append(new Option(sku, sku, true, true));
            }
            $sel.val(sku).trigger('change');
            fillTechFromSkuSelect($sel);
        }

        $(document).on('click', '.po-add-sku-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const row = $(this).closest('.product-row');
            poAddSkuTargetSelect = row.find('select.po-sku-select2').first();
            resetPoAddProductForm();
            const modalEl = document.getElementById('addProductModal');
            if (!modalEl) return;
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        document.getElementById('productImage')?.addEventListener('change', function () {
            const preview = document.getElementById('imagePreview');
            if (!preview) return;
            preview.innerHTML = '';
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    preview.innerHTML = `<img src="${ev.target.result}" alt="Preview" style="max-width:120px;max-height:120px;border-radius:8px;">`;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        document.getElementById('saveProductBtn')?.addEventListener('click', async function () {
            if (!validatePoAddProductForm()) return;
            const saveBtn = this;
            saveBtn.disabled = true;
            try {
                const response = await fetch(@json(route('product_master.store')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: buildPoAddProductFormData(),
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    if (response.status === 409 || (data.message && String(data.message).includes('already exists'))) {
                        showPoAddFieldError(document.getElementById('sku'), 'This SKU already exists. Please use a different SKU.');
                        alert(data.message || 'This SKU already exists in the database!');
                        return;
                    }
                    throw new Error(data.message || ('Server returned status ' + response.status));
                }

                const newSku = (data?.data?.SKU || data?.data?.sku || document.getElementById('sku')?.value || '').trim();
                const modalEl = document.getElementById('addProductModal');
                bootstrap.Modal.getInstance(modalEl)?.hide();
                if (poAddSkuTargetSelect && poAddSkuTargetSelect.length && newSku) {
                    applySkuToPoSelect(poAddSkuTargetSelect, newSku);
                }
                resetPoAddProductForm();
                poAddSkuTargetSelect = null;
                alert(data.message || 'Product successfully added!');
            } catch (err) {
                alert(err.message || 'Failed to save product');
            } finally {
                saveBtn.disabled = false;
            }
        });

        document.getElementById('addProductModal')?.addEventListener('show.bs.modal', function () {
            document.body.classList.add('po-nested-product-modal');
        });

        document.getElementById('addProductModal')?.addEventListener('shown.bs.modal', function () {
            this.style.zIndex = 2200;
            const backs = document.querySelectorAll('body > .modal-backdrop');
            if (backs.length > 1) {
                backs[backs.length - 1].style.zIndex = 2190;
            }
            document.getElementById('sku')?.focus();
        });

        document.getElementById('addProductModal')?.addEventListener('hidden.bs.modal', function () {
            document.body.classList.remove('po-nested-product-modal');
            resetPoAddProductForm();
            // Keep Create/Edit PO modal above its backdrop after nested close
            const openPo = document.querySelector('#createPurchaseOrderModal.show, #editPurchaseOrderModal.show');
            if (openPo) {
                openPo.style.zIndex = 2100;
                const backs = document.querySelectorAll('body > .modal-backdrop');
                backs.forEach((b) => { b.style.zIndex = 2090; });
            }
        });
    });
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    let currentPage = 1;
    const itemsPerPage = 12;
    let allPurchaseOrders = [];
    let sortColumn = 'po_date';
    let sortDir = 'desc';

    // Zoom page content only — never body (breaks modal backdrop / z-index).
    (function () {
        const page = document.querySelector('.content-page');
        if (page) page.style.zoom = '90%';
    })();

    document.addEventListener("DOMContentLoaded", function () {
        // Host modals on <body> so they sit above backdrops correctly.
        ['createPurchaseOrderModal', 'editPurchaseOrderModal', 'poItemsModal'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        });

        getPurchaseOrderData();

        document.getElementById("purchase-order-search").addEventListener("input", applyFilters);
        document.getElementById("search-items").addEventListener("input", applyFilters);
        document.getElementById("po-date-filter").addEventListener("change", applyFilters);
        document.getElementById("archive-filter").addEventListener("change", function() {
            currentPage = 1;
            getPurchaseOrderData();
        });

        document.querySelectorAll('.po-sortable').forEach(th => {
            th.addEventListener('click', function() {
                const key = this.getAttribute('data-sort');
                if (sortColumn === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                else { sortColumn = key; sortDir = 'asc'; }
                currentPage = 1;
                const filtered = getFilteredData();
                renderPurchaseOrderTable(filtered);
                renderPaginationControls(filtered);
            });
        });

        document.getElementById("po-table-body").addEventListener("click", function (e) {
            const btn = e.target.closest(".po-copy-number-btn");
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            copyPoNumber(btn.getAttribute("data-po") || "", btn);
        });
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
                
                // Supplier (Select2 quick search)
                let supplierSelect = form.querySelector("[name='supplier']");
                if (supplierSelect) {
                    const supplierId = order.supplier_id != null ? String(order.supplier_id) : '';
                    if (window.jQuery && $(supplierSelect).hasClass('select2-hidden-accessible')) {
                        $(supplierSelect).val(supplierId).trigger('change');
                    } else {
                        Array.from(supplierSelect.options).forEach(opt => {
                            opt.selected = (String(opt.value) === supplierId);
                        });
                        if (window.jQuery) {
                            $(supplierSelect).val(supplierId);
                        }
                    }
                }


                // PO Date
                form.querySelector("[name='po_date']").value = order.po_date ?? "";

                //Finally show modal
                let editModal = new bootstrap.Modal(document.getElementById("editPurchaseOrderModal"));
                editModal.show();
            }
        });

    });

    function getPurchaseOrderData(){
        const filter = document.getElementById("archive-filter").value;
        fetch('/purchase-orders/list?filter=' + encodeURIComponent(filter))
        .then(res => {
            if (!res.ok) throw new Error('Failed to load data');
            return res.json();
        })
        .then(data => {
            allPurchaseOrders = Array.isArray(data) ? data : [];
            renderPurchaseOrderTable();
            renderPaginationControls();
        })
        .catch(err => {
            console.error(err);
            allPurchaseOrders = [];
            renderPurchaseOrderTable();
            renderPaginationControls();
        });
    }

    function formatNum(val) {
        if (val == null || val === '') return '-';
        const n = parseFloat(val);
        if (isNaN(n)) return '-';
        return n % 1 === 0 ? n : n.toFixed(2);
    }

    /** Display like "1 Apr"; hover title shows full date (e.g. 2026-06-22 / 22 June 2026). */
    function formatPoDateCell(raw) {
        const s = String(raw ?? '').trim();
        if (!s) return '-';
        const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        let d = null;
        if (m) {
            d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        } else {
            const parsed = new Date(s);
            if (!isNaN(parsed.getTime())) d = parsed;
        }
        if (!d || isNaN(d.getTime())) return escapeHtml(s);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const short = d.getDate() + ' ' + months[d.getMonth()];
        const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        const iso = yyyy + '-' + mm + '-' + dd;
        const full = d.getDate() + ' ' + fullMonths[d.getMonth()] + ' ' + yyyy;
        const title = iso + ' — ' + full;
        return `<span class="po-date-short" title="${escapeHtml(title)}" style="cursor:default;">${escapeHtml(short)}</span>`;
    }

    function getFilteredData() {
        const searchValue = document.getElementById("purchase-order-search").value.toLowerCase();
        const searchItemsValue = document.getElementById("search-items").value.toLowerCase();
        const selectedDate = document.getElementById("po-date-filter").value;
        return allPurchaseOrders.filter(order => {
            const matchesSearch = !searchValue || (order.supplier_name && order.supplier_name.toLowerCase().includes(searchValue));
            const matchesItems = !searchItemsValue || (order.sku_list && order.sku_list.toLowerCase().includes(searchItemsValue));
            const matchesDate = selectedDate ? order.po_date === selectedDate : true;
            return matchesSearch && matchesItems && matchesDate;
        });
    }

    function escapeHtml(str) {
        return String(str ?? "")
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    const PO_SUPPLIER_PALETTE = [
        { bg: '#dbeafe', text: '#1e3a8a' },
        { bg: '#dcfce7', text: '#14532d' },
        { bg: '#fce7f3', text: '#831843' },
        { bg: '#e0e7ff', text: '#3730a3' },
        { bg: '#ffedd5', text: '#9a3412' },
        { bg: '#ccfbf1', text: '#115e59' },
        { bg: '#f3e8ff', text: '#581c87' },
        { bg: '#fee2e2', text: '#991b1b' },
        { bg: '#fef3c7', text: '#92400e' },
        { bg: '#cffafe', text: '#155e75' },
    ];

    function poSupplierColors(name) {
        const key = String(name || '').trim().toUpperCase();
        if (!key) return null;
        if (key === 'FIND') return { bg: '#ffc107', text: '#111827' };
        let hash = 0;
        for (let i = 0; i < key.length; i++) {
            hash = ((hash << 5) - hash) + key.charCodeAt(i);
            hash |= 0;
        }
        return PO_SUPPLIER_PALETTE[Math.abs(hash) % PO_SUPPLIER_PALETTE.length];
    }

    function renderSupplierCell(name) {
        const full = String(name || '').trim();
        if (!full) return '<span class="text-muted">-</span>';
        const display = full.split(/\s+/).filter(Boolean)[0] || full;
        const colors = poSupplierColors(full);
        if (!colors) {
            return `<span title="${escapeHtml(full)}" style="font-weight:700;font-size:0.72rem;">${escapeHtml(display)}</span>`;
        }
        return `<span class="po-supplier-badge" style="background:${colors.bg};color:${colors.text};" title="${escapeHtml(full)}">${escapeHtml(display)}</span>`;
    }

    function renderSummarySkuTags(order) {
        let skus = Array.isArray(order.skus)
            ? order.skus.map(s => String(s ?? '').trim()).filter(Boolean)
            : [];
        if (!skus.length && order.items_json) {
            try {
                const items = typeof order.items_json === 'string'
                    ? JSON.parse(order.items_json || '[]')
                    : (order.items_json || []);
                skus = (Array.isArray(items) ? items : [])
                    .map(it => String(it?.sku ?? '').trim())
                    .filter(Boolean);
            } catch (e) { /* ignore */ }
        }
        if (!skus.length && order.sku_list) {
            skus = String(order.sku_list).split(',').map(s => s.trim()).filter(Boolean)
                .filter(s => s !== '...');
        }
        if (!skus.length) {
            return '<span class="text-muted">-</span>';
        }
        const maxVisible = 8;
        const visible = skus.slice(0, maxVisible);
        const extra = skus.length - visible.length;
        const tags = visible.map(sku =>
            `<span class="po-summary-sku-tag" title="${escapeHtml(sku)}">${escapeHtml(sku)}</span>`
        ).join('');
        const more = extra > 0
            ? `<span class="po-summary-sku-more" title="${escapeHtml(skus.slice(maxVisible).join(', '))}">+${extra}</span>`
            : '';
        return `<div class="po-summary-sku-tags">${tags}${more}</div>`;
    }

    function renderPoNumberCell(poNumber) {
        const po = String(poNumber || "").trim();
        if (!po) {
            return '<span class="text-muted">-</span>';
        }
        const esc = escapeHtml(po);
        return `<div class="po-number-dot-wrap">
            <span class="po-status-dot" title="${esc}"></span>
            <div class="po-number-hover-card">
                <span class="po-number-text">${esc}</span>
                <button type="button" class="btn btn-sm btn-link p-0 po-copy-number-btn" data-po="${esc}" title="Copy PO Number" aria-label="Copy PO Number">
                    <i class="far fa-copy"></i>
                </button>
            </div>
        </div>`;
    }

    function copyPoNumber(text, btn) {
        const value = String(text || "").trim();
        if (!value) return;
        const done = function () {
            if (!btn) return;
            const icon = btn.querySelector("i");
            if (!icon) return;
            icon.className = "fas fa-check text-success";
            setTimeout(function () { icon.className = "far fa-copy"; }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {
                fallbackCopy(value);
                done();
            });
        } else {
            fallbackCopy(value);
            done();
        }
    }

    function fallbackCopy(text) {
        const tmp = document.createElement("textarea");
        tmp.value = text;
        document.body.appendChild(tmp);
        tmp.select();
        try { document.execCommand("copy"); } catch (e) {}
        document.body.removeChild(tmp);
    }

    function sortData(arr, key, dir) {
        if (!key || !arr.length) return arr;
        const mult = dir === 'asc' ? 1 : -1;
        return [...arr].sort((a, b) => {
            let va = a[key];
            let vb = b[key];
            const isNum = ['total_amount', 'advance_amount', 'balance', 'total_cbm'].includes(key);
            if (isNum) {
                va = parseFloat(va) || 0;
                vb = parseFloat(vb) || 0;
                return mult * (va - vb);
            }
            va = (va ?? '').toString().toLowerCase();
            vb = (vb ?? '').toString().toLowerCase();
            return mult * va.localeCompare(vb);
        });
    }

    function updateSortIcons() {
        document.querySelectorAll('.po-sortable').forEach(th => {
            const key = th.getAttribute('data-sort');
            const icon = th.querySelector('.sort-icon');
            if (!icon) return;
            icon.className = 'fas ms-1 sort-icon ' + (sortColumn === key ? (sortDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
        });
    }

    function updatePoRowCount(data) {
        const el = document.getElementById("po-row-count");
        if (!el) return;

        const source = Array.isArray(data) ? data : getFilteredData();
        const total = source.length;

        if (total === 0) {
            el.textContent = "Showing 0 rows";
            return;
        }

        const start = (currentPage - 1) * itemsPerPage + 1;
        const end = Math.min(currentPage * itemsPerPage, total);
        el.textContent = "Showing " + start + " to " + end + " of " + total + " rows";
    }

    function resetPoSelectionUi() {
        const selectAll = document.getElementById("select-all-po");
        if (selectAll) selectAll.checked = false;
        const deleteBtn = document.getElementById("delete-selected-btn");
        if (deleteBtn) deleteBtn.style.display = "none";
    }

    function getVisiblePoCheckboxes() {
        return Array.from(document.querySelectorAll("#po-table-body .order-checkbox"));
    }

    function syncPoSelectAllCheckbox() {
        const selectAll = document.getElementById("select-all-po");
        if (!selectAll) return;
        const boxes = getVisiblePoCheckboxes();
        if (!boxes.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        const checkedCount = boxes.filter(function (cb) { return cb.checked; }).length;
        selectAll.checked = checkedCount === boxes.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    }

    function updatePoDeleteButtonVisibility() {
        const deleteBtn = document.getElementById("delete-selected-btn");
        if (!deleteBtn) return;
        const anyChecked = getVisiblePoCheckboxes().some(function (cb) { return cb.checked; });
        deleteBtn.style.display = anyChecked ? "inline-block" : "none";
    }

    function renderPurchaseOrderTable(data) {
        const tbody = document.getElementById("po-table-body");
        tbody.innerHTML = "";
        resetPoSelectionUi();

        let source = Array.isArray(data) ? data : getFilteredData();
        if (sortColumn) source = sortData(source, sortColumn, sortDir);
        updateSortIcons();

        const startIndex = (currentPage - 1) * itemsPerPage;
        const endIndex = startIndex + itemsPerPage;
        const currentItems = source.slice(startIndex, endIndex);
        const filterVal = document.getElementById("archive-filter").value;

        // Update summary badges from full source
        let sumAdvance = 0, sumOAmount = 0, sumBalance = 0, sumCbm = 0;
        source.forEach(o => {
            sumAdvance += parseFloat(o.advance_amount) || 0;
            sumOAmount += parseFloat(o.total_amount) || 0;
            sumBalance += parseFloat(o.balance) || 0;
            sumCbm += parseFloat(o.total_cbm) || 0;
        });
        document.getElementById("badge-sum-advance").textContent = formatNum(sumAdvance);
        document.getElementById("badge-sum-oamount").textContent = formatNum(sumOAmount);
        document.getElementById("badge-sum-balance").textContent = formatNum(sumBalance);
        document.getElementById("badge-sum-cbm").textContent = formatNum(sumCbm);

        if (currentItems.length === 0) {
            const colCount = document.querySelectorAll("#po-table thead th").length;
            const tr = document.createElement("tr");
            tr.innerHTML = `<td colspan="${colCount}" class="text-center text-muted py-4">No data found</td>`;
            tbody.appendChild(tr);
            updatePoRowCount(source);
            return;
        }

        currentItems.forEach(order => {
            const items = JSON.parse(order.items_json || '[]');
            const orderEsc = JSON.stringify(order).replace(/'/g, "&#39;");
            const itemsEsc = JSON.stringify(items).replace(/'/g, "&#39;");
            const isArchived = !!order.is_archived;
            const archiveBtn = (filterVal === "archived" || isArchived)
                ? `<button type="button" class="btn btn-sm btn-success restore-order-btn" data-order-id="${order.id}" title="Restore">
                        <i class="fas fa-undo"></i>
                   </button>`
                : `<button type="button" class="btn btn-sm btn-danger archive-order-btn" data-order-id="${order.id}" title="Archive">
                        <i class="fas fa-archive"></i>
                   </button>`;

            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td class="text-center"><input type="checkbox" class="order-checkbox" data-order-id="${order.id}"/></td>
                <td class="text-center">${renderPoNumberCell(order.po_number)}</td>
                <td class="text-center">${formatPoDateCell(order.po_date)}</td>
                <td class="text-center">${renderSupplierCell(order.supplier_name)}</td>
                <td class="po-summary-cell">${renderSummarySkuTags(order)}</td>
                <td class="text-center">${formatNum(order.total_amount)}</td>
                <td class="text-center">${formatNum(order.advance_amount)}</td>
                <td class="text-center">${formatNum(order.balance)}</td>
                <td class="text-center">${formatNum(order.total_cbm)}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-actions flex-wrap gap-1 justify-content-center">
                        <button type="button" class="btn btn-sm btn-primary view-items-btn" data-items='${itemsEsc}' title="View Items">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-info export-items-btn" data-order='${orderEsc}' data-items='${itemsEsc}' title="Export">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-success generate-pdf-btn" data-order-id="${order.id}" title="PDF">
                            <i class="fas fa-search"></i>
                        </button>
                        ${archiveBtn}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        attachItemModalListeners();
        attachExportExcelListeners();
        attachArchiveRestoreListeners();
        updatePoRowCount(source);
    }

    function renderPaginationControls(data) {
        const source = Array.isArray(data) ? data : getFilteredData();
        const totalPages = Math.max(1, Math.ceil(source.length / itemsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        const paginationContainer = document.getElementById("po-pagination");
        paginationContainer.innerHTML = "";

        if (source.length === 0) {
            updatePoRowCount(source);
            return;
        }

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement("button");
            btn.className = `btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-primary'} mx-1`;
            btn.innerText = i;
            btn.addEventListener("click", function () {
                currentPage = i;
                renderPurchaseOrderTable(source);
                renderPaginationControls(source);
            });
            paginationContainer.appendChild(btn);
        }

        updatePoRowCount(source);
    }

    function attachArchiveRestoreListeners() {
        document.querySelectorAll(".archive-order-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-order-id");
                if (!confirm("Archive this purchase order?")) return;
                fetch("/purchase-orders/" + id + "/archive", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({})
                })
                .then(res => res.json())
                .then(() => getPurchaseOrderData())
                .catch(err => console.error(err));
            });
        });
        document.querySelectorAll(".restore-order-btn").forEach(btn => {
            btn.addEventListener("click", function () {
                const id = this.getAttribute("data-order-id");
                if (!confirm("Restore this purchase order to active?")) return;
                fetch("/purchase-orders/" + id + "/restore", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                        "Accept": "application/json"
                    },
                    body: JSON.stringify({})
                })
                .then(res => res.json())
                .then(() => getPurchaseOrderData())
                .catch(err => console.error(err));
            });
        });
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
                                ${item.photo_url || item.photo
                                    ? `<div class="img-hover-photo">
                                            <img src="${item.photo_url || (String(item.photo).startsWith('http') || String(item.photo).startsWith('/') ? item.photo : '/storage/' + item.photo)}" alt="Photo" class="photo-img">
                                            <div class="zoomed-photo">
                                                <img src="${item.photo_url || (String(item.photo).startsWith('http') || String(item.photo).startsWith('/') ? item.photo : '/storage/' + item.photo)}" alt="Zoomed Photo">
                                            </div>
                                        </div>`
                                    : '<span class="text-muted">No Image</span>'}
                            </td>
                            <td><span class="fw-semibold">${item.sku || '-'}</span></td>
                            <td>${item.supplier_sku || '-'}</td>
                            <td style="white-space: pre-line;">${item.tech || '-'}</td>
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
        const searchItemsValue = document.getElementById("search-items").value.toLowerCase();
        const selectedDate = document.getElementById("po-date-filter").value;

        currentPage = 1;

        const filtered = allPurchaseOrders.filter(order => {
            const matchesSearch = !searchValue || (order.supplier_name && order.supplier_name.toLowerCase().includes(searchValue));
            const matchesItems = !searchItemsValue || (order.sku_list && order.sku_list.toLowerCase().includes(searchItemsValue));
            const matchesDate = selectedDate ? order.po_date === selectedDate : true;

            return matchesSearch && matchesItems && matchesDate;
        });

        renderPurchaseOrderTable(filtered);
        renderPaginationControls(filtered);
    }

    document.addEventListener('DOMContentLoaded', function () {
        // Listen for checkbox changes
        document.addEventListener("change", function (e) {
            if (e.target.classList.contains("order-checkbox")) {
                syncPoSelectAllCheckbox();
                updatePoDeleteButtonVisibility();
            }
            if (e.target.id === "select-all-po") {
                getVisiblePoCheckboxes().forEach(function (cb) {
                    cb.checked = e.target.checked;
                });
                e.target.indeterminate = false;
                updatePoDeleteButtonVisibility();
            }
        });

        // RMB price → show USD conversion; USD price → no RMB autopopulate.
        let poUsdToCny = null;
        fetch(@json(route('purchase-orders.convert')) + '?amount=1&from=USD&to=CNY')
            .then(r => r.json())
            .then(data => {
                const rate = parseFloat(data?.rates?.CNY);
                if (isFinite(rate) && rate > 0) poUsdToCny = rate;
                document.querySelectorAll('.product-row').forEach(updatePoUsdEquiv);
            })
            .catch(() => {});

        function updatePoUsdEquiv(row) {
            if (!row) return;
            const currency = (row.querySelector('.po-currency-select')?.value || 'USD').toUpperCase();
            const price = parseFloat(row.querySelector('.po-price-input')?.value);
            const hint = row.querySelector('.po-usd-equiv');
            if (!hint) return;
            if (currency === 'RMB' && isFinite(price) && price > 0 && poUsdToCny) {
                const usd = (price / poUsdToCny).toFixed(2);
                hint.textContent = '≈ ' + usd + '$ (also shown on proforma)';
                hint.classList.remove('d-none');
            } else {
                hint.textContent = '';
                hint.classList.add('d-none');
            }
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.po-currency-select, .po-price-input')) {
                updatePoUsdEquiv(e.target.closest('.product-row'));
            }
        });
        document.addEventListener('input', function (e) {
            if (e.target.matches('.po-price-input')) {
                updatePoUsdEquiv(e.target.closest('.product-row'));
            }
        });

        // Handle delete button click
        document.getElementById("delete-selected-btn").addEventListener("click", function () {
            const checkedBoxes = document.querySelectorAll(".order-checkbox:checked");
            if (checkedBoxes.length === 0) return;

            // if (!confirm(`Delete ${checkedBoxes.length} selected order(s)?`)) return;

            // Get all selected order IDs
            const ids = Array.from(checkedBoxes).map(cb => cb.dataset.orderId);

            // Remove rows from UI
            checkedBoxes.forEach(cb => { const row = cb.closest("tr"); if (row) row.remove(); });

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

