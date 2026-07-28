@extends('layouts.vertical', ['title' => 'Transit Container INV'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
  .tabulator .tabulator-header {
    background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
    border-bottom: 2px solid #2563eb;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
  }

  .tabulator .tabulator-header .tabulator-col {
    text-align: center;
    background: transparent;
    border-right: 1px solid #e5e7eb;
    padding: 8px 4px;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.08rem;
    letter-spacing: 0.02em;
    transition: background 0.2s;
  }

  .tabulator .tabulator-header .tabulator-col:hover {
    background: #e0eaff;
    color: #2563eb;
  }

  /* Vertical column titles (same pattern as Order / Forecast); SKU, History, checkbox, Actions stay horizontal */
  .tabulator-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
    min-height: 110px;
    padding: 4px 2px;
    box-sizing: border-box;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 2px;
    flex: 0 0 auto;
    min-height: 90px;
    width: 100%;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col .tabulator-title {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    white-space: nowrap;
    font-weight: 700;
    font-size: 0.68rem;
    line-height: 1.1;
    letter-spacing: 0.02em;
    text-align: center;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col .tabulator-col-title br {
    display: none;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-col-content,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="last_saved_by"] .tabulator-col-content,
  .tabulator-table.tabulator .tabulator-header .tabulator-col:not([tabulator-field]) .tabulator-col-content {
    min-height: auto !important;
    justify-content: center;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-col-title-holder,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="last_saved_by"] .tabulator-col-title-holder,
  .tabulator-table.tabulator .tabulator-header .tabulator-col:not([tabulator-field]) .tabulator-col-title-holder {
    min-height: auto !important;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-col-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="last_saved_by"] .tabulator-col-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="last_saved_by"] .tabulator-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col:not([tabulator-field]) .tabulator-col-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col:not([tabulator-field]) .tabulator-title {
    writing-mode: horizontal-tb !important;
    transform: none !important;
    min-height: auto !important;
    font-size: 0.85rem !important;
  }
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-col-title,
  .tabulator-table.tabulator .tabulator-header .tabulator-col[tabulator-field="our_sku"] .tabulator-title {
    font-size: 1.1rem !important;
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
    color: #000000;
    font-weight: 500;
    vertical-align: middle;
    max-width: 300px;
    transition: background 0.18s, color 0.18s;
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
    .nav-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    white-space: nowrap;
    scrollbar-width: thin; /* Firefox */
  }

  .nav-tabs .nav-item {
    flex-shrink: 0;
  }

  /* Optional: customize scrollbar */
  .nav-tabs::-webkit-scrollbar {
    height: 6px;
  }

  .nav-tabs::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 5px;
  }

  .nav-tabs::-webkit-scrollbar-track {
    background: transparent;
  }

  .copy-sku-icon:hover {
    color: #1d4ed8 !important;
    transform: scale(1.1);
  }

  .copy-sku-icon:active {
    transform: scale(0.95);
  }

  /* Keep Bootstrap modals usable with body zoom on this page */
  .modal {
    z-index: 2000 !important;
  }
  .modal-backdrop {
    z-index: 1990 !important;
  }

</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Transit Container INV', 'sub_title' => 'Transit Container INV'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                    <div class="d-flex gap-4 align-items-center">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'transit_container_inv'])
                        <div class="fw-semibold text-dark" style="font-size: 1rem;" title="Cartons">
                            📦 <span class="text-success" id="total-cartons-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            Qty: <span class="text-primary" id="total-qty-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;" title="Amount">
                            💲 <span class="text-primary" id="total-amount-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            CBM: <span class="text-primary" id="total-cbm-display">0</span>
                        </div>
                    </div>

                    <!-- 🔽 Filter Type Dropdown -->
                    <div class="d-flex align-items-center gap-2">
                        <label for="filter-type" class="fw-semibold mb-0" style="font-size: 0.95rem;">Filter Type:</label>
                        <select id="filter-type" class="form-select form-select-sm" style="width: 75px;">
                            <option value="">All</option>
                            <option value="new">New</option>
                            <option value="changes">Changes</option>
                        </select>
                    </div>

                    <!-- 🔍 Search Input -->
                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by SKU, Supplier, Parent..." 
                        style="max-width: 150px; border: 2px solid #2185ff; font-size: 0.95rem;">

                        <button id="export-tab-excel" class="btn btn-sm btn-success" title="Export Excel" aria-label="Export Excel">
                            <i class="fas fa-download"></i>
                        </button>

                    {{-- push Inventory --}}
                    <button id="push-inventory-btn" class="btn btn-primary btn-sm" title="Push Inventory">
                        Push Inv.
                    </button>

                    <button id="push-arrived-container-btn" class="btn btn-info btn-sm" title="Push to Arrived">
                        Push to Arrived
                    </button>

                    <!-- ➕ Add Container Button -->
                    <button id="add-tab-btn" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Container
                    </button>
                    {{-- <button id="add-new-product-btn" class="btn btn-warning btn-sm" 
                        onclick="window.locationhref='product-master'">
                        <i class="fas fa-plus-circle"></i> New Product
                    </button> --}}

                    


                    <button id="add-items-btn" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus"></i> Notes
                    </button>
                    <button type="button" id="add-imp-name-btn" class="btn btn-outline-primary btn-sm" title="Add Imp name option">
                        <i class="fas fa-plus"></i> IMP name
                    </button>
                    <button type="button" id="add-hsn-code-btn" class="btn btn-outline-primary btn-sm" title="Add HSN Code option">
                        <i class="fas fa-plus"></i> HSN Code
                    </button>
                    <button type="button" id="transit-invoice-btn" class="btn btn-dark btn-sm" title="Generate Invoice from table data">
                        <i class="fas fa-file-invoice me-1"></i> Invoice
                    </button>
                    <a href="{{ url('product-master') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-plus-circle"></i> Product
                    </a>
                    @if($canEditDelete ?? false)
                    <button class="btn btn-danger btn-sm d-none" id="delete-selected-btn">
                        <i class="fas fa-trash me-1"></i> Delete Selected
                    </button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" id="transit-history-btn" data-bs-toggle="modal" data-bs-target="#transitHistoryModal" title="History" aria-label="History">
                        <i class="fas fa-history"></i>
                    </button>
                </div>

                <!-- Tabs Navigation -->
                <div style="overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                        div[style*="overflow-x: auto"]::-webkit-scrollbar {
                            display: none;
                        }
                    </style>
                    <ul class="nav nav-tabs flex-nowrap d-flex mb-0" id="tabList" role="tablist" style="min-width: max-content;">
                        @foreach($tabs as $index => $tab)
                            <li class="nav-item" style="flex-shrink: 0;">
                                <button class="nav-link {{ $index == 0 ? 'active' : '' }}" id="tab-{{ $index }}-tab" data-bs-toggle="tab" data-bs-target="#tab-{{ $index }}" type="button" role="tab">
                                    {{ $tab }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Tabs Content -->
                {{-- Drive panes from $tabs (same source as the buttons above) so button index === pane index.
                     Previously this iterated $groupedData, whose key order is not guaranteed to match $tabs —
                     that caused the Tabulator for tab N to be bound to the wrong container's rows, and any
                     edit there got POSTed back with the wrong tab_name (e.g., Container 85 → Container 86). --}}
                <div class="tab-content mt-3" id="tabContent">
                    @foreach($tabs as $index => $tab)
                        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                             id="tab-{{ $index }}"
                             role="tabpanel"
                             data-tab-name="{{ $tab }}">
                            <div id="tabulator-{{ $index }}" class="tabulator-table" data-tab-name="{{ $tab }}"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div id="cell-image-preview" style="position:absolute; display:none; z-index:9999; border:1px solid #ccc; background:#fff; padding:5px; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.2);">
  <img src="" style="max-height:250px; max-width:350px;">
</div>

<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="addItemModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Add Notes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="purchaseOrderForm" method="POST" action="{{ url('transit-container/save') }}" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        console.log("PAGE LOADED - JS WORKING");

                        $(document).on("change", ".sku-select", function () {
                            console.log("SKU changed!");
                        });
                        console.log("Product Values Map:", {!! $productValuesMap !!});
                    });
                </script>

                <div class="modal-body">
                    {{-- Product Section --}}
                    <div>
                        <h5 class="fw-semibold mb-2 text-primary">
                            <i class="fas fa-boxes-stacked me-1"></i> Notes
                        </h5>
                        <div class="row g-2">
                          <div class="col-md-3">
                              <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                              <select class="form-select" name="tab_name" required>
                                  <option value="" disabled selected>select container</option>
                                  @foreach($tabs as $tab)
                                      <option value="{{ $tab }}">{{ $tab }}</option>
                                  @endforeach
                              </select>
                          </div>
                        </div>
                        <div id="productRowsWrapper">
                            <div class="row g-2 product-row border rounded p-2 mt-2 position-relative">
                                <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2 ">
                                    <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor: pointer; font-size: 1.2rem; margin-top:-10px;"></i>
                                </div>
                                {{-- <div class="col-md-3">
                                    <label class="form-label fw-semibold">SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="our_sku[]" required>
                                </div> --}}
                                <div class="col-md-3">
                                    <select class="form-select sku-select" name="our_sku[]" required>
                                        <option value="" disabled selected>Select SKU</option>
                                        @foreach($skus as $sku)
                                            <option value="{{ $sku }}">{{ $sku }}</option>
                                        @endforeach
                                    </select>
                                </div>  

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Supplier</label>
                                    <select class="form-select" name="supplier_name[]">
                                        <option value="" disabled>Select Supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Qty/Ctns</label>
                                    <input type="number" class="form-control" name="no_of_units[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Qty Ctns</label>
                                    <input type="number" class="form-control" name="total_ctn[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Qty</label>
                                    <input type="number" class="form-control" name="pcs_qty[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Rate ($)</label>
                                    <input type="number" class="form-control" name="rate[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">CBM</label>
                                    <input type="number" class="form-control" name="cbm[]" step="any">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Unit</label>
                                    <input type="text" class="form-control" name="unit[]" step="any">
                                </div>
                                {{-- <div class="col-md-3">
                                    <label class="form-label fw-semibold">Unit</label>
                                    <select class="form-select" name="unit[]">
                                        <option value="" disabled>select unit</option>
                                        <option value="pieces">pieces</option>
                                        <option value="pair">pair</option>
                                    </select>
                                </div> --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Imp name</label>
                                    <select class="form-select transit-imp-select" name="company_name[]">
                                        <option value="">Select</option>
                                        @foreach(($impNameOptions ?? ['5 core', 'K cube']) as $impOpt)
                                            <option value="{{ $impOpt }}" @selected(($lastImpName ?? '') === $impOpt)>{{ $impOpt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">HSN Code</label>
                                    <select class="form-select transit-hsn-select" name="hsn_code[]">
                                        <option value="">Select</option>
                                        @foreach(($hsnCodeOptions ?? []) as $hsnOpt)
                                            <option value="{{ $hsnOpt }}" @selected(($lastHsnCode ?? '') === $hsnOpt)>{{ $hsnOpt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Changes</label>
                                    <input type="text" class="form-control" name="changes[]">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Specifications</label>
                                    <textarea type="text" class="form-control" name="specification[]" rows="2"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRowBtn">
                                <i class="fas fa-plus-circle me-1"></i> Add Item Row
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

{{-- Invoice Modal — autopopulated from active container table --}}
<div class="modal fade" id="transitInvoiceModal" tabindex="-1" aria-labelledby="transitInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="transitInvoiceModalLabel">
                    <i class="fas fa-file-invoice me-2"></i> Invoice
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light">
                <div id="transit-invoice-print-area" class="bg-white border rounded p-4 shadow-sm">
                    <div class="text-center mb-3">
                        <h3 class="fw-bold mb-1" style="letter-spacing:1px;">INVOICE</h3>
                        <div class="text-muted small">Transit Container</div>
                    </div>
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3 pb-2 border-bottom">
                        <div>
                            <div class="fw-semibold">Container</div>
                            <div id="invoice-container-name" class="fs-5">—</div>
                        </div>
                        <div>
                            <div class="fw-semibold">Date</div>
                            <div id="invoice-date">—</div>
                        </div>
                        <div>
                            <div class="fw-semibold">Items</div>
                            <div id="invoice-item-count">0</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0" id="transit-invoice-table">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width:48px;">SL</th>
                                    <th>SKU</th>
                                    <th>Imp Name</th>
                                    <th>HSN</th>
                                    <th>Qty / Ctn</th>
                                    <th>Ctn</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody id="transit-invoice-tbody">
                                <tr><td colspan="7" class="text-center text-muted">No data</td></tr>
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr class="text-center">
                                    <td colspan="4" class="text-end">Total</td>
                                    <td id="invoice-total-units">0</td>
                                    <td id="invoice-total-ctn">0</td>
                                    <td id="invoice-total-qty">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="transit-invoice-print-btn">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden !important; }
    #transit-invoice-print-area, #transit-invoice-print-area * { visibility: visible !important; }
    #transit-invoice-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: none !important;
    }
}
</style>

{{-- Add Imp / HSN option modal --}}
<div class="modal fade" id="transitOptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold mb-0" id="transitOptionModalLabel">Add option</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="transit-option-field">
                <label class="form-label fw-semibold" for="transit-option-value" id="transit-option-value-label">Value</label>
                <input type="text" id="transit-option-value" class="form-control" maxlength="191" autocomplete="off">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="transit-option-save-btn">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Edit Row Modal (replaces inline column editing) --}}
<div class="modal fade" id="transitEditModal" tabindex="-1" aria-labelledby="transitEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="transitEditModalLabel">
                    <i class="fas fa-pen me-2"></i> Edit
                    <span class="ms-1 text-white-50 small" id="transit-edit-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="transit-edit-id">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="transit-edit-tab">Container <span class="text-danger">*</span></label>
                        <select id="transit-edit-tab" class="form-select" required>
                            <option value="">select container</option>
                            @foreach($tabs as $tab)
                                <option value="{{ $tab }}">{{ $tab }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="transit-edit-sku">SKU <span class="text-danger">*</span></label>
                        <select id="transit-edit-sku" class="form-select" required>
                            <option value="">Select SKU</option>
                            @foreach($skus as $sku)
                                <option value="{{ $sku }}">{{ $sku }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="transit-edit-supplier">Supplier</label>
                        <select id="transit-edit-supplier" class="form-select">
                            <option value="">Select Supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-units">Qty/Ctns</label>
                        <input type="number" step="any" id="transit-edit-units" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-ctn">Qty Ctns</label>
                        <input type="number" step="any" id="transit-edit-ctn" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-qty">Qty</label>
                        <input type="number" step="any" id="transit-edit-qty" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-rate">Rate ($)</label>
                        <input type="number" step="any" id="transit-edit-rate" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-cbm">CBM</label>
                        <input type="number" step="any" id="transit-edit-cbm" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-unit">Unit</label>
                        <select id="transit-edit-unit" class="form-select">
                            <option value="pieces">Pieces</option>
                            <option value="pair">Pair</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="transit-edit-clink">C link</label>
                        <input type="url" id="transit-edit-clink" class="form-control" placeholder="https://...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-imp">Imp name</label>
                        <select id="transit-edit-imp" class="form-select transit-imp-select">
                            <option value="">Select</option>
                            @foreach(($impNameOptions ?? ['5 core', 'K cube']) as $impOpt)
                                <option value="{{ $impOpt }}">{{ $impOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="transit-edit-hsn">HSN Code</label>
                        <select id="transit-edit-hsn" class="form-select transit-hsn-select">
                            <option value="">Select</option>
                            @foreach(($hsnCodeOptions ?? []) as $hsnOpt)
                                <option value="{{ $hsnOpt }}">{{ $hsnOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="transit-edit-changes">Changes</label>
                        <input type="text" id="transit-edit-changes" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="transit-edit-photos">Image URL</label>
                        <input type="text" id="transit-edit-photos" class="form-control" placeholder="Image URL">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="transit-edit-spec">Specifications</label>
                        <textarea id="transit-edit-spec" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="transit-edit-save-btn" onclick="saveTransitEditModal()">
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Transit Container History Modal --}}
<div class="modal fade" id="transitHistoryModal" tabindex="-1" aria-labelledby="transitHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="transitHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> Transit Container History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Action</label>
                        <select id="history-action-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="">All</option>
                            <option value="row_created">Row created</option>
                            <option value="row_updated">Row updated</option>
                            <option value="row_moved">Row moved</option>
                            <option value="row_deleted">Row deleted</option>
                            <option value="purchase_added">Purchase added</option>
                            <option value="tab_added">Tab/Container added</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">Container / Tab</label>
                        <input type="text" id="history-tab-filter" class="form-control form-control-sm" placeholder="Tab name" style="width: 140px;">
                    </div>
                    <div class="col-auto">
                        <label class="form-label small mb-0">SKU</label>
                        <input type="text" id="history-sku-filter" class="form-control form-control-sm" placeholder="SKU" style="width: 120px;">
                    </div>
                    <div class="col-auto align-self-end">
                        <button type="button" id="history-refresh-btn" class="btn btn-primary btn-sm"><i class="fas fa-sync-alt me-1"></i> Load</button>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 60vh;">
                    <table class="table table-bordered table-hover table-sm mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>From Tab</th>
                                <th>To Tab</th>
                                <th>SKU</th>
                                <th>Details</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody id="transit-history-tbody">
                            <tr><td colspan="7" class="text-center text-muted">Click Load to fetch history.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>



<script>

    $(document).ready(function () {

        function initSelect2() {
            $('.sku-select').select2({
                width: '100%',
                dropdownParent: $('#addItemModal')   // your modal ID
            });
        }

        // Initialize for first row
        initSelect2();

        // When new row added
        $(document).on('click', '#addItemRowBtn', function () {
            setTimeout(() => {
                initSelect2(); // Re-initialize for new dropdown
            }, 100);
        });

    });

let tabCounter = {{ count($tabs) }};
const groupedData = @json($groupedData);
const CAN_EDIT_DELETE = @json($canEditDelete ?? false);

function transitCellEditor(editor) {
    // Inline cell editors disabled — edit via Edit Modal Form only.
    return false;
}

let transitEditContext = { table: null, row: null, index: null };

function openTransitEditModal(row, table, index) {
    if (!CAN_EDIT_DELETE || !row) return;
    const data = row.getData ? row.getData() : row;
    transitEditContext = { table: table || null, row: row || null, index: index };

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = val == null ? '' : val;
    };

    let cbm = data.cbm;
    if ((cbm === undefined || cbm === null || cbm === '') && data.Values) {
        try {
            const values = typeof data.Values === 'string' ? JSON.parse(data.Values) : data.Values;
            cbm = values?.cbm ?? '';
        } catch (e) { cbm = ''; }
    }

    function ensureSelectOption(selectId, value) {
        const el = document.getElementById(selectId);
        if (!el || value == null || value === '') return;
        const v = String(value);
        const exists = Array.from(el.options).some(function (o) { return o.value === v; });
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            el.appendChild(opt);
        }
        el.value = v;
    }

    setVal('transit-edit-id', data.id || '');
    ensureSelectOption('transit-edit-tab', data.tab_name || '');
    ensureSelectOption('transit-edit-sku', data.our_sku || '');
    ensureSelectOption('transit-edit-supplier', data.supplier_name || '');
    setVal('transit-edit-units', data.no_of_units ?? '');
    setVal('transit-edit-ctn', data.total_ctn ?? '');
    setVal('transit-edit-qty', data.pcs_qty ?? '');
    setVal('transit-edit-rate', data.rate ?? '');
    setVal('transit-edit-cbm', cbm ?? '');
    let unitVal = String(data.unit || 'pieces').toLowerCase().trim();
    if (unitVal === 'pcs' || unitVal === 'piece') unitVal = 'pieces';
    if (unitVal !== 'pair') unitVal = 'pieces';
    setVal('transit-edit-unit', unitVal);
    setVal('transit-edit-imp', '');
    if (data.company_name) ensureSelectOption('transit-edit-imp', data.company_name);
    setVal('transit-edit-hsn', '');
    if (data.hsn_code) ensureSelectOption('transit-edit-hsn', data.hsn_code);
    setVal('transit-edit-changes', data.changes || '');
    setVal('transit-edit-spec', data.specification || '');
    setVal('transit-edit-clink', data.Clink || '');
    setVal('transit-edit-photos', data.photos || '');

    const titleSku = document.getElementById('transit-edit-modal-sku');
    if (titleSku) titleSku.textContent = data.our_sku || '';

    const modalEl = document.getElementById('transitEditModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

function saveTransitEditModal() {
    if (!CAN_EDIT_DELETE) return;
    const id = document.getElementById('transit-edit-id')?.value || '';
    const tabName = document.getElementById('transit-edit-tab')?.value || '';
    const sku = (document.getElementById('transit-edit-sku')?.value || '').trim();
    if (!tabName) {
        alert('Container is required.');
        return;
    }
    if (!sku) {
        alert('SKU is required.');
        return;
    }

    const units = parseFloat(document.getElementById('transit-edit-units')?.value) || 0;
    const ctn = parseFloat(document.getElementById('transit-edit-ctn')?.value) || 0;
    let qty = parseFloat(document.getElementById('transit-edit-qty')?.value);
    if (!(qty > 0)) qty = units * ctn;
    const rate = parseFloat(document.getElementById('transit-edit-rate')?.value) || 0;
    const clink = (document.getElementById('transit-edit-clink')?.value || '').trim();

    const payload = {
        id: id || undefined,
        tab_name: tabName,
        our_sku: sku,
        supplier_name: document.getElementById('transit-edit-supplier')?.value || '',
        no_of_units: document.getElementById('transit-edit-units')?.value || '',
        total_ctn: document.getElementById('transit-edit-ctn')?.value || '',
        rate: document.getElementById('transit-edit-rate')?.value || '',
        cbm: document.getElementById('transit-edit-cbm')?.value || '',
        unit: document.getElementById('transit-edit-unit')?.value || '',
        company_name: document.getElementById('transit-edit-imp')?.value || '',
        hsn_code: document.getElementById('transit-edit-hsn')?.value || '',
        changes: document.getElementById('transit-edit-changes')?.value || '',
        specification: document.getElementById('transit-edit-spec')?.value || '',
        photos: document.getElementById('transit-edit-photos')?.value || '',
        pcs_qty: qty,
        amount: rate * qty,
    };

    const saveBtn = document.getElementById('transit-edit-save-btn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    Promise.resolve()
        .then(function () {
            return fetch('/update-link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({ sku: sku.toUpperCase().replace(/\s+/g, ' '), column: 'Clink', value: clink })
            }).then(function (r) { return r.json(); }).catch(function () { return null; });
        })
        .then(function () {
            return fetch('/transit-container/save-row', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify(payload)
            }).then(function (r) { return r.json(); });
        })
        .then(function (response) {
            if (!response || !(response.success || response.id)) {
                alert((response && response.message) || 'Update failed');
                return;
            }
            const rowUpdate = Object.assign({}, payload, {
                id: response.id || payload.id,
                Clink: clink,
            });
            if (transitEditContext.row && typeof transitEditContext.row.update === 'function') {
                transitEditContext.row.update(rowUpdate);
            } else if (transitEditContext.table && rowUpdate.id) {
                try { transitEditContext.table.updateRow(rowUpdate.id, rowUpdate); } catch (e) {}
            }
            if (transitEditContext.table && typeof updateActiveTabSummary === 'function') {
                updateActiveTabSummary(transitEditContext.index || 0, transitEditContext.table);
            }
            // Remember selected Imp / HSN for next SKU import + refresh all dropdowns
            if (payload.company_name) {
                if (window.TRANSIT_IMP_OPTIONS.indexOf(payload.company_name) === -1) {
                    window.TRANSIT_IMP_OPTIONS.push(payload.company_name);
                }
                window.TRANSIT_LAST_IMP = payload.company_name;
                rebuildTransitOptionSelects('imp_name', window.TRANSIT_IMP_OPTIONS, payload.company_name);
            }
            if (payload.hsn_code) {
                if (window.TRANSIT_HSN_OPTIONS.indexOf(payload.hsn_code) === -1) {
                    window.TRANSIT_HSN_OPTIONS.push(payload.hsn_code);
                }
                window.TRANSIT_LAST_HSN = payload.hsn_code;
                rebuildTransitOptionSelects('hsn_code', window.TRANSIT_HSN_OPTIONS, payload.hsn_code);
            }
            const modalEl = document.getElementById('transitEditModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
        })
        .catch(function (err) {
            console.error(err);
            alert('Something went wrong while saving');
        })
        .finally(function () {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save';
            }
        });
}

function deleteTransitRows(table, ids) {
    if (!CAN_EDIT_DELETE || !ids.length) {
        return;
    }

    if (!confirm(`Are you sure you want to delete ${ids.length} selected record(s)?`)) {
        return;
    }

    $.ajax({
        url: '/transit-container/delete',
        type: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            ids: ids
        },
        success: function(response) {
            if (response.success) {
                ids.forEach(id => table.deleteRow(id));
                $('#delete-selected-btn').addClass('d-none');
                if (response.message) {
                    alert(response.message);
                }
            } else {
                alert(response.message || "Failed to delete rows.");
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON?.message || "Failed to delete rows.";
            alert(msg);
            console.error(xhr.responseText);
        }
    });
}

function transitClinkLinkFormatter(cell) {
    let url = cell.getValue() || "";
    if (url && url.trim() !== "") {
        return `
            <div style="display:flex;align-items:center;justify-content:center;">
                <a href="${url}" target="_blank" rel="noopener noreferrer"
                    class="btn btn-sm btn-outline-primary"
                    title="Open link" aria-label="Open link">
                    <i class="fas fa-link"></i>
                </a>
            </div>
        `;
    }
}

function escapeHtmlTransit(s) {
    if (s == null || s === "") return "";
    return String(s)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

// IMPORTANT: iterate the tab order used by the buttons + panes (server-rendered $tabs),
// not Object.entries(groupedData) which can return keys in a different order. Mixing the two
// caused container 85's tab pane to render container 86's rows (and vice versa) — and any
// edit there saved with the wrong tab_name, effectively "transferring" rows between containers.
const TAB_NAMES = @json(array_values($tabs));
TAB_NAMES.forEach((tabName, index) => {
    const data = (groupedData && groupedData[tabName]) ? groupedData[tabName] : [];
    let table = new Tabulator(`#tabulator-${index}`, {
        layout: "fitDataFill",
        data: data,
        pagination: "local",
        paginationSize: 50,
        height: "700px",
        rowHeight: 55,
        index: "id",
        selectable: CAN_EDIT_DELETE,
        columnDefaults: {
            headerTooltip: true,
        },
        // Default sort: surface "Not pushed" rows first so users can act on them.
        initialSort: [
            { column: "push_status", dir: "asc" }
        ],
        rowFormatter: function(row) {
            const rowData = row.getData();
            const status  = rowData.push_status || 'pending';
            const el      = row.getElement();

            el.style.backgroundColor = '';

            if (status === 'failed') {
                el.style.backgroundColor = '#f8d7da'; // red for failed
            }
        },

        columns: [
            ...(CAN_EDIT_DELETE ? [{
                formatter: "rowSelection",
                titleFormatter: "rowSelection",
                hozAlign: "center",
                headerSort: false,
                width: 50,
                headerTooltip: "Select rows",
            }] : []),
            {
                title: "Id",
                field: "id",
                visible: false,
                headerTooltip: "Id",
            },
            {
            title: "SL",
            field: "sl_no",
            headerTooltip: "Serial Number",
            formatter: function(cell) {
                return cell.getRow().getPosition(true) + 0;
            },
            hozAlign: "center",
            headerSort: false
            },
            {
              title: "Images",
              headerTooltip: "Images",
              field: "photos",
              // ✅ Enhanced formatter with fallback to `TransitContainerDetail.photos` or default image
              formatter: function(cell) {
                const row = cell.getRow().getData();
                let url = cell.getValue(); // primary from TransitContainerDetail.photos

                // Fallback 1: shopify image_src
                if (!url && row.image_src) {
                  url = row.image_src;
                }

                // Fallback 2: product_master.Values.image_path
                if (!url && row.Values) {
                  try {
                    const values = typeof row.Values === "string" ? JSON.parse(row.Values) : row.Values;
                    if (values.image_path) {
                      url = "/storage/" + values.image_path.replace(/^storage\//, "");
                    }
                  } catch (err) {
                    console.error("JSON parse error:", err);
                  }
                }

                if (!url) {
                  return '<span class="text-muted">No Image</span>';
                }

                return `<img src="${url}" data-preview="${url}" 
                style="height:40px;border-radius:4px;border:1px solid #ccc;cursor:zoom-in;">`;
              }
            },
            {
              title: "Parent",
              headerTooltip: "Parent",
              field: "parent",
              headerSort: true,
              hozAlign: "center",
              width: 72,
              formatter: function (cell) {
                  const v = String(cell.getValue() || "").trim();
                  if (!v) return '<span class="text-muted">—</span>';
                  return `<div title="${escapeHtmlTransit(v)}" style="font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;">${escapeHtmlTransit(v)}</div>`;
              },
              tooltip: function (cell) {
                  return String(cell.getValue() || "").trim();
              },
            },
            { 
              title: "Sku",
              headerTooltip: "SKU",
              field: "our_sku",
              formatter: function(cell) {
                const sku = cell.getValue() || '';
                const shopifyDomain = '{{ config("services.shopify.store_url") }}';
                // Use exact-SKU filter (`sku:"..."`) so Shopify Admin returns only the
                // specific variant being worked on, not every variant whose SKU contains
                // this string (e.g. "ABC" was previously also matching "ABC 2P").
                const shopifyUrl = shopifyDomain && sku
                    ? `https://${shopifyDomain}/admin/products/inventory?query=${encodeURIComponent('sku:"' + sku + '"')}`
                    : '#';
                const shopifyLink = sku ? `<a href="${shopifyUrl}" target="_blank" title="View exact SKU in Shopify" style="color: #28a745; text-decoration: none; font-size: 0.9rem;" onclick="event.stopPropagation();"><i class="fas fa-external-link-alt"></i></a>` : '';
                return `
                  <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>${sku}</span>
                    <i class="fas fa-copy copy-sku-icon" 
                       data-sku="${sku}" 
                       style="cursor: pointer; color: #2563eb; font-size: 0.9rem; transition: color 0.2s;"
                       title="Copy SKU">
                    </i>
                    ${shopifyLink}
                  </div>
                `;
              }
            },
            {
              title: "Stat",
              headerTooltip: "Status",
              field: "push_status",
              headerSort: true,
              hozAlign: "center",
              width: 80,
              // Sort order (ascending): not pushed → failed → processing → success
              // so user can immediately see what still needs attention at the top.
              sorter: function(a, b) {
                const order = { pending: 0, failed: 1, processing: 2, success: 3 };
                const av = order[a || 'pending'] ?? 0;
                const bv = order[b || 'pending'] ?? 0;
                return av - bv;
              },
              formatter: function(cell) {
                const status = cell.getValue() || 'pending';

                if (status === 'success') {
                  return '<i class="fas fa-check-circle" style="font-size:1.4rem; color:#16a34a;" title="Successfully pushed to Shopify"></i>';
                } else if (status === 'failed') {
                  return '<i class="fas fa-times-circle text-danger" style="font-size:1.4rem;" title="Push failed"></i>';
                } else if (status === 'processing') {
                  return '<i class="fas fa-spinner fa-spin text-primary" style="font-size:1.4rem;" title="Processing..."></i>';
                } else {
                  return '<i class="fas fa-clock text-muted" style="font-size:1.4rem;" title="Not pushed yet"></i>';
                }
              }
            },
            // { title: "Rec Qty", field: "rec_qty"},
            { title: "Qty/Ctns", field: "no_of_units", headerTooltip: "Quantity per Carton" },
            { title: "Qty Ctns", field: "total_ctn", headerTooltip: "Quantity of Cartons" },
            { 
              title: "Qty",
              headerTooltip: "Total Quantity",
              field: "pcs_qty", 
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  const pcsQty = parseFloat(data.pcs_qty);
                  if (pcsQty > 0) return pcsQty;
                  const units = parseFloat(data.no_of_units) || 0;
                  const ctn = parseFloat(data.total_ctn) || 0;
                  return units * ctn;
              }
            },
            { title: "Rate$", field: "rate", headerTooltip: "Rate ($)" },
            {
              title: "CBM",
              headerTooltip: "Cubic Meter (CBM)",
              field: "cbm",
              hozAlign: "center",
              width: 60,
              mutator: function(value, data) {
                  if (value !== undefined && value !== null && value !== '') return value;
                  let values = data.Values;
                  if (!values) return value;
                  if (typeof values === "string") {
                      try { values = JSON.parse(values); } catch(e) { return value; }
                  }
                  return values?.cbm ?? value;
              },
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  let values = data.Values;
                  let cbm = parseFloat(cell.getValue()) || 0;

                  if (!cbm && values) {
                      if (typeof values === "string") {
                          try { values = JSON.parse(values); } catch(e) { values = {}; }
                      }
                      cbm = parseFloat(values?.cbm) || 0;
                  }

                  if (cbm > 0) {
                      return `<span title="CBM: ${cbm.toFixed(3)}"
                                    style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                           background:#16a34a;cursor:default;"></span>`;
                  }
                  return `<span title="No CBM data"
                                style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                       background:#ef4444;cursor:default;"></span>`;
              },
              tooltip: function(cell) {
                  const data = cell.getRow().getData();
                  let values = data.Values;
                  let cbm = parseFloat(cell.getValue()) || 0;
                  if (!cbm && values) {
                      if (typeof values === "string") { try { values = JSON.parse(values); } catch(e) { values = {}; } }
                      cbm = parseFloat(values?.cbm) || 0;
                  }
                  return cbm > 0 ? 'CBM: ' + cbm.toFixed(3) : 'No CBM data';
              },
            },
            {
              title: "Unit",
              headerTooltip: "Unit",
              field: "unit",
              headerSort: false,
                hozAlign: "center",
                formatter: function (cell) {
                const value = cell.getValue();
                if (value === "pieces") return 'Pcs';
                if (value === "pair") return 'Pair';
                return value || '—';
                },
              },
            {
                title: "C link",
                headerTooltip: "Competitor Link",
                field: "Clink",
                headerSort: false,
                hozAlign: "center",
                formatter: transitClinkLinkFormatter,
            },
            {
                title: "Supplier",
                headerTooltip: "Supplier",
                field: "supplier_name",
                headerSort: false,
                hozAlign: "center",
                width: 72,
                minWidth: 56,
                maxWidth: 96,
                widthGrow: 0,
                formatter: function (cell) {
                    const saved = String(cell.getValue() || "").trim();
                    if (!saved) return '<span class="text-muted">—</span>';
                    const first = saved.split(/\s+/).filter(Boolean)[0] || saved;
                    return `<div title="${saved.replace(/"/g,'&quot;')}"
                                 style="font-weight:700;overflow:hidden;white-space:nowrap;
                                        text-overflow:ellipsis;max-width:100%;font-size:0.72rem;">
                                ${escapeHtmlTransit(first)}
                            </div>`;
                },
                tooltip: function (cell) {
                    return String(cell.getValue() || "").trim();
                },
            },
            {
                title: "Cat",
                headerTooltip: "Category",
                field: "Category",
                headerSort: false,
                hozAlign: "center",
                width: 72,
                formatter: function (cell) {
                    const v = String(cell.getValue() || "").trim();
                    if (!v) return '<span class="text-muted">—</span>';
                    const words = v.split(/\s+/).filter(Boolean);
                    const w1 = (words[0] || '').slice(0, 3);
                    const w2 = (words[1] || '').slice(0, 3);
                    const short = w2 ? (w1 + ' ' + w2) : w1;
                    const tip = escapeHtmlTransit(v).replace(/"/g, '&quot;');
                    return `<div title="${tip}" style="font-weight:700;white-space:nowrap;">${escapeHtmlTransit(short)}</div>`;
                },
                tooltip: function (cell) {
                    return String(cell.getValue() || "").trim();
                },
            },
            {
                title: "Imp",
                headerTooltip: "Imp name",
                field: "company_name",
                headerSort: false,
                hozAlign: "center",
                width: 56,
                formatter: function (cell) {
                    const v = String(cell.getValue() || "").trim();
                    if (!v) return '<span class="text-muted">—</span>';
                    return `<div title="${escapeHtmlTransit(v)}" style="font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;">${escapeHtmlTransit(v)}</div>`;
                },
                tooltip: function (cell) {
                    return String(cell.getValue() || "").trim() || 'Imp name';
                },
            },
            {
                title: "HSN",
                headerTooltip: "HSN Code",
                field: "hsn_code",
                headerSort: false,
                hozAlign: "center",
                width: 56,
                formatter: function (cell) {
                    const v = String(cell.getValue() || "").trim();
                    if (!v) return '<span class="text-muted">—</span>';
                    return `<div title="${escapeHtmlTransit(v)}" style="font-weight:700;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:100%;">${escapeHtmlTransit(v)}</div>`;
                },
                tooltip: function (cell) {
                    return String(cell.getValue() || "").trim() || 'HSN Code';
                },
            },
            {
                title: "CHG",
                headerTooltip: "Changes",
                field: "changes",
                hozAlign: "center",
                width: 60,
                formatter: function(cell) {
                    const val = (cell.getValue() || '').trim();
                    if (val) {
                        return `<span title="${val.replace(/"/g, '&quot;')}"
                                      style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                             background:#16a34a;cursor:default;"></span>`;
                    }
                    return `<span title="No data"
                                  style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                         background:#eab308;cursor:default;"></span>`;
                },
                tooltip: function(cell) {
                    return (cell.getValue() || '').trim() || 'No data';
                },
            },
            {
              title: "SPEC",
              headerTooltip: "Specifications",
              field: "specification",
              hozAlign: "center",
              width: 65,
              formatter: function(cell) {
                const val = (cell.getValue() || '').trim();
                if (val) {
                    return `<span title="${val.replace(/"/g, '&quot;')}"
                                  style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                         background:#16a34a;cursor:default;"></span>`;
                }
                return `<span title="No specification"
                              style="display:inline-block;width:14px;height:14px;border-radius:50%;
                                     background:#eab308;cursor:default;"></span>`;
              },
              tooltip: function(cell) {
                return (cell.getValue() || '').trim() || 'No specification';
              },
            },
            {
                title: '<i class="fas fa-history" title="History" aria-label="History"></i>',
                headerTooltip: "History",
                field: "last_saved_by",
                headerSort: false,
                hozAlign: "center",
                headerHozAlign: "center",
                width: 56,
                formatter: function(cell) {
                    const row  = cell.getRow().getData();
                    const name = (row.last_saved_by || '').trim();
                    const date = (row.last_saved_at || '').trim();
                    if (!name && !date) {
                        return '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#cbd5e1;cursor:pointer;" title="No history"></span>';
                    }
                    const tip = ((name || '—') + (date ? ' — ' + date : ''))
                        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                    return `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#2563eb;cursor:pointer;" title="${tip}"></span>`;
                },
                tooltip: function(cell) {
                    const row = cell.getRow().getData();
                    const name = (row.last_saved_by || '').trim();
                    const date = (row.last_saved_at || '').trim();
                    if (!name && !date) return 'No history';
                    return (name || '—') + (date ? ' — ' + date : '');
                },
                cellClick: function (e, cell) {
                    const sku = cell.getRow().getData().our_sku || '';
                    const input = document.getElementById('history-sku-filter');
                    if (input) input.value = sku;
                    const modalEl = document.getElementById('transitHistoryModal');
                    if (modalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                },
            },
            ...(CAN_EDIT_DELETE ? [{
                title: "Actions",
                headerTooltip: "Actions",
                headerSort: false,
                hozAlign: "center",
                width: 95,
                formatter: function() {
                    return `<div class="d-flex gap-1 justify-content-center">
                        <button type="button" class="btn btn-sm btn-outline-primary transit-edit-btn" title="Edit row">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger transit-delete-btn" title="Delete row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>`;
                },
                cellClick: function(e, cell) {
                    const target = e.target.closest('.transit-edit-btn, .transit-delete-btn');
                    if (!target) return;
                    e.stopPropagation();
                    const row = cell.getRow();
                    const rowId = row.getData().id;
                    if (target.classList.contains('transit-edit-btn')) {
                        openTransitEditModal(row, cell.getTable(), index);
                    } else if (target.classList.contains('transit-delete-btn') && rowId) {
                        deleteTransitRows(cell.getTable(), [rowId]);
                    }
                },
            }] : []),
        ],
    });

    if (CAN_EDIT_DELETE) {
        table.on("rowSelectionChanged", function(data) {
            if (data.length > 0) {
                $('#delete-selected-btn').removeClass('d-none');
            } else {
                $('#delete-selected-btn').addClass('d-none');
            }
        });
    }

    if (data.length === 0 && CAN_EDIT_DELETE) {
        table.addRow({ tab_name: tabName });
    }

    window.tabTables = window.tabTables || {};
    window.tabTables[index] = table;

    // Copy SKU functionality
    table.on("cellClick", function(e, cell) {
        const target = e.target;
        if (target && target.classList.contains('copy-sku-icon')) {
            const sku = target.getAttribute('data-sku');
            if (sku) {
                // Copy to clipboard
                navigator.clipboard.writeText(sku).then(function() {
                    // Visual feedback
                    const originalColor = target.style.color;
                    target.style.color = '#10b981';
                    target.classList.remove('fa-copy');
                    target.classList.add('fa-check');
                    
                    setTimeout(function() {
                        target.style.color = originalColor;
                        target.classList.remove('fa-check');
                        target.classList.add('fa-copy');
                    }, 1000);
                }).catch(function(err) {
                    console.error('Failed to copy SKU:', err);
                    alert('Failed to copy SKU to clipboard');
                });
            }
        }
    });

    // ✅ Ensure listener runs only once
    const exportBtn = document.getElementById("export-tab-excel");
    exportBtn.replaceWith(exportBtn.cloneNode(true));

    document.getElementById("export-tab-excel").addEventListener("click", function() {
        const activeTabPane = document.querySelector(".tab-pane.active");
        if (!activeTabPane) {
            alert("No active tab found!");
            return;
        }

        const tabIndex = Array.from(activeTabPane.parentElement.children).indexOf(activeTabPane);

        const table = window.tabTables[tabIndex];
        if (!table) {
            alert("No table found for the active tab!");
            return;
        }

        const data = table.getData();
        if (data.length === 0) {
            alert("No data to export for this tab.");
            return;
        }

        const exportData = data
          .filter(row => row.parent || row.our_sku)
          .map(row => {
              const pcsQty = parseFloat(row.pcs_qty);
              const qty = (pcsQty > 0) ? pcsQty : (parseFloat(row.no_of_units || 0) * parseFloat(row.total_ctn || 0));
              const savedSup = String(row.supplier_name || "").trim();
              const relatedSup = Array.isArray(row.supplier_names) ? row.supplier_names.filter(Boolean).join(", ") : "";
              let supplierCol = savedSup;
              if (relatedSup) {
                  supplierCol = savedSup ? savedSup + " | Related: " + relatedSup : "Related: " + relatedSup;
              }
              return {
                  "SKU": row.our_sku,
                  "Supplier": supplierCol,
                  "Qty / Ctns": row.no_of_units,
                  "Qty Ctns": row.total_ctn,
                  "Qty": qty,
                  "Rate ($)": row.rate,
                  "Amt ($)": Math.round(qty * parseFloat(row.rate || 0)),
                  "CBM": typeof row.Values === "string" ? JSON.parse(row.Values)?.cbm || 0 : row.Values?.cbm || 0,
                  "Unit": row.unit,
                  "C link": row.Clink || "",
                  "Changes": row.changes,
                  "Specifications": row.specification,
              };
          });

        const worksheet = XLSX.utils.json_to_sheet(exportData);

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Tab Data");

        const tabName = data[0]?.tab_name || `tab_${tabIndex + 1}`;
        XLSX.writeFile(workbook, `${tabName}_data.xlsx`);
    });

});

if (CAN_EDIT_DELETE) {
    $('#delete-selected-btn').on('click', function() {
        const activeTabPane = document.querySelector(".tab-pane.active");
        if (!activeTabPane) {
            alert("No active tab found!");
            return;
        }

        const tabIndex = Array.from(activeTabPane.parentElement.children).indexOf(activeTabPane);
        const table = window.tabTables[tabIndex];
        if (!table) {
            alert("No table found for the active tab!");
            return;
        }

        const selectedData = table.getSelectedData();
        if (selectedData.length === 0) {
            alert('Please select at least one record to delete.');
            return;
        }

        deleteTransitRows(table, selectedData.map(row => row.id));
    });
}

window.addEventListener("DOMContentLoaded", () => {
    document.documentElement.setAttribute("data-sidenav-size", "condensed");
    const firstTabIndex = 0;
    const table = window.tabTables && window.tabTables[firstTabIndex];
    if (table) {
        setTimeout(() => {
            updateActiveTabSummary(firstTabIndex, table);
        }, 300);
    }
});

//push inventory to inventory warehouse 
// document.getElementById("push-inventory-btn").addEventListener("click", async function () {
//     const activeTab = document.querySelector(".nav-link.active");
//     if (!activeTab) return alert("No container tab selected.");

//     const tabId = activeTab.getAttribute("data-bs-target"); 
//     const index = tabId.replace("#tab-", "");
//     const table = window.tabTables[index];
//     if (!table) return alert("No data found for this container.");

//     const selectedRows = table.getSelectedData();
//     if (selectedRows.length === 0) return alert("Please select at least one SKU to push.");

//     if (!confirm(`Are you sure you want to push ${selectedRows.length} selected SKU(s)?`)) return;

//     const tabName = activeTab.textContent.trim();

//     // Normalize SKUs before sending
//     const rowsToSend = selectedRows.map(r => ({
//         ...r,
//         our_sku: r.our_sku.trim().toUpperCase(),
//         row_id: r.id,
//         tab_name: tabName
//     }));

//     fetch("/inventory-warehouse/push", {
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json",
//             "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
//         },
//         body: JSON.stringify({ tab_name: tabName, data: rowsToSend })
//     })
//     .then(res => res.json())
//     .then(response => {
//         if (!response.success) return alert(response.message || "Push failed!");

//         // const pushedSkus = [];
//         // const skippedSkus = response.skipped || [];
//         // const notFoundSkus = response.not_found || [];
//         const pushed = response.pushed || [];
//         const skipped = response.skipped || [];
//         const notFound = response.not_found || [];


//         selectedRows.forEach(row => {
//             const tableRow = table.getRow(row.id);
//             if (!tableRow) return;

//             const id = row.id;

//             if (skippedIds.includes(id)) {
//                 tableRow.getElement().style.backgroundColor = "#f8d7da"; // red - skipped
//             } else if (notFoundIds.includes(id)) {
//                 tableRow.getElement().style.backgroundColor = "#fff3cd"; // yellow - not found
//             } else if (pushedIds.includes(id)) {
//                 tableRow.getElement().style.backgroundColor = "#d4edda"; // green - pushed
//                 tableRow.deselect();
//                 tableRow.update({ pushed: 1 });
//             }

//             // if (skippedSkus.includes(row.our_sku)) {
//             //     tableRow.getElement().style.backgroundColor = "#f8d7da"; // red
//             // } else if (notFoundSkus.includes(row.our_sku)) {
//             //     tableRow.getElement().style.backgroundColor = "#fff3cd"; // yellow for not found
//             // } else {
//             //     tableRow.getElement().style.backgroundColor = "#d4edda"; // green
//             //     tableRow.deselect();
//             //     tableRow.update({ pushed: 1 });
//             //     pushedSkus.push(row.our_sku);
//             // }
//         });

//         // Alert skipped SKUs
//         if (skippedIds.length > 0) {
//             alert("These rows were already pushed and skipped (row ids):\n" + skippedIds.join(", "));
//         }

//         // Alert not found SKUs
//         if (notFoundIds.length > 0) {
//             alert("These rows' SKUs were not found in Shopify (row ids):\n" + notFoundIds.join(", "));
//         }

//         // Redirect with pushed SKUs info
//         const query = pushedSkus.length > 0 ? `?pushed=${encodeURIComponent(pushedSkus.join(","))}` : "";
//         window.location.href = "/inventory-warehouse" + query;
//     })
//     .catch(err => {
//         console.error("Push error:", err);
//         alert("Something went wrong while pushing inventory.");
//     });
// });

// document.getElementById("push-inventory-btn").addEventListener("click", async function () {
//     const activeTab = document.querySelector(".nav-link.active");
//     if (!activeTab) return alert("No container tab selected.");

//     const tabId = activeTab.getAttribute("data-bs-target"); 
//     const index = tabId.replace("#tab-", "");
//     const table = window.tabTables[index];
//     if (!table) return alert("No data found for this container.");

//     const selectedRows = table.getSelectedData();
//     if (selectedRows.length === 0) return alert("Please select at least one SKU to push.");

//     if (!confirm(`Are you sure you want to push ${selectedRows.length} selected SKU(s)?`)) return;

//     const tabName = activeTab.textContent.trim();

//     const rowsToSend = selectedRows.map(r => ({
//         ...r,
//         our_sku: r.our_sku.trim().toUpperCase(),
//         row_id: r.id,
//         tab_name: tabName
//     }));

//     fetch("/inventory-warehouse/push", {
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json",
//             "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
//         },
//         body: JSON.stringify({ tab_name: tabName, data: rowsToSend })
//     })
//     .then(res => res.json())
//     .then(response => {
//         if (!response.success) return alert(response.message || "Push failed!");

//         const pushed = response.pushed || [];
//         const skipped = response.skipped || [];
//         const notFound = response.not_found || [];

//         // ✅ Apply colors row-wise
//         pushed.forEach(({ row_id }) => {
//             const row = table.getRow(row_id);
//             if (row) {
//                 row.getElement().style.backgroundColor = "#d4edda"; // green
//                 row.deselect();
//                 row.update({ pushed: 1 });
//             }
//         });

//         // Alerts with SKUs instead of IDs
//         if (skipped.length > 0)
//             alert("These SKUs were already pushed and skipped:\n" + skipped.join(", "));

//         if (notFound.length > 0)
//             alert("These SKUs were not found in Shopify:\n" + notFound.join(", "));

//         if (pushed.length > 0)
//             alert("Successfully pushed SKUs:\n" + pushed.map(r => r.sku).join(", "));
//     })
//     .catch(err => {
//         console.error("Push error:", err);
//         alert("Something went wrong while pushing inventory.");
//     });
// });

// Retry configuration for failed pushes — keeps retrying until success or until
// the safety cap is reached, so the user does not have to manually re-push.
const PUSH_MAX_ATTEMPTS = 8;     // hard safety cap to avoid an infinite loop
const PUSH_RETRY_BASE_MS = 800;  // initial backoff (doubles each attempt, capped)
const PUSH_RETRY_MAX_MS  = 5000; // max backoff between retries

function resolveTransitPushQty(row) {
    const pcsQty = parseFloat(row.pcs_qty);
    if (pcsQty > 0) return pcsQty;
    const units = parseFloat(row.no_of_units) || 0;
    const ctn = parseFloat(row.total_ctn) || 0;
    return units * ctn;
}

async function pushSingleWithRetry(row, tableRow, table, tabName, forceRepush) {
    const rowId = row.id;
    let lastError = null;
    const computedQty = resolveTransitPushQty(row);

    for (let attempt = 1; attempt <= PUSH_MAX_ATTEMPTS; attempt++) {
        tableRow.update({ push_status: 'processing' });
        table.redraw();

        try {
            const res = await fetch("/inventory-warehouse/push-single", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    tab_name: tabName,
                    force: forceRepush,
                    data: {
                        ...row,
                        our_sku: row.our_sku ? row.our_sku.trim().toUpperCase() : '',
                        id: rowId,
                        // Always send computed qty so fractional cartons (e.g. 0.33) are not lost
                        pcs_qty: computedQty
                    }
                })
            });

            const response = await res.json();

            if (response.status === 'success') {
                tableRow.update({ push_status: 'success', pushed: 1 });
                tableRow.deselect();
                return { status: 'success', attempts: attempt };
            }

            if (response.status === 'skipped') {
                tableRow.update({ push_status: 'success' });
                return { status: 'skipped', attempts: attempt };
            }

            lastError = response.message || 'Push failed';
        } catch (err) {
            console.error(`Error pushing SKU ${row.our_sku} (attempt ${attempt}):`, err);
            lastError = err && err.message ? err.message : 'Network error';
        }

        if (attempt < PUSH_MAX_ATTEMPTS) {
            const delay = Math.min(PUSH_RETRY_BASE_MS * Math.pow(2, attempt - 1), PUSH_RETRY_MAX_MS);
            console.warn(`Retrying SKU ${row.our_sku} in ${delay}ms (attempt ${attempt + 1}/${PUSH_MAX_ATTEMPTS})...`);
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }

    tableRow.update({ push_status: 'failed' });
    table.redraw();
    return { status: 'failed', attempts: PUSH_MAX_ATTEMPTS, error: lastError };
}

document.getElementById("push-inventory-btn").addEventListener("click", async function () {
    const activeTab = document.querySelector(".nav-link.active");
    if (!activeTab) {
        alert("⚠️ No container tab selected.");
        return;
    }

    const tabId = activeTab.getAttribute("data-bs-target");
    const index = tabId.replace("#tab-", "");
    const table = window.tabTables[index];
    if (!table) {
        alert("⚠️ No data found for this container.");
        return;
    }

    const selectedRows = table.getSelectedData();
    if (selectedRows.length === 0) {
        alert("⚠️ Please select at least one SKU to push.");
        return;
    }

    const alreadyPushedRows = selectedRows.filter(r => (r.push_status || 'pending') === 'success');
    const pendingRows       = selectedRows.filter(r => (r.push_status || 'pending') !== 'success');

    // Decide which rows to push and whether to force-repush
    let rowsToPush = pendingRows;
    let forceRepush = false;

    if (alreadyPushedRows.length > 0 && pendingRows.length === 0) {
        // All selected are already pushed — ask if they want to re-push
        if (!confirm(`All ${alreadyPushedRows.length} selected item(s) are already pushed.\n\nDo you want to re-push them to Shopify? (Inventory will be adjusted again)`)) return;
        rowsToPush = alreadyPushedRows;
        forceRepush = true;
    } else if (alreadyPushedRows.length > 0) {
        // Mix — ask whether to include already-pushed ones
        const includeAlready = confirm(
            `You selected ${selectedRows.length} item(s).\n` +
            `• ${pendingRows.length} pending/failed — will be pushed\n` +
            `• ${alreadyPushedRows.length} already pushed\n\n` +
            `Do you also want to RE-PUSH the already-pushed items?\n(Click OK to include them, Cancel to skip them)`
        );
        if (includeAlready) {
            rowsToPush = selectedRows;
            forceRepush = true;
        } else {
            if (!confirm(`Push ${pendingRows.length} pending item(s)? Continue?`)) return;
        }
    } else {
        if (!confirm(`Push ${pendingRows.length} item(s)? Continue?`)) return;
    }

    const tabName = activeTab.textContent.trim();
    const button = this;
    const originalText = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';

    let successCount = 0;
    let failedCount  = 0;
    let skippedCount = 0;
    const stillFailed = [];

    for (let i = 0; i < rowsToPush.length; i++) {
        const row    = rowsToPush[i];
        const rowId  = row.id;
        const tableRow = table.getRow(rowId);

        if (!tableRow) continue;

        const result = await pushSingleWithRetry(row, tableRow, table, tabName, forceRepush);

        if (result.status === 'success') {
            successCount++;
        } else if (result.status === 'skipped') {
            skippedCount++;
        } else {
            failedCount++;
            stillFailed.push({ row, tableRow, error: result.error });
        }

        table.redraw();

        if (i < rowsToPush.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    // Final sweep: if anything is still failed after the per-row retry budget,
    // give the user an option to keep retrying so the column eventually clears.
    while (stillFailed.length > 0) {
        const proceed = confirm(
            `${stillFailed.length} item(s) are still failing after ${PUSH_MAX_ATTEMPTS} attempts each.\n\n` +
            `Click OK to keep retrying these items, or Cancel to stop.`
        );
        if (!proceed) break;

        const retryQueue = stillFailed.splice(0, stillFailed.length);
        for (const item of retryQueue) {
            const result = await pushSingleWithRetry(item.row, item.tableRow, table, tabName, forceRepush);
            if (result.status === 'success') {
                successCount++;
                failedCount--;
            } else if (result.status === 'skipped') {
                skippedCount++;
                failedCount--;
            } else {
                stillFailed.push(item);
            }
            table.redraw();
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    button.disabled = false;
    button.innerHTML = originalText;

    let message = `Push completed!\n\n`;
    if (successCount > 0) message += `✅ Successfully pushed: ${successCount}\n`;
    if (skippedCount > 0) message += `⚠️ Skipped (already pushed): ${skippedCount}\n`;
    if (failedCount  > 0) message += `❌ Failed: ${failedCount}\n`;

    alert(message);
    updateActiveTabSummary(index, table);
});




//push arrived container to inventory warehouse 
document.getElementById("push-arrived-container-btn").addEventListener("click", function () {
    // Find the active tab index
    const activeTab = document.querySelector(".nav-link.active");
    if (!activeTab) {
        alert("No container tab selected.");
        return;
    }

    const tabId = activeTab.getAttribute("data-bs-target"); // e.g. #tab-0
    const index = tabId.replace("#tab-", ""); // get the index
    const table = window.tabTables[index];

    if (!table) {
        alert("No data found for this container.");
        return;
    }

    // Get selected data only
    const selectedData = table.getSelectedData();

    if (selectedData.length === 0) {
        alert("Please select at least one item to push to arrived container.");
        return;
    }

    // Confirm before pushing
    if (!confirm(`Are you sure you want to push ${selectedData.length} selected item(s) to arrived container?`)) {
        return;
    }

    // Send data to backend
    fetch("/arrived/container/push", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            tab_name: activeTab.textContent.trim(),
            data: selectedData
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.success) {
            let msg = "Selected items saved in Arrived Container successfully!";
            if (response.task && response.task.created) {
                msg += "\n\nTask created: " + (response.task.title || "Verify pricing Container") + " → Ritu (inventory@5core.com).";
            } else if (response.task && response.task.message) {
                msg += "\n\n" + response.task.message;
            }
            if (response.inv_verify_task && response.inv_verify_task.created) {
                msg += "\n\nTask created: " + (response.inv_verify_task.title || "inv Verify Container") + " → Ritu (inventory@5core.com).";
            } else if (response.inv_verify_task && response.inv_verify_task.message) {
                msg += "\n\n" + response.inv_verify_task.message;
            }
            if (response.qc_task && response.qc_task.created) {
                msg += "\n\nTask created: " + (response.qc_task.title || "QC Container") + " → Ritu (inventory@5core.com).";
            } else if (response.qc_task && response.qc_task.message) {
                msg += "\n\n" + response.qc_task.message;
            }
            alert(msg);
            window.location.reload();
        } else {
            alert(response.message || "Push failed!");
        }
    })
    .catch(err => {
        console.error("Push error:", err);
        alert("Something went wrong while pushing to Arrived Container.");
    });
});

document.getElementById('add-tab-btn').addEventListener('click', async function () {
    const tabName = prompt("Enter new container name:");
    if (!tabName || tabName.trim() === "") {
        alert("Tab name is required.");
        return;
    }

    const response = await fetch('/transit-container/add-tab', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify({ tab_name: tabName.trim() })
    });

    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Failed to create tab.');
        return;
    }

    location.reload();
});

function updateActiveTabSummary(index, table) {
  const data = table.getData();
  let totalCtn = 0;
  let totalQty = 0;
  let totalAmount = 0;
  let totalCBM = 0;

  data.forEach(row => {
        const ctn = parseFloat(row.total_ctn) || 0;
        const units = parseFloat(row.no_of_units) || 0;
        const rate = parseFloat(row.rate) || 0;
        const pcsQty = parseFloat(row.pcs_qty);
        const qty = (pcsQty > 0) ? pcsQty : (ctn * units);

        let cbmPerUnit = 0;
        if (row.Values) {
            try {
                const values = typeof row.Values === 'string' ? JSON.parse(row.Values) : row.Values;
                cbmPerUnit = parseFloat(values.cbm) || 0;
            } catch (e) {
                console.error("Invalid JSON in Values:", row.Values);
            }
        }

        const rowCBM = qty * cbmPerUnit;

        totalCtn += ctn;
        totalQty += qty;
        totalAmount += qty * rate;
        totalCBM += rowCBM;
    });

  document.getElementById("total-cartons-display").textContent = Math.round(totalCtn);
  document.getElementById("total-qty-display").textContent = Math.round(totalQty);
  document.getElementById("total-amount-display").textContent = Math.round(totalAmount);
  document.getElementById("total-cbm-display").textContent = Math.round(totalCBM);

}

document.querySelectorAll('[data-bs-toggle="tab"]').forEach((btn, index) => {
    btn.addEventListener("shown.bs.tab", () => {
        if (window.tabTables && window.tabTables[index]) {
            updateActiveTabSummary(index, window.tabTables[index]);
        }
    });
});

document.getElementById('search-input').addEventListener('input', function () {
    const searchValue = this.value.toLowerCase();

    const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
    if (!activeTab) return;

    const activeIndex = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]')).indexOf(activeTab);
    const activeTable = window.tabTables[activeIndex];

    if (activeTable) {
        const filterType = document.getElementById("filter-type").value;

        // Apply combined filters including search
        activeTable.setFilter(function(data) {
            let passFilterType = true;
            let passSearch = true;

            // Search filter
            if (searchValue) {
                const sku = (data.our_sku || "").toLowerCase();
                const supplier = (data.supplier_name || "").toLowerCase();
                const parent = (data.parent || "").toLowerCase();
                passSearch = sku.includes(searchValue) || supplier.includes(searchValue) || parent.includes(searchValue);
            }

            // Filter Type logic
            if (filterType === "new") {
                const parent = (data.parent || "").toUpperCase().trim();
                passFilterType = parent === "SOURCING";
            } else if (filterType === "changes") {
                const parent = (data.parent || "").toUpperCase().trim();
                passFilterType = parent !== "SOURCING";
            }

            return passFilterType && passSearch;
        });

        activeTable.redraw();
    }
});

  document.addEventListener("DOMContentLoaded", function () {
    // Function to apply all filters together
    function applyFilters() {
        const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
        if (!activeTab) return;

        const activeIndex = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]')).indexOf(activeTab);
        const activeTable = window.tabTables[activeIndex];

        if (!activeTable) {
            console.warn("No Tabulator instance found for index:", activeIndex);
            return;
        }

        const filterType = document.getElementById("filter-type").value;
        const searchValue = document.getElementById("search-input").value.toLowerCase();

        // Apply combined filters
        activeTable.setFilter(function(data) {
            let passFilterType = true;
            let passSearch = true;

            // Search filter
            if (searchValue) {
                const sku = (data.our_sku || "").toLowerCase();
                const supplier = (data.supplier_name || "").toLowerCase();
                const parent = (data.parent || "").toLowerCase();
                passSearch = sku.includes(searchValue) || supplier.includes(searchValue) || parent.includes(searchValue);
            }

            // Filter Type logic
            if (filterType === "new") {
                const parent = (data.parent || "").toUpperCase().trim();
                passFilterType = parent === "SOURCING";
            } else if (filterType === "changes") {
                const parent = (data.parent || "").toUpperCase().trim();
                passFilterType = parent !== "SOURCING";
            }

            return passFilterType && passSearch;
        });

        activeTable.redraw();
        console.log("Filtered data count:", activeTable.getDataCount("active"));
    }

    // Event listener for Filter Type
    document.getElementById("filter-type").addEventListener("change", applyFilters);

    document.addEventListener("mouseover", function(e) {
      if (e.target && e.target.dataset.preview) {
        const previewBox = document.getElementById("cell-image-preview");
        const img = previewBox.querySelector("img");
        img.src = e.target.dataset.preview;

        const rect = e.target.getBoundingClientRect(); 
        previewBox.style.left = (rect.right + 10) + "px"; 
        previewBox.style.top = rect.top + "px";

        previewBox.style.display = "block";
      }
    });

    document.addEventListener("mouseout", function(e) {
      if (e.target && e.target.dataset.preview) {
        const previewBox = document.getElementById("cell-image-preview");
        previewBox.style.display = "none";
      }
    });

    // Transit Container History: load when modal is shown or when Load is clicked
    function formatTransitHistoryDetails(actionType, details) {
      if (!details) return "—";

      try {
        const parsed = typeof details === "string" && details.trim().startsWith("{")
          ? JSON.parse(details)
          : details;

        if (!parsed || typeof parsed !== "object") {
          return String(details);
        }

        if (actionType === "row_deleted") {
          const parts = [];
          if (parsed.tab) parts.push("Container: " + parsed.tab);
          if (parsed.supplier_name) parts.push("Supplier: " + parsed.supplier_name);
          if (parsed.no_of_units || parsed.total_ctn) {
            parts.push("Qty/Ctns: " + (parsed.no_of_units ?? "—") + " × " + (parsed.total_ctn ?? "—"));
          }
          if (parsed.rate != null && parsed.rate !== "") parts.push("Rate: $" + parsed.rate);
          if (parsed.unit) parts.push("Unit: " + parsed.unit);
          if (parsed.changes) parts.push("Changes: " + parsed.changes);
          if (parsed.specification) parts.push("Spec: " + parsed.specification);
          if (parsed.restored_ready_to_ship) parts.push("Restored to Ready to Ship");
          return parts.length ? parts.join("; ") : JSON.stringify(parsed);
        }

        if (parsed.from && parsed.to && parsed.sku && !parsed.supplier_name) {
          return parsed.from + " → " + parsed.to;
        }

        if (parsed.tab_name && parsed.count) {
          return parsed.count + " item(s) in " + parsed.tab_name;
        }

        const fieldLabels = {
          tab_name: "Container",
          our_sku: "SKU",
          supplier_name: "Supplier",
          no_of_units: "Qty/Ctns",
          total_ctn: "Qty Ctns",
          rate: "Rate",
          unit: "Unit",
          cbm: "CBM",
          changes: "Changes",
          specification: "Spec",
          photos: "Photo",
          parent: "Parent",
          company_name: "Imp name",
          hsn_code: "HSN Code",
        };

        const parts = [];
        for (const k of Object.keys(parsed)) {
          const v = parsed[k];
          if (v && typeof v === "object" && "from" in v && "to" in v) {
            const label = fieldLabels[k] || k;
            parts.push(label + ": " + String(v.from ?? "—") + " → " + String(v.to ?? "—"));
          }
        }

        return parts.length ? parts.join("; ") : JSON.stringify(parsed);
      } catch (_) {
        return String(details);
      }
    }

    function loadTransitHistory() {
      const params = new URLSearchParams();
      const action = document.getElementById("history-action-filter").value;
      const tab = document.getElementById("history-tab-filter").value.trim();
      const sku = document.getElementById("history-sku-filter").value.trim();
      if (action) params.set("action_type", action);
      if (tab) params.set("tab_name", tab);
      if (sku) params.set("sku", sku);
      params.set("limit", "200");
      const tbody = document.getElementById("transit-history-tbody");
      tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';
      fetch("/transit-container/history?" + params.toString())
        .then(r => r.json())
        .then(res => {
          const data = res.data || [];
          const actionLabels = {
            row_created: "Row created",
            row_updated: "Row updated",
            row_moved: "Moved",
            row_deleted: "Row deleted",
            purchase_added: "Purchase added",
            tab_added: "Container added",
            push_inventory: "Push inventory",
            push_arrived: "Push arrived"
          };
          if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No history found.</td></tr>';
            return;
          }
          tbody.innerHTML = data.map(h => {
            const label = actionLabels[h.action_type] || h.action_type;
            const detailsStr = formatTransitHistoryDetails(h.action_type, h.details);
            return "<tr><td>" + h.created_at + "</td><td>" + label + "</td><td>" + (h.from_tab || "—") + "</td><td>" + (h.to_tab || "—") + "</td><td>" + (h.our_sku || "—") + "</td><td class=\"small\">" + detailsStr + "</td><td>" + (h.user_name || "—") + "</td></tr>";
          }).join("");
        })
        .catch(() => {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load history.</td></tr>';
        });
    }
    document.getElementById("transit-history-btn")?.addEventListener("click", loadTransitHistory);
    document.getElementById("history-refresh-btn")?.addEventListener("click", loadTransitHistory);
    document.getElementById("transitHistoryModal")?.addEventListener("show.bs.modal", function() { loadTransitHistory(); });

  });


document.body.style.zoom = "90%"; 

// Imp name / HSN Code dropdown options (persisted server-side; last selection remembered for next SKU)
window.TRANSIT_IMP_OPTIONS = @json($impNameOptions ?? ['5 core', 'K cube']);
window.TRANSIT_HSN_OPTIONS = @json($hsnCodeOptions ?? []);
window.TRANSIT_LAST_IMP = @json($lastImpName ?? '');
window.TRANSIT_LAST_HSN = @json($lastHsnCode ?? '');

function rebuildTransitOptionSelects(field, options, preferValue) {
    const selector = field === 'imp_name' ? '.transit-imp-select' : '.transit-hsn-select';
    const list = Array.isArray(options) ? options.slice() : [];
    document.querySelectorAll(selector).forEach(function (sel) {
        const current = preferValue != null && preferValue !== '' ? preferValue : sel.value;
        const keep = sel.value;
        sel.innerHTML = '<option value="">Select</option>';
        list.forEach(function (opt) {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            sel.appendChild(o);
        });
        if (current && list.indexOf(current) !== -1) {
            sel.value = current;
        } else if (keep && list.indexOf(keep) !== -1) {
            sel.value = keep;
        }
    });
    if (field === 'imp_name') {
        window.TRANSIT_IMP_OPTIONS = list;
        if (preferValue) window.TRANSIT_LAST_IMP = preferValue;
    } else {
        window.TRANSIT_HSN_OPTIONS = list;
        if (preferValue) window.TRANSIT_LAST_HSN = preferValue;
    }
}

function openTransitOptionModal(field) {
    const isImp = field === 'imp_name';
    document.getElementById('transit-option-field').value = field;
    document.getElementById('transitOptionModalLabel').textContent = isImp ? 'Add IMP name' : 'Add HSN Code';
    document.getElementById('transit-option-value-label').textContent = isImp ? 'IMP name' : 'HSN Code';
    document.getElementById('transit-option-value').value = '';
    const modalEl = document.getElementById('transitOptionModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        setTimeout(function () { document.getElementById('transit-option-value')?.focus(); }, 200);
    }
}

function saveTransitOptionModal() {
    const field = document.getElementById('transit-option-field')?.value || '';
    const value = (document.getElementById('transit-option-value')?.value || '').trim();
    if (!field || !value) {
        alert('Please enter a value.');
        return;
    }
    const btn = document.getElementById('transit-option-save-btn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...'; }
    fetch('/transit-container/dropdown-option', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ field: field, value: value })
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (!res || !res.success) {
            alert((res && res.message) || 'Failed to add option');
            return;
        }
        rebuildTransitOptionSelects(field, res.options || [], res.value || value);
        const modalEl = document.getElementById('transitOptionModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    })
    .catch(function (err) {
        console.error(err);
        alert('Failed to add option');
    })
    .finally(function () {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-plus me-1"></i> Add'; }
    });
}

document.getElementById('add-imp-name-btn')?.addEventListener('click', function () {
    openTransitOptionModal('imp_name');
});
document.getElementById('add-hsn-code-btn')?.addEventListener('click', function () {
    openTransitOptionModal('hsn_code');
});
document.getElementById('transit-option-save-btn')?.addEventListener('click', saveTransitOptionModal);

function getActiveTransitTableAndName() {
    const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
    if (!activeTab) return { table: null, tabName: '' };
    const tabs = Array.from(document.querySelectorAll('#tabList [data-bs-toggle="tab"]'));
    const index = tabs.indexOf(activeTab);
    const table = (window.tabTables && window.tabTables[index]) ? window.tabTables[index] : null;
    const tabName = (activeTab.textContent || '').trim();
    return { table: table, tabName: tabName, index: index };
}

function transitInvoiceRowQty(row) {
    const pcsQty = parseFloat(row.pcs_qty);
    if (!isNaN(pcsQty) && pcsQty > 0) return pcsQty;
    const units = parseFloat(row.no_of_units) || 0;
    const ctn = parseFloat(row.total_ctn) || 0;
    return units * ctn;
}

function openTransitInvoiceModal() {
    const ctx = getActiveTransitTableAndName();
    if (!ctx.table) {
        alert('No active container table found.');
        return;
    }

    // Prefer currently visible/filtered rows; fall back to all data
    let rows = [];
    try {
        rows = ctx.table.getData('active') || [];
    } catch (e) {
        rows = ctx.table.getData() || [];
    }
    rows = (rows || []).filter(function (r) {
        return String(r.our_sku || '').trim() !== '' || String(r.company_name || '').trim() !== '';
    });

    const tbody = document.getElementById('transit-invoice-tbody');
    const esc = (typeof escapeHtmlTransit === 'function')
        ? escapeHtmlTransit
        : function (s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

    let totalUnits = 0;
    let totalCtn = 0;
    let totalQty = 0;
    let html = '';

    if (!rows.length) {
        html = '<tr><td colspan="7" class="text-center text-muted">No items in this container.</td></tr>';
    } else {
        rows.forEach(function (row, i) {
            const units = parseFloat(row.no_of_units) || 0;
            const ctn = parseFloat(row.total_ctn) || 0;
            const qty = transitInvoiceRowQty(row);
            totalUnits += units;
            totalCtn += ctn;
            totalQty += qty;
            html += '<tr class="text-center">'
                + '<td>' + (i + 1) + '</td>'
                + '<td class="text-start">' + esc(row.our_sku || '—') + '</td>'
                + '<td>' + esc(row.company_name || '—') + '</td>'
                + '<td>' + esc(row.hsn_code || '—') + '</td>'
                + '<td>' + (units || '—') + '</td>'
                + '<td>' + (ctn || '—') + '</td>'
                + '<td>' + (qty || '—') + '</td>'
                + '</tr>';
        });
    }

    tbody.innerHTML = html;
    document.getElementById('invoice-container-name').textContent = ctx.tabName || '—';
    document.getElementById('invoice-date').textContent = new Date().toLocaleDateString(undefined, {
        year: 'numeric', month: 'short', day: '2-digit'
    });
    document.getElementById('invoice-item-count').textContent = String(rows.length);
    document.getElementById('invoice-total-units').textContent = String(Math.round(totalUnits * 1000) / 1000);
    document.getElementById('invoice-total-ctn').textContent = String(Math.round(totalCtn * 1000) / 1000);
    document.getElementById('invoice-total-qty').textContent = String(Math.round(totalQty * 1000) / 1000);

    const modalEl = document.getElementById('transitInvoiceModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

document.getElementById('transit-invoice-btn')?.addEventListener('click', openTransitInvoiceModal);
document.getElementById('transit-invoice-print-btn')?.addEventListener('click', function () {
    window.print();
});
document.getElementById('transit-option-value')?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        saveTransitOptionModal();
    }
});

// When Notes modal opens, default Imp/HSN to last remembered selection for next SKU import
document.getElementById('addItemModal')?.addEventListener('show.bs.modal', function () {
    rebuildTransitOptionSelects('imp_name', window.TRANSIT_IMP_OPTIONS, window.TRANSIT_LAST_IMP || '');
    rebuildTransitOptionSelects('hsn_code', window.TRANSIT_HSN_OPTIONS, window.TRANSIT_LAST_HSN || '');
});

// Auto-sync all containers to On Sea Transit (silent; also runs on save via backend autoSyncToOnSea)
function syncAllContainersSilent() {
    fetch('/transit-container/sync-all-to-on-sea', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    }).catch(function (error) {
        console.error('Auto sync to On Sea failed:', error);
    });
}
document.addEventListener('DOMContentLoaded', function () {
    syncAllContainersSilent();
});

