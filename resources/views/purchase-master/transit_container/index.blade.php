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
    padding: 16px 10px;
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

</style>
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Transit Container INV', 'sub_title' => 'Transit Container INV'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                    <div class="d-flex gap-4 align-items-center">
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            📦 To. Ctns: <span class="text-success" id="total-cartons-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            🧮 To. Qty: <span class="text-primary" id="total-qty-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            💲 To. Amt: <span class="text-primary" id="total-amount-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            To. CBM: <span class="text-primary" id="total-cbm-display">0</span>
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

                    <!-- 🔽 Changes Column Filter Dropdown -->
                    <div class="d-flex align-items-center gap-2">
                        <label for="filter-changes" class="fw-semibold mb-0" style="font-size: 0.95rem;">Changes:</label>
                        <select id="filter-changes" class="form-select form-select-sm" style="width: 80px;">
                            <option value="">All</option>
                            <option value="old">Old</option>
                            <option value="new">New</option>
                        </select>
                    </div>

                    <!-- 🔍 Search Input -->
                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by SKU, Supplier, Parent..." 
                        style="max-width: 150px; border: 2px solid #2185ff; font-size: 0.95rem;">

                        <button id="export-tab-excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>

                    {{-- push Inventory --}}
                    <button id="push-inventory-btn" class="btn btn-primary btn-sm">
                        <i class="fas fa-dolly"></i> Push Inventory
                    </button>

                    <button id="push-arrived-container-btn" class="btn btn-info btn-sm">
                        Arrived Container
                    </button>

                    <!-- ➕ Add Container Button -->
                    <button id="add-tab-btn" class="btn btn-success btn-sm">
                        <i class="fas fa-plus"></i> Add Container
                    </button>
                    {{-- <button id="add-new-product-btn" class="btn btn-warning btn-sm" 
                        onclick="window.locationhref='product-master'">
                        <i class="fas fa-plus-circle"></i> New Product
                    </button> --}}

                    


                    <button id="add-items-btn" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus"></i> Add Notes
                    </button>
                    <a href="{{ url('product-master') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-plus-circle"></i> Add New Product
                    </a>
                    <button class="btn btn-danger btn-sm d-none" id="delete-selected-btn">
                        <i class="fas fa-trash me-1"></i> Delete
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
                <div class="tab-content mt-3" id="tabContent">
                    @foreach($groupedData as $tabName => $items)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $loop->index }}" role="tabpanel">
                            <div id="tabulator-{{ $loop->index }}" class="tabulator-table"></div>
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

