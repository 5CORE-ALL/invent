@extends('layouts.vertical', ['title' => 'Arrived Container'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Arrived Container', 'sub_title' => 'Arrived Container'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                    <div class="d-flex gap-4 align-items-center">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'arrived_container'])
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            📦 Ctns: <span class="text-success" id="total-cartons-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            🧮 Qty: <span class="text-primary" id="total-qty-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            💲 Amt: <span class="text-primary" id="total-amount-display">0</span>
                        </div>
                        <div class="fw-semibold text-dark" style="font-size: 1rem;">
                            CBM: <span class="text-primary" id="total-cbm-display">0</span>
                        </div> 
                        <div class="d-flex align-items-center gap-1">
                            <label for="container-quick-search" class="fw-semibold mb-0" style="font-size: 0.95rem;">C #</label>
                            <input type="text" id="container-quick-search" class="form-control form-control-sm" placeholder="No."
                                style="width: 72px; border: 2px solid #2185ff; font-size: 0.95rem;" inputmode="numeric" autocomplete="off">
                        </div>
                    </div>

                    <!-- 🔍 Search Input -->
                    <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search by SKU, Supplier, Parent..." 
                        style="max-width: 180px; border: 2px solid #2185ff; font-size: 0.95rem;">

                    <button id="export-tab-excel" class="btn btn-sm btn-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="arrived-history-btn" data-bs-toggle="modal" data-bs-target="#arrivedHistoryModal">
                        <i class="fas fa-history me-1"></i> History
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
                                <button class="nav-link {{ $index == 0 ? 'active' : '' }}" id="tab-{{ $index }}-tab" data-bs-toggle="tab" data-bs-target="#tab-{{ $index }}" type="button" role="tab" data-tab-name="{{ $tab }}">
                                    {{ preg_replace('/^Container\s+/i', 'C ', $tab) }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Tabs Content -->
                <div class="tab-content mt-3" id="tabContent">
                    @foreach($tabs as $index => $tab)
                        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="tab-{{ $index }}" role="tabpanel" data-tab-name="{{ $tab }}">
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

@include('purchase-master.partials.arrived-po-olink-edit')

<div class="modal fade" id="arrivedHistoryModal" tabindex="-1" aria-labelledby="arrivedHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="arrivedHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> Arrived Container History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <select id="arrived-history-action-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All actions</option>
                        <option value="row_created">Row created</option>
                        <option value="row_updated">Row updated</option>
                        <option value="row_moved">Moved tab</option>
                        <option value="pushed_from_transit">Pushed from transit</option>
                    </select>
                    <input type="text" id="arrived-history-tab-filter" class="form-control form-control-sm" placeholder="Tab name" style="width: 140px;">
                    <input type="text" id="arrived-history-sku-filter" class="form-control form-control-sm" placeholder="SKU" style="width: 120px;">
                    <button type="button" id="arrived-history-refresh-btn" class="btn btn-primary btn-sm"><i class="fas fa-sync-alt me-1"></i> Load</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>From tab</th>
                                <th>To tab</th>
                                <th>SKU</th>
                                <th>Details</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody id="arrived-history-tbody">
                            <tr><td colspan="7" class="text-center text-muted">Open modal or click Load to fetch history.</td></tr>
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
<script>
document.body.style.zoom = "80%";
let tabCounter = {{ count($tabs) }};
const tabs = @json($tabs);
const groupedData = @json($groupedData);
const purchaseOrdersPageUrl = @json(route('list-all-purchase-orders'));
const comparisonSheetPageUrl = @json(route('comparison.sheet.page'));

function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function arrivedClinkFormatter(cell) {
    const url = String(cell.getValue() || '').trim();
    if (!url) return '<span class="text-muted">—</span>';
    return `<div style="display:flex;align-items:center;justify-content:center;">
        <a href="${escHtml(url)}" target="_blank" rel="noopener noreferrer"
            class="btn btn-sm btn-outline-primary"
            title="Open link" aria-label="Open link">
            <i class="fas fa-link"></i>
        </a>
    </div>`;
}

function arrivedOlinkFormatter(cell) {
    const url = String(cell.getValue() || '').trim();
    if (!url) return '<span class="text-muted">—</span>';
    return `<div style="display:flex;align-items:center;justify-content:center;">
        <a href="${escHtml(url)}" target="_blank" rel="noopener noreferrer"
            class="btn btn-sm btn-outline-primary"
            title="${escHtml(url)}" aria-label="Open order link">
            <i class="fas fa-external-link-alt"></i>
        </a>
    </div>`;
}

tabs.forEach((tabName, index) => {
    const data = groupedData[tabName] || [];
    let table = new Tabulator(`#tabulator-${index}`, {
        layout: "fitDataFill",
        data: data,
        pagination: "local",
        paginationSize: 50,
        height: "700px",
        rowHeight: 55,
        index: "id",
        selectable: true,
        columns: [
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
            {
              title: "Supp.",
              field: "supplier_name",
              headerTooltip: "Supplier (same as Forecast)",
              width: 72,
              minWidth: 56,
              maxWidth: 96,
              widthGrow: 0,
              hozAlign: "center",
              editor: false,
              formatter: function(cell) {
                const value = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                const esc = function(s) {
                  return String(s == null ? '' : s)
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
                };
                if (!value) return '-';
                const display = esc(value.split(/\s+/).filter(Boolean)[0] || value);
                let color = '#212529';
                if (value.toUpperCase() === 'FIND') {
                  color = '#eab308';
                } else {
                  let h = 0;
                  for (let i = 0; i < value.length; i++) h = (h * 31 + value.charCodeAt(i)) % 360;
                  color = 'hsl(' + h + ', 70%, 40%)';
                }
                return '<span title="' + esc(value) + '" style="color:' + color + ';font-weight:700;font-size:0.72rem;white-space:nowrap;">' + display + '</span>';
              }
            },
            {
              title: "Images",
              field: "photos",
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
            { title: "Qty / Ctns", field: "no_of_units", editor: "false" },
            { title: "Qty Ctns", field: "total_ctn", editor: "false" },
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
            { title: "Rate ($)", field: "rate", editor: "false" },
            { 
              title: "CBM", 
              field: "cbm", 
              editor: "false",
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
                  return cbm.toFixed(3);
              }
            },
            {
              title: "Unit",
              field: "unit",
              headerSort: false,
              headerTooltip: "Unit from CP Master",
              hozAlign: "center",
              editor: false,
              formatter: function (cell) {
                const value = String(cell.getValue() || '').trim();
                if (!value) return '—';
                // Same display as CP Master datatable
                if (value.toLowerCase() === 'pieces') return 'PCs';
                return value;
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
            { title: "Changes", field: "changes", editor: "false" },
            { 
              title: "Spec.",
              field: "specification", 
              editor: "false",
              formatter: function(cell) {
                const value = cell.getValue();
                return `<div title="${value?.replace(/"/g, '&quot;') ?? ''}" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                          ${value ?? ''}
                        </div>`;
              }
            },
            {
              title: "CD",
              field: "cd_link",
              hozAlign: "center",
              headerSort: false,
              width: 56,
              headerTooltip: "Comparison Data — open comparison page for this SKU",
              formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const sku = String(d.our_sku || '').trim();
                if (!sku) {
                  return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                }
                const hasSheet = !!d.has_sheet_data;
                const color = hasSheet ? '#16a34a' : '#dc2626';
                const title = hasSheet ? 'View/edit comparison sheet' : 'No comparison data — open to add';
                const safeSku = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                return `<div style="display:flex;align-items:center;justify-content:center;">
                  <button type="button" class="arrived-cd-open border-0 bg-transparent p-0" data-sku="${safeSku}" title="${title}" aria-label="${title}" style="line-height:1;cursor:pointer;">
                    <i class="mdi mdi-magnify" style="color:${color};font-size:18px;"></i>
                  </button>
                </div>`;
              }
            },
            {
              title: "C link",
              headerTooltip: "Competitor Link (from Forecast)",
              field: "Clink",
              headerSort: false,
              hozAlign: "center",
              width: 64,
              formatter: arrivedClinkFormatter,
            },
            {
              title: "PO",
              field: "po_number",
              headerSort: true,
              headerTooltip: "PO Number (from list-all-purchase-orders)",
              hozAlign: "center",
              minWidth: 110,
              formatter: function(cell) {
                const po = String(cell.getValue() || '').trim();
                if (!po) return '—';
                const href = purchaseOrdersPageUrl + '?po=' + encodeURIComponent(po);
                return `<a href="${escHtml(href)}" target="_blank" rel="noopener" title="Open in Purchase Orders">${escHtml(po)}</a>`;
              }
            },
            {
              title: "O link",
              headerTooltip: "Order link",
              field: "order_link",
              headerSort: false,
              hozAlign: "center",
              width: 70,
              formatter: arrivedOlinkFormatter,
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
            (typeof window.arrivedPoOlinkActionsColumn === 'function'
                ? window.arrivedPoOlinkActionsColumn({ width: 70 })
                : {
                    title: "Actions",
                    headerSort: false,
                    hozAlign: "center",
                    width: 70,
                    formatter: function() { return '—'; }
                }),

        ],
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

        fetch('/arrived/container/save-row', {
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
                  "C link": row.Clink || "",
                  "Supplier": row.supplier_name,
                  "Qty / Ctns": row.no_of_units,
                  "Qty Ctns": row.total_ctn,
                  "Qty": (parseFloat(row.no_of_units || 0) * parseFloat(row.total_ctn || 0)),
                  "Rate ($)": row.rate,
                  "Amt ($)": Math.round((parseFloat(row.no_of_units || 0) * parseFloat(row.total_ctn || 0)) * parseFloat(row.rate || 0)),
                  "CBM": typeof row.Values === "string" ? JSON.parse(row.Values)?.cbm || 0 : row.Values?.cbm || 0,
                  "Unit": row.unit,
                  "Changes": row.changes,
                  "Specification": row.specification,
                  "PO": row.po_number || "",
                  "O link": row.order_link || "",
              };
          });

        const worksheet = XLSX.utils.json_to_sheet(exportData);

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Tab Data");

        const tabName = data[0]?.tab_name || `tab_${tabIndex + 1}`;
        XLSX.writeFile(workbook, `${tabName}_data.xlsx`);
    });

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

  document.getElementById("total-cartons-display").textContent = Math.round(totalCtn);
  document.getElementById("total-qty-display").textContent = Math.round(totalQty);
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

function getContainerNumberFromTabName(tabName) {
    const match = String(tabName || '').match(/(\d+)/);
    return match ? match[1] : '';
}

function applyContainerQuickSearch(query) {
    const q = String(query || '').trim();
    const tabButtons = Array.from(document.querySelectorAll('#tabList [data-bs-toggle="tab"]'));
    let matchBtn = null;

    tabButtons.forEach(function(btn) {
        const num = getContainerNumberFromTabName(btn.dataset.tabName);
        const navItem = btn.closest('.nav-item');
        const visible = !q || num === q || num.startsWith(q);

        if (navItem) {
            navItem.style.display = visible ? '' : 'none';
        }

        if (visible && !matchBtn) {
            matchBtn = btn;
        }
        if (q && num === q) {
            matchBtn = btn;
        }
    });

    if (matchBtn && q) {
        bootstrap.Tab.getOrCreateInstance(matchBtn).show();
        matchBtn.scrollIntoView({ inline: 'nearest', behavior: 'smooth', block: 'nearest' });
    }
}

const containerQuickSearch = document.getElementById('container-quick-search');
if (containerQuickSearch) {
    containerQuickSearch.addEventListener('input', function() {
        applyContainerQuickSearch(this.value);
    });
    containerQuickSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            applyContainerQuickSearch('');
        }
    });
}

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

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.arrived-cd-open');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const sku = String(btn.getAttribute('data-sku') || '').trim();
    if (!sku) return;
    window.open(comparisonSheetPageUrl + '?sku=' + encodeURIComponent(sku), '_blank', 'noopener');
});


  document.addEventListener("DOMContentLoaded", function () {
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

    function loadArrivedHistory() {
        const params = new URLSearchParams();
        const action = document.getElementById("arrived-history-action-filter").value;
        const tab = document.getElementById("arrived-history-tab-filter").value.trim();
        const sku = document.getElementById("arrived-history-sku-filter").value.trim();
        if (action) params.set("action_type", action);
        if (tab) params.set("tab_name", tab);
        if (sku) params.set("sku", sku);
        params.set("limit", "200");
        const tbody = document.getElementById("arrived-history-tbody");
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';
        fetch("/arrived/container/history?" + params.toString())
            .then(r => r.json())
            .then(res => {
                const data = res.data || [];
                const actionLabels = {
                    row_created: "Row created",
                    row_updated: "Row updated",
                    row_moved: "Moved tab",
                    pushed_from_transit: "Pushed from transit"
                };
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No history found.</td></tr>';
                    return;
                }
                tbody.innerHTML = data.map(h => {
                    const label = actionLabels[h.action_type] || h.action_type;
                    let detailsStr = "—";
                    if (h.details) {
                        try {
                            const parsed = typeof h.details === "string" && h.details.trim().startsWith("{") ? JSON.parse(h.details) : h.details;
                            if (parsed && typeof parsed === "object") {
                                if (parsed.from && parsed.to && !parsed.transit_container_id) detailsStr = parsed.from + " → " + parsed.to;
                                else if (parsed.transit_container_id != null) detailsStr = "Transit ID " + parsed.transit_container_id + (parsed.sku ? " · " + parsed.sku : "");
                                else {
                                    const parts = [];
                                    for (const k of Object.keys(parsed)) {
                                        const v = parsed[k];
                                        if (v && typeof v === "object" && "from" in v && "to" in v) {
                                            parts.push(k + ": " + String(v.from) + " → " + String(v.to));
                                        }
                                    }
                                    detailsStr = parts.length ? parts.join("; ") : JSON.stringify(parsed);
                                }
                            } else detailsStr = h.details;
                        } catch (_) { detailsStr = h.details; }
                    }
                    return "<tr><td>" + h.created_at + "</td><td>" + label + "</td><td>" + (h.from_tab || "—") + "</td><td>" + (h.to_tab || "—") + "</td><td>" + (h.our_sku || "—") + "</td><td class=\"small\">" + detailsStr + "</td><td>" + (h.user_name || "—") + "</td></tr>";
                }).join("");
            })
            .catch(() => {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load history.</td></tr>';
            });
    }
    document.getElementById("arrived-history-btn")?.addEventListener("click", loadArrivedHistory);
    document.getElementById("arrived-history-refresh-btn")?.addEventListener("click", loadArrivedHistory);
    document.getElementById("arrivedHistoryModal")?.addEventListener("show.bs.modal", function() { loadArrivedHistory(); });


document.body.style.zoom = "90%"; 

</script>

@endsection