</script>

<script>
  document.addEventListener("DOMContentLoaded", function () {
      const wrapper = document.getElementById("productRowsWrapper");
      const addBtn = document.getElementById("addItemRowBtn");

      addBtn.addEventListener("click", function () {
          const newRow = wrapper.querySelector(".product-row").cloneNode(true);

          newRow.querySelectorAll("input, select, textarea").forEach(el => {
              el.value = "";
          });

          // Prefill last remembered Imp / HSN for next SKU import
          const impSel = newRow.querySelector('.transit-imp-select');
          const hsnSel = newRow.querySelector('.transit-hsn-select');
          if (impSel && window.TRANSIT_LAST_IMP) impSel.value = window.TRANSIT_LAST_IMP;
          if (hsnSel && window.TRANSIT_LAST_HSN) hsnSel.value = window.TRANSIT_LAST_HSN;

          wrapper.appendChild(newRow);

          bindDeleteBtns();
      });

      function bindDeleteBtns() {
          wrapper.querySelectorAll(".delete-product-row-btn").forEach(btn => {
              btn.onclick = function () {
                  if (wrapper.querySelectorAll(".product-row").length > 1) {
                      btn.closest(".product-row").remove();
                  } else {
                      alert("At least one row is required.");
                  }
              };
          });
      }

      bindDeleteBtns();
  });
</script>

<script>
const productValues = {!! $productValuesMap !!};
console.log(productValues,'dfdf');


document.addEventListener("DOMContentLoaded", function () {

    $(document).on("change", ".sku-select", function () {

        let selectedSku = $(this).val();
        if (!selectedSku) return;

        // Normalize EXACTLY same way as controller
        selectedSku = selectedSku.toUpperCase().trim().replace(/\s+/g, ' ');
        console.log("Normalized SKU:", selectedSku);

        let row = $(this).closest(".product-row");

        let values = productValues[selectedSku];
        console.log("Matched Values:", values);

        if (!values) {
            row.find('input[name="cbm[]"]').val('');
            row.find('input[name="rate[]"]').val('');
            row.find('select[name="unit[]"]').val('');
            return;
        }

        row.find('input[name="cbm[]"]').val(values.cbm ?? '');
        row.find('input[name="rate[]"]').val(values.cp ?? '');
        // row.find('select[name="unit[]"]').val(values.unit ?? '');
        row.find('input[name="unit[]"]').val(values.unit ? values.unit.toLowerCase().trim() : '');

    });
});
</script>



@endsection
