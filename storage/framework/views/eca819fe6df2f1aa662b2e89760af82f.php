
<?php $__env->startSection('css'); ?>
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

</style>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('layouts.shared.page-title', ['page_title' => 'Transit Container INV', 'sub_title' => 'Transit Container INV'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

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

                    <!-- 🔍 Search Input -->
                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by SKU, Supplier, Parent..." 
                        style="max-width: 150px; border: 2px solid #2185ff; font-size: 0.95rem;">

                        <button id="export-tab-excel" class="btn btn-sm btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>

                    
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
                    

                    <a href="<?php echo e(url('product-master')); ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-plus-circle"></i> Add New Product
                    </a>


                    <button id="add-items-btn" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="fas fa-plus"></i> Add Items
                    </button>
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
                        <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="nav-item" style="flex-shrink: 0;">
                                <button class="nav-link <?php echo e($index == 0 ? 'active' : ''); ?>" id="tab-<?php echo e($index); ?>-tab" data-bs-toggle="tab" data-bs-target="#tab-<?php echo e($index); ?>" type="button" role="tab">
                                    <?php echo e($tab); ?>

                                </button>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>

                <!-- Tabs Content -->
                <div class="tab-content mt-3" id="tabContent">
                    <?php $__currentLoopData = $groupedData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tabName => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="tab-pane fade <?php echo e($loop->first ? 'show active' : ''); ?>" id="tab-<?php echo e($loop->index); ?>" role="tabpanel">
                            <div id="tabulator-<?php echo e($loop->index); ?>" class="tabulator-table"></div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                    <i class="fas fa-file-invoice me-2"></i> Add Items
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="purchaseOrderForm" method="POST" action="<?php echo e(url('transit-container/save')); ?>" enctype="multipart/form-data" autocomplete="off">
                <?php echo csrf_field(); ?>
                <div class="modal-body">
                    
                    <div>
                        <h5 class="fw-semibold mb-2 text-primary">
                            <i class="fas fa-boxes-stacked me-1"></i> Items
                        </h5>
                        <div class="row g-2">
                          <div class="col-md-3">
                              <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                              <select class="form-select" name="tab_name" required>
                                  <option value="" disabled selected>select container</option>
                                  <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                      <option value="<?php echo e($tab); ?>"><?php echo e($tab); ?></option>
                                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                              </select>
                          </div>
                        </div>
                        <div id="productRowsWrapper">
                            <div class="row g-2 product-row border rounded p-2 mt-2 position-relative">
                                <div class="d-flex justify-content-end position-absolute top-0 end-0 p-2 ">
                                    <i class="fas fa-trash-alt text-danger delete-product-row-btn" style="cursor: pointer; font-size: 1.2rem; margin-top:-10px;"></i>
                                </div>
                                
                                <div class="col-md-3">
                                    <select class="form-select sku-select" name="our_sku[]" required>
                                        <option value="" disabled selected>Select SKU</option>
                                        <?php $__currentLoopData = $skus; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sku): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($sku); ?>"><?php echo e($sku); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>  

                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Supplier</label>
                                    <select class="form-select" name="supplier_name[]">
                                        <option value="" disabled>Select Supplier</option>
                                        <?php $__currentLoopData = $suppliers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supplier): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($supplier->name); ?>"><?php echo e($supplier->name); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                                    <select class="form-select" name="unit[]">
                                        <option value="" disabled>select unit</option>
                                        <option value="pieces">pieces</option>
                                        <option value="pair">pair</option>
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

<?php $__env->stopSection(); ?>

<?php $__env->startSection('script'); ?>
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