Object.entries(groupedData).forEach(([tabName, data], index) => {
    let table = new Tabulator(`#tabulator-${index}`, {
        layout: "fitDataFill",
        data: data,
        pagination: "local",
        paginationSize: 50,
        height: "700px",
        rowHeight: 55,
        index: "id",
        selectable: true,
        rowFormatter: function(row) {
            const rowData = row.getData();

            // Ensure tab_name and SKU are normalized
            const tabName = (rowData.tab_name || '').trim().toUpperCase();
            const sku = (rowData.our_sku || '').trim().toUpperCase();
            const rowId = rowData.id;

            // The pushed flag should already be set in your controller for this exact combination
            // if (rowData.pushed == 1) {
            //     const cell = row.getCell("our_sku");
            //     if (cell) {
            //         const el = cell.getElement();
            //         el.style.boxShadow = "0 0 10px 2px #4CAF50"; // green shadow
            //         el.style.borderRadius = "6px";
            //         el.style.padding = "3px";
            //     }
            // }
        },

        columns: [{
                formatter: "rowSelection",
                titleFormatter: "rowSelection",
                hozAlign: "center",
                headerSort: false,
                width: 50
            },
            {
                title: "Id",
                field: "id",
                visible: false,
            },
            {
            title: "Sl No.",
            formatter: function(cell) {
                return cell.getRow().getPosition(true) + 0;
            },
            hozAlign: "center",
            headerSort: false
            },
            {
              title: "Images",
              field: "photos",
              editor: function(cell, onRendered, success, cancel) {
                const container = document.createElement("div");
                container.style.display = "flex";
                container.style.flexDirection = "column";

                const preview = document.createElement("div");
                preview.style.marginBottom = "6px";

                const input = document.createElement("input");
                input.type = "file";
                input.accept = "image/*";
                input.multiple = false;
                input.style.marginBottom = "6px";

                input.addEventListener("change", function (e) {
                  if (e.target.files.length > 0) {
                    handleUpload(e.target.files[0]);
                  }
                });

                container.appendChild(input);
                container.appendChild(preview);

                setTimeout(() => {
                  container.focus();
                }, 200);

                container.setAttribute("contenteditable", true);
                container.addEventListener("paste", function (e) {
                  e.preventDefault();
                  for (let item of e.clipboardData.items) {
                    if (item.type.indexOf("image") !== -1) {
                      const blob = item.getAsFile();
                      handleUpload(blob);
                    }
                  }
                });

                function handleUpload(file) {
                  const formData = new FormData();
                  formData.append("image", file);
                  formData.append("_token", document.querySelector('meta[name="csrf-token"]').content);

                  fetch("/upload-image", {
                    method: "POST",
                    body: formData,
                  })
                  .then(res => res.json())
                  .then(data => {
                    if (data.url) {
                      preview.innerHTML = `<img src="${data.url}" style="height: 50px;"/>`;
                      success(data.url);
                    } else {
                      alert("Upload failed.");
                      cancel();
                    }
                  })
                  .catch(err => {
                    console.error(err);
                    alert("Upload error.");
                    cancel();
                  });
                }

                return container;
              },

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
            // { title: "Parent", field: "parent"},
            { 
              title: "Sku", 
              field: "our_sku",
              formatter: function(cell) {
                const sku = cell.getValue() || '';
                return `
                  <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <span>${sku}</span>
                    <i class="fas fa-copy copy-sku-icon" 
                       data-sku="${sku}" 
                       style="cursor: pointer; color: #2563eb; font-size: 0.9rem; transition: color 0.2s;"
                       title="Copy SKU">
                    </i>
                  </div>
                `;
              }
            },
            // { title: "Supplier", field: "supplier_name", editor: "input" },
            {
              title: "Status",
              field: "push_status",
              headerSort: false,
              hozAlign: "center",
              width: 80,
              formatter: function(cell) {
                const status = cell.getValue() || 'pending';
                const rowData = cell.getRow().getData();
                
                if (status === 'success') {
                  return '<i class="fas fa-check-circle text-success" style="font-size: 1.2rem;" title="Successfully pushed"></i>';
                } else if (status === 'failed') {
                  return '<i class="fas fa-times-circle text-danger" style="font-size: 1.2rem;" title="Failed to push"></i>';
                } else if (status === 'processing') {
                  return '<i class="fas fa-spinner fa-spin text-primary" style="font-size: 1.2rem;" title="Processing..."></i>';
                } else {
                  return '<i class="fas fa-clock text-muted" style="font-size: 1.2rem;" title="Pending"></i>';
                }
              }
            },
            // { title: "Rec Qty", field: "rec_qty"},
            { title: "Qty / Ctns", field: "no_of_units", editor: "input" },
            { title: "Qty Ctns", field: "total_ctn", editor: "input" },
            { 
              title: "Qty", 
              field: "pcs_qty", 
              editor: false,
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  const pcsQty = parseFloat(data.pcs_qty);
                  if (pcsQty > 0) return pcsQty;
                  const units = parseFloat(data.no_of_units) || 0;
                  const ctn = parseFloat(data.total_ctn) || 0;
                  return units * ctn;
              }
            },
            { title: "Rate ($)", field: "rate", editor: "input" },
            { 
              title: "CBM", 
              field: "cbm", 
              editor: "input",
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  let values = data.Values;

                  if (!values) {
                      return "0.000";
                  }

                  if (typeof values === "string") {
                      try {
                          values = JSON.parse(values);
                      } catch (e) {
                          console.error("JSON parse error:", e, values);
                          values = {};
                      }
                  }

                  const cbm = parseFloat(values?.cbm) || 0;
                  return cbm ? cbm.toFixed(3) : "0.000";
              }
            },
            {
              title: "Unit",
              field: "unit",
              headerSort: false,
                hozAlign: "center",
                editor: function (cell, onRendered, success, cancel) {
                const value = cell.getValue();
                const select = document.createElement("select");
                select.className = "form-select form-select-sm";
                select.style.minWidth = "110px";
                select.style.padding = "4px 10px";
                select.style.height = "32px";
                select.style.borderRadius = "6px";
                select.style.border = "1px solid #cbd5e1";
                select.style.background = "#f8fafc";
                select.style.fontWeight = "500";
                select.style.fontSize = "1rem";

                const options = {
                  pieces: "Pieces",
                  pair: "Pair",
                };

                for (let key in options) {
                  const option = document.createElement("option");
                  option.value = key;
                  option.textContent = options[key];
                  select.appendChild(option);
                }

                select.value = value || "pieces";

                select.addEventListener("change", function () {
                  success(this.value);
                });

                select.addEventListener("blur", function () {
                  success(select.value);
                });

                onRendered(() => {
                  select.focus();
                  const event = new MouseEvent('mousedown', { bubbles: true });
                  select.dispatchEvent(event);
                });

                return select;
                },
                formatter: function (cell) {
                const value = cell.getValue();
                if (value === "pieces")
                  return '<span class="badge bg-info text-dark" style="font-size:0.98rem;padding:6px 14px;border-radius:6px;">Pcs</span>';
                if (value === "pair")
                  return '<span class="badge bg-info text-dark" style="font-size:0.98rem;padding:6px 14px;border-radius:6px;">Pair</span>';
                return `<span class="badge bg-secondary" style="font-size:0.98rem;padding:6px 14px;border-radius:6px;">${value || "—"}</span>`;
                },
                cellClick: function (e, cell) {
                cell.edit(true);
                },
                cellDblClick: function (e, cell) {
                cell.edit(true);
                },
              },
            // {
            //   title: "Amt($)", 
            //   field: "amount", 
            //   editor: false,
            //   mutator: false,  // Don't store in data
            //   formatter: function(cell) {
            //     const data = cell.getRow().getData();
            //     const rate = parseFloat(data.rate) || 0;
            //     const pcs_qty = parseFloat(data.no_of_units || 0) * parseFloat(data.total_ctn || 0);
            //     return Math.round(rate * pcs_qty);
            //   }
            // },
            { title: "Changes", field: "changes", editor: "input" },
            { 
              title: "Spec.",
              field: "specification", 
              editor: "input",
              formatter: function(cell) {
                const value = cell.getValue();
                return `<div title="${value?.replace(/"/g, '&quot;') ?? ''}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                          ${value ?? ''}
                        </div>`;
              }
            },
            {
                title: "Created By",
                field: "created_by_name",
                headerSort: false,
                hozAlign: "center",
                formatter: function(cell) {
                    const value = cell.getValue();
                    return `<span class="badge bg-secondary" style="padding: 6px 12px; font-size: 0.9rem;">
                                ${value || '—'}
                            </span>`;
                }
            },
        ],
    });

    table.on("rowSelectionChanged", function(data, rows){
        if(data.length > 0){
            $('#delete-selected-btn').removeClass('d-none');
        } else {
            $('#delete-selected-btn').addClass('d-none');
        }
    });

    $('#delete-selected-btn').off('click').on('click', function() {
        // Find active tab
        const activeTabPane = document.querySelector(".tab-pane.active");
        if (!activeTabPane) {
            alert("No active tab found!");
            return;
        }

        // Find tab index & table
        const tabIndex = Array.from(activeTabPane.parentElement.children).indexOf(activeTabPane);
        const table = window.tabTables[tabIndex];
        if (!table) {
            alert("No table found for the active tab!");
            return;
        }

        // Get selected rows
        const selectedData = table.getSelectedData();
        if (selectedData.length === 0) {
            alert('Please select at least one record to delete.');
            return;
        }

        // Confirm delete
        if (!confirm(`Are you sure you want to delete ${selectedData.length} selected records?`)) {
            return;
        }

        const ids = selectedData.map(row => row.id);

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
                } else {
                    alert("Failed to delete rows.");
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
            }
        });
    });



    window.addEventListener("DOMContentLoaded", () => {
      document.documentElement.setAttribute("data-sidenav-size", "condensed");
        const firstTabIndex = 0;
        const table = window.tabTables[firstTabIndex];
        if (table) {
            setTimeout(() => {
                updateActiveTabSummary(firstTabIndex, table);
            }, 300);
        }
    });

    if (data.length === 0) {
        table.addRow({ tab_name: tabName });
    }

    table.on("cellEdited", function(cell) {
        const row = cell.getRow();
        const data = row.getData();
        data.tab_name = tabName;
        const field = cell.getField();

        if (["no_of_units", "total_ctn"].includes(field)) {
            const units = parseFloat(data.no_of_units) || 0;
            const ctn = parseFloat(data.total_ctn) || 0;
            const pcs_qty = units * ctn;
            row.update({ pcs_qty: pcs_qty });

            const rate = parseFloat(data.rate) || 0;
            const amount = rate * pcs_qty;
            row.update({ amount: amount });
        }

        if (["rate", "pcs_qty"].includes(field)) {
            const rate = parseFloat(data.rate) || 0;
            const qty = parseFloat(data.pcs_qty) || 0;
            const amount = rate * qty;
            row.update({ amount: amount });
        }

        fetch('/transit-container/save-row', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.success || response.id) {
                console.log("Row saved successfully:", response);
                if (response.id) {
                    row.update({ id: response.id }); 
                }
            } else {
                alert(response.message || "Update failed");
            }
        })
        .catch(err => {
            console.error("Save error:", err);
            alert("Something went wrong while saving");
        });

        updateActiveTabSummary(index, table);
    });

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
              return {
                  "SKU": row.our_sku,
                  "Supplier": row.supplier_name,
                  "Qty / Ctns": row.no_of_units,
                  "Qty Ctns": row.total_ctn,
                  "Qty": qty,
                  "Rate ($)": row.rate,
                  "Amt ($)": Math.round(qty * parseFloat(row.rate || 0)),
                  "CBM": typeof row.Values === "string" ? JSON.parse(row.Values)?.cbm || 0 : row.Values?.cbm || 0,
                  "Unit": row.unit,
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

    // Filter out already pushed items (status = 'success')
    const rowsToPush = selectedRows.filter(row => {
        const status = row.push_status || 'pending';
        return status !== 'success';
    });

    const alreadyPushedRows = selectedRows.filter(row => {
        const status = row.push_status || 'pending';
        return status === 'success';
    });

    // Show message if all selected items are already pushed
    if (rowsToPush.length === 0) {
        alert("⚠️ All selected items are already pushed successfully!");
        return;
    }

    // Show message if some items are already pushed
    let confirmMessage = `You have selected ${selectedRows.length} item(s).\n`;
    if (alreadyPushedRows.length > 0) {
        confirmMessage += `⚠️ ${alreadyPushedRows.length} item(s) are already pushed and will be skipped.\n`;
    }
    confirmMessage += `\nOnly ${rowsToPush.length} item(s) will be pushed.\n\nContinue?`;

    if (!confirm(confirmMessage)) return;

    const tabName = activeTab.textContent.trim();
    const button = this;
    const originalText = button.innerHTML;
    
    // Disable button during processing
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';

    let successCount = 0;
    let failedCount = 0;
    let skippedCount = alreadyPushedRows.length; // Count already pushed items as skipped

    // Process items one by one (only failed/pending items)
    for (let i = 0; i < rowsToPush.length; i++) {
        const row = rowsToPush[i];
        const rowId = row.id;
        const tableRow = table.getRow(rowId);
        
        if (!tableRow) continue;

        // Update status to processing
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
                    data: {
                        ...row,
                        our_sku: row.our_sku ? row.our_sku.trim().toUpperCase() : '',
                        id: rowId
                    }
                })
            });

            const response = await res.json();

            // Update status based on response
            if (response.status === 'success') {
                tableRow.update({ 
                    push_status: 'success',
                    pushed: 1
                });
                tableRow.getElement().style.backgroundColor = "#d4edda";
                tableRow.deselect();
                successCount++;
            } else if (response.status === 'skipped') {
                // This should not happen since we filtered, but handle it anyway
                tableRow.update({ push_status: 'success' });
                tableRow.getElement().style.backgroundColor = "#fff3cd";
                skippedCount++;
            } else {
                tableRow.update({ push_status: 'failed' });
                tableRow.getElement().style.backgroundColor = "#f8d7da";
                failedCount++;
            }

            table.redraw();

        } catch (err) {
            console.error(`Error pushing SKU ${row.our_sku}:`, err);
            tableRow.update({ push_status: 'failed' });
            tableRow.getElement().style.backgroundColor = "#f8d7da";
            failedCount++;
            table.redraw();
        }

        // Small delay between items to avoid overwhelming the server
        if (i < rowsToPush.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    }

    // Re-enable button
    button.disabled = false;
    button.innerHTML = originalText;

    // Show summary
    let message = `Push completed!\n\n`;
    if (successCount > 0) {
        message += `✅ Successfully pushed: ${successCount}\n`;
    }
    if (skippedCount > 0) {
        message += `⚠️ Already pushed (skipped): ${skippedCount}\n`;
    }
    if (failedCount > 0) {
        message += `❌ Failed: ${failedCount}\n`;
    }
    
    if (successCount === 0 && failedCount === 0 && skippedCount > 0) {
        message = `⚠️ All selected items were already pushed successfully!\n\nNo items were processed.`;
    }

    alert(message);
    
    // Update summary totals
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
            alert("Selected items saved in Arrived Container successfully!");
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

  document.getElementById("total-cartons-display").textContent = totalCtn;
  document.getElementById("total-qty-display").textContent = totalQty;
  document.getElementById("total-amount-display").textContent = Math.round(totalAmount);
  document.getElementById("total-cbm-display").textContent = totalCBM.toFixed(3);

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
        const filterChanges = document.getElementById("filter-changes").value;

        // Apply combined filters including search
        activeTable.setFilter(function(data) {
            let passFilterType = true;
            let passFilterChanges = true;
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

            // Changes column filter logic
            if (filterChanges === "old") {
                const changes = (data.changes || "").toLowerCase().trim();
                passFilterChanges = changes.includes("old");
            } else if (filterChanges === "new") {
                const changes = (data.changes || "").toLowerCase().trim();
                passFilterChanges = changes.includes("new");
            }

            return passFilterType && passFilterChanges && passSearch;
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
        const filterChanges = document.getElementById("filter-changes").value;
        const searchValue = document.getElementById("search-input").value.toLowerCase();

        // Apply combined filters
        activeTable.setFilter(function(data) {
            let passFilterType = true;
            let passFilterChanges = true;
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

            // Changes column filter logic
            if (filterChanges === "old") {
                const changes = (data.changes || "").toLowerCase().trim();
                passFilterChanges = changes.includes("old");
            } else if (filterChanges === "new") {
                const changes = (data.changes || "").toLowerCase().trim();
                passFilterChanges = changes.includes("new");
            }

            return passFilterType && passFilterChanges && passSearch;
        });

        activeTable.redraw();
        console.log("Filtered data count:", activeTable.getDataCount("active"));
    }

    // Event listener for Filter Type
    document.getElementById("filter-type").addEventListener("change", applyFilters);

    // Event listener for Changes filter
    document.getElementById("filter-changes").addEventListener("change", applyFilters);

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

  });


document.body.style.zoom = "90%"; 

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