let tabCounter = <?php echo e(count($tabs)); ?>;
const groupedData = <?php echo json_encode($groupedData, 15, 512) ?>;

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
            if (rowData.pushed == 1) {
                const cell = row.getCell("our_sku");
                if (cell) {
                    const el = cell.getElement();
                    el.style.boxShadow = "0 0 10px 2px #4CAF50"; // green shadow
                    el.style.borderRadius = "6px";
                    el.style.padding = "3px";
                }
            }
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
            { title: "Parent", field: "parent"},
            { title: "Sku", field: "our_sku" },
            { title: "Supplier", field: "supplier_name", editor: "input" },
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
            { title: "Rec Qty", field: "rec_qty"},
            { title: "Qty / Ctns", field: "no_of_units", editor: "input" },
            { title: "Qty Ctns", field: "total_ctn", editor: "input" },
            { 
              title: "Qty", 
              field: "pcs_qty", 
              editor: false,
              formatter: function(cell) {
                  const data = cell.getRow().getData();
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
                      return "0.00";
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
                  return cbm ? cbm.toFixed(2) : "0.00";
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
                  return '<span class="badge bg-primary" style="font-size:0.98rem;padding:6px 14px;border-radius:6px;">Pcs</span>';
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
            {
              title: "Amt($)", 
              field: "amount", 
              editor: false,
              mutator: false,  // Don't store in data
              formatter: function(cell) {
                const data = cell.getRow().getData();
                const rate = parseFloat(data.rate) || 0;
                const pcs_qty = parseFloat(data.no_of_units || 0) * parseFloat(data.total_ctn || 0);
                return Math.round(rate * pcs_qty);
              }
            },
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
              return {
                  "SKU": row.our_sku,
                  "Supplier": row.supplier_name,
                  "Qty / Ctns": row.no_of_units,
                  "Qty Ctns": row.total_ctn,
                  "Qty": (parseFloat(row.no_of_units || 0) * parseFloat(row.total_ctn || 0)),
                  "Rate ($)": row.rate,
                  "Amt ($)": Math.round((parseFloat(row.no_of_units || 0) * parseFloat(row.total_ctn || 0)) * parseFloat(row.rate || 0)),
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
    if (!activeTab) return alert("No container tab selected.");

    const tabId = activeTab.getAttribute("data-bs-target");
    const index = tabId.replace("#tab-", "");
    const table = window.tabTables[index];
    if (!table) return alert("No data found for this container.");

    const selectedRows = table.getSelectedData();
    if (selectedRows.length === 0) return alert("Please select at least one SKU to push.");

    if (!confirm(`Are you sure you want to push ${selectedRows.length} selected SKU(s)?`)) return;

    const tabName = activeTab.textContent.trim();
    const rowsToSend = selectedRows.map(r => ({
        ...r,
        our_sku: r.our_sku.trim().toUpperCase(),
        row_id: r.id,
        tab_name: tabName
    }));

    // Show processing overlay
    const overlay = document.createElement("div");
    overlay.id = "processing-overlay";
    overlay.innerHTML = `
        <div style="position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.6);color:white;display:flex;align-items:center;
            justify-content:center;flex-direction:column;z-index:9999;font-size:20px;">
            <div>🚀 Processing container...</div>
            <small>Please wait while we push inventory to Shopify.</small>
        </div>`;
    document.body.appendChild(overlay);

    try {
        const res = await fetch("/inventory-warehouse/push", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ tab_name: tabName, data: rowsToSend })
        });

        const response = await res.json();
        document.body.removeChild(overlay);

        if (!response.success) {
            alert(response.message || "Push failed!");
            return;
        }

        const pushed = response.pushed || [];
        const skipped = response.skipped || [];
        const notFound = response.not_found || [];

        pushed.forEach(({ row_id }) => {
            const row = table.getRow(row_id);
            if (row) {
                row.getElement().style.backgroundColor = "#d4edda"; // green
                row.deselect();
                row.update({ pushed: 1 });
            }
        });

        let msg = "";
        if (pushed.length) msg += `✅ Pushed successfully:\n${pushed.map(r => r.sku).join(", ")}\n\n`;
        if (skipped.length) msg += `⚠️ Already pushed (skipped):\n${skipped.join(", ")}\n\n`;
        if (notFound.length) msg += `❌ Not found in Shopify:\n${notFound.join(", ")}`;

        alert(msg || "Push complete!");
        location.reload(); // 🔄 Auto refresh

    } catch (err) {
        document.body.removeChild(overlay);
        console.error("Push error:", err);
        alert("Something went wrong while pushing inventory.");
    }
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
            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
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

        const qty = ctn * units;

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
  document.getElementById("total-cbm-display").textContent = totalCBM.toFixed(0);

}

document.querySelectorAll('[data-bs-toggle="tab"]').forEach((btn, index) => {
    btn.addEventListener("shown.bs.tab", () => {
        if (window.tabTables && window.tabTables[index]) {
            updateActiveTabSummary(index, window.tabTables[index]);
        }
    });
});

document.getElementById('search-input').addEventListener('input', function () {
    const value = this.value.toLowerCase();

    const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
    if (!activeTab) return;

    const activeIndex = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]')).indexOf(activeTab);
    const activeTable = window.tabTables[activeIndex];

    if (activeTable) {
        activeTable.setFilter([
            [
                { field: "our_sku", type: "like", value: value },
                { field: "supplier_name", type: "like", value: value },
                { field: "parent", type: "like", value: value }
            ]
        ]);
    }
});

  document.addEventListener("DOMContentLoaded", function () {
    document.getElementById("filter-type").addEventListener("change", function () {
        const selected = this.value;

        const activeTab = document.querySelector('.nav-link.active[data-bs-toggle="tab"]');
        if (!activeTab) return;

        const activeIndex = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]')).indexOf(activeTab);
        const activeTable = window.tabTables[activeIndex];

        if (!activeTable) {
            console.warn("No Tabulator instance found for index:", activeIndex);
            return;
        }

        if (selected === "new") {
            activeTable.setFilter((data) => {
                const parent = (data.parent || "").toUpperCase().trim();
                return parent === "SOURCING";
            });
        } else if (selected === "changes") {
            activeTable.setFilter((data) => {
                const parent = (data.parent || "").toUpperCase().trim();
                return parent !== "SOURCING";
            });
        } else {
            activeTable.clearFilter();
        }

        activeTable.redraw();
        console.log("Filtered data count:", activeTable.getDataCount("active"));
    });

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




<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.vertical', ['title' => 'Transit Container INV'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\5core_inventory\resources\views/purchase-master/transit_container/index.blade.php ENDPATH**/ ?>