@extends('layouts.vertical', ['title' => 'QC Container'])
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

  .qc-audit-yes-btn.active {
    background: #16a34a !important;
    border-color: #16a34a !important;
    color: #fff !important;
  }

  .qc-audit-no-btn.active {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
    color: #fff !important;
  }

  .qc-audit-yes-btn {
    color: #16a34a;
    border-color: #16a34a;
  }

  .qc-audit-no-btn {
    color: #dc2626;
    border-color: #dc2626;
  }

  #qcAuditSpecsBody tr.qc-audit-no-selected {
    background: #fef2f2;
  }

  .qc-audit-discrepancy-wrap {
    display: none;
  }

  .qc-audit-discrepancy-wrap.show {
    display: block;
  }

  .qc-audit-image-preview {
    max-height: 48px;
    max-width: 72px;
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    cursor: zoom-in;
  }

  .qc-claims-badge {
    background: #0ea5e9;
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 999px;
    text-decoration: none;
    display: inline-block;
    line-height: 1.2;
  }

  .qc-claims-badge:hover {
    background: #0284c7;
    color: #fff;
  }

  .qc-claim-link-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #e0f2fe;
    color: #0369a1;
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 0.72rem;
    margin: 2px;
    text-decoration: none;
  }

  .qc-claim-link-chip:hover {
    background: #bae6fd;
    color: #0c4a6e;
  }

  .qc-claim-link-remove {
    border: 0;
    background: transparent;
    color: #dc2626;
    padding: 0 2px;
    line-height: 1;
    cursor: pointer;
  }

  #qcAuditModal .modal-body {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: calc(100vh - 180px);
    padding-top: 0.75rem;
  }

  #qcAuditModal .qc-audit-sticky-top {
    flex: 0 0 auto;
    position: sticky;
    top: 0;
    z-index: 30;
    background: #fff;
    padding-bottom: 10px;
    margin-bottom: 0;
  }

  #qcAuditModal .qc-audit-scroll-area {
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
  }

  /* Keep sticky thead working (overflow on .table-responsive breaks sticky). */
  #qcAuditModal .qc-audit-scroll-area .table-responsive {
    overflow: visible;
  }

  .qc-audit-product-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    margin-bottom: 0;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: linear-gradient(90deg, #eff6ff 0%, #f8fafc 100%);
  }

  .qc-audit-product-header img {
    width: 72px;
    height: 72px;
    object-fit: contain;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;
  }

  .qc-audit-product-header .qc-audit-no-image {
    width: 72px;
    height: 72px;
    border-radius: 8px;
    border: 1px dashed #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 0.75rem;
    background: #fff;
    text-align: center;
    padding: 4px;
  }

  .qc-audit-product-meta .qc-meta-label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    font-weight: 700;
  }

  .qc-audit-product-meta .qc-meta-value {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
  }

  .qc-priority-dot {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.18);
  }

  .qc-priority-dot-critical { background-color: #dc2626; }
  .qc-priority-dot-important { background-color: #2563eb; }

  #qcAuditModal th.qc-audit-spec-col,
  #qcAuditModal td.qc-audit-spec-col {
    background-color: #fef9c3 !important;
  }

  #qcAuditModal .qc-audit-specs-table thead th {
    background-color: #bfdbfe !important;
    color: #1e3a8a;
    text-align: center !important;
    vertical-align: middle;
    position: sticky;
    top: 0;
    z-index: 20;
    box-shadow: 0 1px 0 #93c5fd;
  }

  #qcAuditModal .qc-audit-specs-table thead th.qc-audit-spec-col {
    background-color: #fef08a !important;
    color: #1e3a8a;
    z-index: 21;
  }

  #qcAuditModal .qc-audit-specs-table td {
    text-align: center !important;
    vertical-align: middle !important;
  }

  #qcAuditModal .qc-audit-specs-table .form-text {
    text-align: center !important;
  }

  #qcAuditModal .qc-audit-specs-table .btn-group {
    justify-content: center;
  }

  .qc-disc-status-btn {
    border: 0;
    background: transparent;
    padding: 0;
    line-height: 1;
    cursor: default;
  }

  .qc-disc-status-btn.is-clickable {
    cursor: pointer;
  }

  .qc-disc-status-btn .fa-exclamation-triangle {
    color: #dc2626;
    font-size: 18px;
  }

  .qc-disc-status-btn .fa-check-square {
    color: #16a34a;
    font-size: 18px;
  }

  #qcAuditModal.qc-discrepancy-mode .modal-title::after {
    content: " · Discrepancies only";
    font-size: 0.85rem;
    font-weight: 600;
    color: #dc2626;
  }

  .qc-action-history-table {
    font-size: 0.78rem;
    margin-bottom: 6px;
  }

  .qc-action-history-table th,
  .qc-action-history-table td {
    padding: 4px 6px !important;
    vertical-align: middle;
  }

</style>
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'QC Container', 'sub_title' => 'QC Container'])

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
                    <button type="button" class="btn btn-secondary btn-sm" id="qc-history-btn" data-bs-toggle="modal" data-bs-target="#qcHistoryModal">
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

<div class="modal fade" id="qcHistoryModal" tabindex="-1" aria-labelledby="qcHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="qcHistoryModalLabel">
                    <i class="fas fa-history me-2"></i> QC Container History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <select id="qc-history-action-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All actions</option>
                        <option value="row_created">Row created</option>
                        <option value="row_updated">Row updated</option>
                        <option value="row_moved">Moved tab</option>
                        <option value="pushed_from_transit">Pushed from transit</option>
                    </select>
                    <input type="text" id="qc-history-tab-filter" class="form-control form-control-sm" placeholder="Tab name" style="width: 140px;">
                    <input type="text" id="qc-history-sku-filter" class="form-control form-control-sm" placeholder="SKU" style="width: 120px;">
                    <button type="button" id="qc-history-refresh-btn" class="btn btn-primary btn-sm"><i class="fas fa-sync-alt me-1"></i> Load</button>
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
                        <tbody id="qc-history-tbody">
                            <tr><td colspan="7" class="text-center text-muted">Open modal or click Load to fetch history.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- QC Action / Communication History Modal --}}
<div class="modal fade" id="qcActionHistoryModal" tabindex="-1" aria-labelledby="qcActionHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="qcActionHistoryModalLabel">
                    <i class="fas fa-comments me-2"></i>Action / Communication History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="qc_action_arrived_id">
                <input type="hidden" id="qc_action_sku">
                <input type="hidden" id="qc_action_supplier">
                <div class="mb-2">
                    <label for="qc_action_note_input" class="form-label fw-semibold">Action / Communication</label>
                    <textarea id="qc_action_note_input" class="form-control" rows="3"
                        placeholder="Enter action or communication note..." maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="qc-action-save-btn">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
        </div>
    </div>
</div>

{{-- QC Specs Audit Modal --}}
<div class="modal fade" id="qcAuditModal" tabindex="-1" aria-labelledby="qcAuditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="qcAuditModalLabel">
                    <i class="fas fa-search me-2 text-danger"></i> QC Specs Audit
                </h5>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <a href="{{ route('claim.reimbursement', ['open_add' => 1]) }}" target="_blank" rel="noopener"
                       class="qc-claims-badge" id="qc-audit-claims-badge" title="Open Add Claim / Reimbursement">
                        Claims
                    </a>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="qc-audit-sticky-top">
                    <div class="qc-audit-product-header" id="qc-audit-product-header">
                        <div id="qc-audit-product-image"><div class="qc-audit-no-image">No Image</div></div>
                        <div class="qc-audit-product-meta flex-grow-1">
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="qc-meta-label">SKU</div>
                                    <div class="qc-meta-value" id="qc-audit-sku-value">—</div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="qc-meta-label">Supplier</div>
                                    <div class="qc-meta-value" id="qc-audit-supplier-value">—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="qc-audit-scroll-area">
                    <div id="qc-audit-loading" class="text-center text-muted py-4">Loading comparison specs…</div>
                    <div id="qc-audit-empty" class="text-center text-muted py-4 d-none">No Critical / Important QC specs found for this SKU.</div>
                    <div id="qc-audit-error" class="alert alert-danger d-none"></div>
                    <div id="qc-audit-table-wrap" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0 qc-audit-specs-table text-center">
                                <thead>
                                    <tr>
                                        <th style="width:56px;" title="QC priority from Comparison Sheet">QC</th>
                                        <th style="min-width:120px;" id="qc-audit-basic-header">OLD</th>
                                        <th style="min-width:140px;" class="qc-audit-spec-col">Spec</th>
                                        <th style="min-width:120px;" id="qc-audit-supplier-header">Supplier</th>
                                        <th style="width:120px;">Yes / No</th>
                                        <th style="min-width:180px;">Audit Discrepancy</th>
                                        <th style="min-width:120px;">Images</th>
                                        <th style="min-width:180px;">Further Suggestion / Instructions</th>
                                    </tr>
                                </thead>
                                <tbody id="qcAuditSpecsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="qc-audit-save-btn">
                    <i class="fas fa-save me-1"></i> Save Audit
                </button>
            </div>
        </div>
    </div>
</div>

@include('purchase-master.partials.arrived-po-olink-edit')

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

function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
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
            { title: "Parent", field: "parent"},
            { title: "Sku", field: "our_sku" },
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
                  <button type="button" class="qc-cd-open border-0 bg-transparent p-0" data-sku="${safeSku}" title="${title}" aria-label="${title}" style="line-height:1;cursor:pointer;">
                    <i class="mdi mdi-magnify" style="color:${color};font-size:18px;"></i>
                  </button>
                </div>`;
              }
            },
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
            { title: "Qty / Ctns", field: "no_of_units", editor: "false", visible: false },
            { title: "Qty Ctns", field: "total_ctn", editor: "false", visible: false },
            { 
              title: "Qty", 
              field: "pcs_qty", 
              editor: false,
              visible: false,
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  const units = parseFloat(data.no_of_units) || 0;
                  const ctn = parseFloat(data.total_ctn) || 0;
                  return units * ctn;
              }
            },
            { title: "Rate ($)", field: "rate", editor: "false", visible: false },
            { 
              title: "CBM", 
              field: "cbm", 
              editor: "false",
              visible: false,
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
              visible: false,
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
              formatter: function(cell) {
                const url = String(cell.getValue() || '').trim();
                if (!url) return '—';
                return `<a href="${escHtml(url)}" target="_blank" rel="noopener" title="${escHtml(url)}"><i class="fas fa-external-link-alt" style="color:#2563eb;"></i></a>`;
              }
            },
            {
              title: "QC",
              field: "qc_audit",
              hozAlign: "center",
              headerSort: false,
              width: 110,
              headerTooltip: "QC specs audit from Comparison Sheet",
              formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const sku = String(d.our_sku || '').trim();
                if (!sku) {
                  return '<span style="display:block;text-align:center;color:#6c757d;">-</span>';
                }
                const hasAudit = !!d.has_qc_audit;
                const title = hasAudit ? 'View / edit QC audit' : 'Open QC specs audit';
                const safeSku = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                const safeSupplier = String(d.supplier_name || '')
                  .replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                const rowId = d.id != null ? String(d.id) : '';
                const claimsUrl = @json(route('claim.reimbursement'))
                  + '?open_add=1&sku=' + encodeURIComponent(sku)
                  + '&supplier=' + encodeURIComponent(String(d.supplier_name || '').trim());
                return `<div style="display:flex;align-items:center;justify-content:center;gap:6px;flex-wrap:wrap;">
                  <button type="button" class="qc-audit-open border-0 bg-transparent p-0"
                    data-sku="${safeSku}"
                    data-supplier="${safeSupplier}"
                    data-arrived-id="${rowId}"
                    title="${title}" aria-label="${title}"
                    style="line-height:1;cursor:pointer;">
                    <i class="fas fa-search" style="color:#dc2626;font-size:16px;"></i>
                  </button>
                  <a href="${claimsUrl}" target="_blank" rel="noopener" class="qc-claims-badge" title="Add Claim / Reimbursement">Claims</a>
                </div>`;
              }
            },
            {
              title: "Disc.",
              field: "has_qc_discrepancy",
              hozAlign: "center",
              headerSort: false,
              width: 70,
              headerTooltip: "QC discrepancy status from audit",
              formatter: function(cell) {
                const d = cell.getRow().getData() || {};
                const hasAudit = !!d.has_qc_audit;
                const hasDisc = !!d.has_qc_discrepancy;
                if (!hasAudit) {
                  return '<span class="text-muted">—</span>';
                }
                if (hasDisc) {
                  return `<button type="button" class="qc-disc-status-btn is-clickable qc-disc-open"
                    title="Discrepancy found — click to view"
                    aria-label="View discrepancies">
                    <i class="fas fa-exclamation-triangle"></i>
                  </button>`;
                }
                return `<span class="qc-disc-status-btn" title="No discrepancy">
                  <i class="fas fa-check-square"></i>
                </span>`;
              },
              cellClick: function(e, cell) {
                if (!e.target.closest('.qc-disc-open')) return;
                const d = cell.getRow().getData() || {};
                const sku = String(d.our_sku || '').trim();
                if (!sku) return;
                let imageSrc = String(d.photos || d.image_src || '').trim();
                if (!imageSrc && d.Values) {
                  try {
                    const values = typeof d.Values === 'string' ? JSON.parse(d.Values) : d.Values;
                    if (values && values.image_path) {
                      imageSrc = '/storage/' + String(values.image_path).replace(/^storage\//, '');
                    }
                  } catch (_) {}
                }
                openQcAuditModal(
                  d.id != null ? String(d.id) : '',
                  sku,
                  String(d.supplier_name || '').trim(),
                  cell.getRow(),
                  imageSrc,
                  true
                );
              }
            },
            {
              title: "Action / Communication History",
              field: "action_history",
              hozAlign: "left",
              headerSort: false,
              minWidth: 260,
              widthGrow: 2,
              variableHeight: true,
              formatter: function(cell) {
                return buildQcActionHistoryInline(cell.getValue());
              },
              cellClick: function(e, cell) {
                if (!e.target.closest('.qc-add-action-btn')) return;
                const d = cell.getRow().getData() || {};
                openQcAddActionModal(d.id, d.our_sku || '', d.supplier_name || '', cell.getRow());
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

    function loadQcHistory() {
        const params = new URLSearchParams();
        const action = document.getElementById("qc-history-action-filter").value;
        const tab = document.getElementById("qc-history-tab-filter").value.trim();
        const sku = document.getElementById("qc-history-sku-filter").value.trim();
        if (action) params.set("action_type", action);
        if (tab) params.set("tab_name", tab);
        if (sku) params.set("sku", sku);
        params.set("limit", "200");
        const tbody = document.getElementById("qc-history-tbody");
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
    document.getElementById("qc-history-btn")?.addEventListener("click", loadQcHistory);
    document.getElementById("qc-history-refresh-btn")?.addEventListener("click", loadQcHistory);
    document.getElementById("qcHistoryModal")?.addEventListener("show.bs.modal", function() { loadQcHistory(); });

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.qc-cd-open');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    const sku = String(btn.getAttribute('data-sku') || '').trim();
    if (!sku) return;
    const url = @json(route('comparison.sheet.page')) + '?sku=' + encodeURIComponent(sku);
    window.open(url, '_blank', 'noopener');
});

/* ── QC Specs Audit modal ── */
const qcAuditSpecsUrl = @json(route('qc.container.specs'));
const qcAuditSaveUrl = @json(route('qc.container.audit'));
const qcActionSaveUrl = @json(route('qc.container.action'));
const qcClaimReimbursementUrl = @json(route('claim.reimbursement'));
let qcAuditContext = { arrivedId: null, sku: '', supplier: '', rowRef: null, specs: [], claimLinks: [], discrepancyOnly: false };
let qcActionRowRef = null;

function buildQcClaimAddUrl(sku, supplier) {
    const url = new URL(qcClaimReimbursementUrl, window.location.origin);
    url.searchParams.set('open_add', '1');
    if (sku) url.searchParams.set('sku', String(sku).trim());
    if (supplier) url.searchParams.set('supplier', String(supplier).trim());
    return url.toString();
}

function syncQcClaimOpenLinks(sku, supplier) {
    const addUrl = buildQcClaimAddUrl(sku, supplier);
    const claimsBadge = document.getElementById('qc-audit-claims-badge');
    if (claimsBadge) claimsBadge.href = addUrl;
}

function buildQcActionHistoryInline(historyData) {
    let history = historyData || [];
    if (typeof history === 'string') {
        try { history = JSON.parse(history) || []; } catch (e) { history = []; }
    }
    if (!Array.isArray(history)) history = [];

    let html = '';
    if (history.length > 0) {
        html += '<table class="table table-sm table-bordered qc-action-history-table mb-1"><thead class="table-light"><tr>'
            + '<th>Action</th><th>Note</th><th>User</th><th>Date</th></tr></thead><tbody>';
        history.forEach(function(entry) {
            html += '<tr>'
                + '<td><span class="badge bg-info-subtle text-dark">' + qcEsc(entry.action || '-') + '</span></td>'
                + '<td class="text-start">' + qcEsc(entry.note || '-') + '</td>'
                + '<td>' + qcEsc(entry.user || '-') + '</td>'
                + '<td title="' + qcEsc(entry.datetime || entry.date || '') + '">' + qcEsc(entry.date || '-') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<span class="text-muted small">No history</span><br>';
    }
    html += '<button type="button" class="btn btn-sm btn-outline-primary qc-add-action-btn">'
        + '<i class="fas fa-plus me-1"></i> Add Action</button>';
    return html;
}

function openQcAddActionModal(arrivedId, sku, supplier, rowRef) {
    if (!arrivedId) {
        alert('Row must be saved before adding action history.');
        return;
    }
    qcActionRowRef = rowRef || null;
    document.getElementById('qc_action_arrived_id').value = arrivedId;
    document.getElementById('qc_action_sku').value = sku || '';
    document.getElementById('qc_action_supplier').value = supplier || '';
    document.getElementById('qc_action_note_input').value = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('qcActionHistoryModal')).show();
}

document.getElementById('qc-action-save-btn')?.addEventListener('click', function() {
    const arrivedId = document.getElementById('qc_action_arrived_id').value;
    const note = String(document.getElementById('qc_action_note_input').value || '').trim();
    if (!note) {
        document.getElementById('qc_action_note_input').focus();
        return;
    }
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(qcActionSaveUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            arrived_id: arrivedId,
            sku: document.getElementById('qc_action_sku').value || '',
            supplier_name: document.getElementById('qc_action_supplier').value || '',
            note: note
        })
    })
    .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j }; }); })
    .then(function(res) {
        if (!res.ok || !res.j.success) {
            alert((res.j && res.j.message) || 'Failed to save action.');
            return;
        }
        if (qcActionRowRef) {
            try { qcActionRowRef.update({ action_history: res.j.action_history || [] }); } catch (_) {}
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('qcActionHistoryModal')).hide();
    })
    .catch(function() {
        alert('Something went wrong while saving action.');
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});

function qcEsc(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function setQcAuditAnswer(rowEl, answer) {
    const yesBtn = rowEl.querySelector('.qc-audit-yes-btn');
    const noBtn = rowEl.querySelector('.qc-audit-no-btn');
    const wrap = rowEl.querySelector('.qc-audit-discrepancy-wrap');
    const input = rowEl.querySelector('.qc-audit-discrepancy-input');
    rowEl.dataset.answer = answer || '';

    yesBtn?.classList.toggle('active', answer === 'yes');
    noBtn?.classList.toggle('active', answer === 'no');
    rowEl.classList.toggle('qc-audit-no-selected', answer === 'no');

    if (answer === 'no') {
        wrap?.classList.add('show');
        if (input) input.required = true;
    } else {
        wrap?.classList.remove('show');
        if (input) {
            input.required = false;
            if (answer === 'yes') input.value = '';
        }
    }
}

function setQcAuditProductHeader(sku, supplier, imageSrc) {
    document.getElementById('qc-audit-sku-value').textContent = sku || '—';
    document.getElementById('qc-audit-supplier-value').textContent = supplier || '—';
    const imgWrap = document.getElementById('qc-audit-product-image');
    if (!imgWrap) return;
    const url = String(imageSrc || '').trim();
    if (url) {
        imgWrap.innerHTML = `<img src="${qcEsc(url)}" alt="Product" data-preview="${qcEsc(url)}">`;
    } else {
        imgWrap.innerHTML = '<div class="qc-audit-no-image">No Image</div>';
    }
}

function renderQcAuditRows(specs, savedItems) {
    const tbody = document.getElementById('qcAuditSpecsBody');
    const savedMap = {};
    (savedItems || []).forEach(function(item) {
        const key = String(item.row_index != null ? item.row_index : '') + '|' + String(item.spec || '');
        savedMap[key] = item;
    });

    if (!specs.length) {
        tbody.innerHTML = '';
        return;
    }

    tbody.innerHTML = specs.map(function(spec, idx) {
        const key = String(spec.row_index) + '|' + String(spec.spec || '');
        const saved = savedMap[key] || null;
        const answer = saved && (saved.answer === 'yes' || saved.answer === 'no') ? saved.answer : '';
        const discrepancy = saved && saved.answer === 'no' ? String(saved.discrepancy || '') : '';
        const instructions = saved ? String(saved.instructions || '') : '';
        const imageUrl = saved && saved.image_url ? String(saved.image_url) : '';
        const qcPriority = String(spec.qc_priority || '');
        const qcDotClass = qcPriority === 'Critical'
            ? 'qc-priority-dot-critical'
            : (qcPriority === 'Important' ? 'qc-priority-dot-important' : '');
        const imagePreview = imageUrl
            ? `<div class="mb-1"><img src="${qcEsc(imageUrl)}" class="qc-audit-image-preview" data-preview="${qcEsc(imageUrl)}" alt="QC image">
                 <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-1 qc-audit-remove-image" title="Remove image">Remove</button></div>`
            : '';
        return `<tr data-row-index="${qcEsc(spec.row_index)}" data-spec-idx="${idx}" data-answer="${qcEsc(answer)}" data-remove-image="0">
            <td title="${qcEsc(qcPriority)}">
                ${qcDotClass ? `<span class="qc-priority-dot ${qcDotClass}" aria-label="${qcEsc(qcPriority)}"></span>` : '<span class="text-muted">—</span>'}
            </td>
            <td class="small">${qcEsc(spec.basic) || '<span class="text-muted">—</span>'}</td>
            <td class="fw-semibold qc-audit-spec-col">${qcEsc(spec.spec)}</td>
            <td class="small">${qcEsc(spec.supplier) || '<span class="text-muted">—</span>'}</td>
            <td>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-success qc-audit-yes-btn ${answer === 'yes' ? 'active' : ''}">Yes</button>
                    <button type="button" class="btn btn-outline-danger qc-audit-no-btn ${answer === 'no' ? 'active' : ''}">No</button>
                </div>
            </td>
            <td>
                <div class="qc-audit-discrepancy-wrap ${answer === 'no' ? 'show' : ''}">
                    <label class="form-label small fw-semibold mb-1 text-danger">Audit Discrepancy <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm qc-audit-discrepancy-input text-center"
                        maxlength="100" placeholder="Required (max 100 chars)"
                        value="${qcEsc(discrepancy)}" ${answer === 'no' ? 'required' : ''}>
                    <div class="form-text"><span class="qc-audit-char-count">${discrepancy.length}</span>/100</div>
                </div>
            </td>
            <td>
                ${imagePreview}
                <input type="file" accept="image/*" class="form-control form-control-sm qc-audit-image-input">
                <div class="form-text">Optional</div>
            </td>
            <td>
                <textarea class="form-control form-control-sm qc-audit-instructions-input text-center" rows="2"
                    maxlength="500" placeholder="Further suggestion / instructions…">${qcEsc(instructions)}</textarea>
                <div class="form-text"><span class="qc-audit-instr-count">${instructions.length}</span>/500</div>
            </td>
        </tr>`;
    }).join('');

    tbody.querySelectorAll('tr').forEach(function(rowEl) {
        setQcAuditAnswer(rowEl, rowEl.dataset.answer || '');
    });
}

function openQcAuditModal(arrivedId, sku, supplier, rowRef, imageSrc, discrepancyOnly) {
    qcAuditContext = {
        arrivedId,
        sku,
        supplier,
        rowRef,
        specs: [],
        claimLinks: [],
        discrepancyOnly: !!discrepancyOnly
    };

    const modalEl = document.getElementById('qcAuditModal');
    modalEl.classList.toggle('qc-discrepancy-mode', !!discrepancyOnly);

    setQcAuditProductHeader(sku, supplier, imageSrc || '');
    syncQcClaimOpenLinks(sku, supplier);
    document.getElementById('qc-audit-loading').classList.remove('d-none');
    document.getElementById('qc-audit-empty').classList.add('d-none');
    document.getElementById('qc-audit-error').classList.add('d-none');
    document.getElementById('qc-audit-table-wrap').classList.add('d-none');
    document.getElementById('qcAuditSpecsBody').innerHTML = '';
    document.getElementById('qc-audit-empty').textContent = discrepancyOnly
        ? 'No discrepancy rows found for this SKU.'
        : 'No Critical / Important QC specs found for this SKU.';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalEl.addEventListener('hidden.bs.modal', function onQcModalHidden() {
        modalEl.classList.remove('qc-discrepancy-mode');
        modalEl.removeEventListener('hidden.bs.modal', onQcModalHidden);
    });
    modal.show();

    const params = new URLSearchParams();
    params.set('sku', sku);
    if (supplier) params.set('supplier', supplier);
    if (arrivedId) params.set('arrived_id', arrivedId);

    fetch(qcAuditSpecsUrl + '?' + params.toString(), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('qc-audit-loading').classList.add('d-none');
        if (!res.success) {
            const err = document.getElementById('qc-audit-error');
            err.textContent = res.message || 'Failed to load specs.';
            err.classList.remove('d-none');
            return;
        }

        document.getElementById('qc-audit-basic-header').textContent = res.basic_header || 'OLD';
        document.getElementById('qc-audit-supplier-header').textContent = res.supplier_header || 'Supplier';

        let specs = Array.isArray(res.specs) ? res.specs : [];
        const savedItems = res.audit && Array.isArray(res.audit.items) ? res.audit.items : [];

        if (qcAuditContext.discrepancyOnly) {
            const discKeys = {};
            savedItems.forEach(function(item) {
                const isNo = String(item.answer || '').toLowerCase() === 'no';
                const hasNote = String(item.discrepancy || '').trim() !== '';
                if (isNo || hasNote) {
                    discKeys[String(item.row_index != null ? item.row_index : '') + '|' + String(item.spec || '')] = true;
                }
            });
            specs = specs.filter(function(spec) {
                const key = String(spec.row_index) + '|' + String(spec.spec || '');
                return !!discKeys[key];
            });
        }

        qcAuditContext.specs = specs;
        qcAuditContext.supplier = res.supplier_name || supplier;
        qcAuditContext.sku = res.sku || sku;
        qcAuditContext.claimLinks = (res.audit && Array.isArray(res.audit.claim_links)) ? res.audit.claim_links : [];

        setQcAuditProductHeader(
            res.sku || sku,
            res.supplier_name || supplier,
            res.image_src || imageSrc || ''
        );
        syncQcClaimOpenLinks(res.sku || sku, res.supplier_name || supplier);

        if (!specs.length) {
            document.getElementById('qc-audit-empty').classList.remove('d-none');
            document.getElementById('qc-audit-table-wrap').classList.remove('d-none');
            return;
        }

        renderQcAuditRows(specs, savedItems);
        document.getElementById('qc-audit-table-wrap').classList.remove('d-none');
    })
    .catch(function() {
        document.getElementById('qc-audit-loading').classList.add('d-none');
        const err = document.getElementById('qc-audit-error');
        err.textContent = 'Failed to load comparison specs.';
        err.classList.remove('d-none');
    });
}

document.addEventListener('click', function(e) {
    const openBtn = e.target.closest('.qc-audit-open');
    if (openBtn) {
        e.preventDefault();
        e.stopPropagation();
        const sku = String(openBtn.getAttribute('data-sku') || '').trim();
        if (!sku) return;
        const supplier = String(openBtn.getAttribute('data-supplier') || '').trim();
        const arrivedId = String(openBtn.getAttribute('data-arrived-id') || '').trim();
        let rowRef = null;
        let imageSrc = '';
        const tabulatorRowEl = openBtn.closest('.tabulator-row');
        if (tabulatorRowEl) {
            Object.values(window.tabTables || {}).some(function(table) {
                try {
                    const rows = table.getRows();
                    for (let i = 0; i < rows.length; i++) {
                        if (rows[i].getElement() === tabulatorRowEl) {
                            rowRef = rows[i];
                            const d = rows[i].getData() || {};
                            imageSrc = String(d.photos || d.image_src || '').trim();
                            if (!imageSrc && d.Values) {
                                try {
                                    const values = typeof d.Values === 'string' ? JSON.parse(d.Values) : d.Values;
                                    if (values && values.image_path) {
                                        imageSrc = '/storage/' + String(values.image_path).replace(/^storage\//, '');
                                    }
                                } catch (_) {}
                            }
                            return true;
                        }
                    }
                } catch (_) {}
                return false;
            });
        }
        openQcAuditModal(arrivedId, sku, supplier, rowRef, imageSrc, false);
        return;
    }

    const yesBtn = e.target.closest('.qc-audit-yes-btn');
    if (yesBtn) {
        e.preventDefault();
        const rowEl = yesBtn.closest('tr');
        if (rowEl) setQcAuditAnswer(rowEl, 'yes');
        return;
    }

    const noBtn = e.target.closest('.qc-audit-no-btn');
    if (noBtn) {
        e.preventDefault();
        const rowEl = noBtn.closest('tr');
        if (rowEl) setQcAuditAnswer(rowEl, 'no');
        return;
    }

    const removeImgBtn = e.target.closest('.qc-audit-remove-image');
    if (removeImgBtn) {
        e.preventDefault();
        const rowEl = removeImgBtn.closest('tr');
        if (!rowEl) return;
        rowEl.dataset.removeImage = '1';
        const previewWrap = removeImgBtn.closest('div');
        if (previewWrap) previewWrap.remove();
        return;
    }

});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('qc-audit-discrepancy-input')) {
        const countEl = e.target.closest('td')?.querySelector('.qc-audit-char-count');
        if (countEl) countEl.textContent = String(e.target.value.length);
        return;
    }
    if (e.target.classList.contains('qc-audit-instructions-input')) {
        const countEl = e.target.closest('td')?.querySelector('.qc-audit-instr-count');
        if (countEl) countEl.textContent = String(e.target.value.length);
    }
});

document.getElementById('qc-audit-save-btn')?.addEventListener('click', function() {
    const arrivedId = qcAuditContext.arrivedId;
    const sku = qcAuditContext.sku;
    if (!arrivedId) {
        alert('Row must be saved before QC audit.');
        return;
    }

    const rows = Array.from(document.querySelectorAll('#qcAuditSpecsBody tr'));
    if (!rows.length) {
        alert('No specs to audit.');
        return;
    }

    const items = [];
    const formData = new FormData();
    formData.append('arrived_id', arrivedId);
    formData.append('sku', sku);
    formData.append('supplier_name', qcAuditContext.supplier || '');

    for (let i = 0; i < rows.length; i++) {
        const rowEl = rows[i];
        const answer = String(rowEl.dataset.answer || '');
        const specIdx = parseInt(rowEl.dataset.specIdx, 10);
        const spec = qcAuditContext.specs[specIdx] || {};
        const discrepancyInput = rowEl.querySelector('.qc-audit-discrepancy-input');
        const discrepancy = discrepancyInput ? String(discrepancyInput.value || '').trim() : '';
        const instructionsInput = rowEl.querySelector('.qc-audit-instructions-input');
        const instructions = instructionsInput ? String(instructionsInput.value || '').trim() : '';
        const imageInput = rowEl.querySelector('.qc-audit-image-input');
        const removeImage = String(rowEl.dataset.removeImage || '0') === '1';

        if (answer !== 'yes' && answer !== 'no') {
            alert('Please select Yes or No for every spec (missing: ' + (spec.spec || ('row ' + (i + 1))) + ').');
            return;
        }
        if (answer === 'no') {
            if (!discrepancy) {
                alert('Audit Discrepancy is required when No is selected (' + (spec.spec || '') + ').');
                discrepancyInput?.focus();
                return;
            }
            if (discrepancy.length > 100) {
                alert('Audit Discrepancy must be 100 characters or less (' + (spec.spec || '') + ').');
                discrepancyInput?.focus();
                return;
            }
        }
        if (instructions.length > 500) {
            alert('Further Suggestion / Instructions must be 500 characters or less (' + (spec.spec || '') + ').');
            instructionsInput?.focus();
            return;
        }

        items.push({
            row_index: spec.row_index,
            spec: spec.spec || '',
            basic: spec.basic || '',
            supplier: spec.supplier || '',
            answer: answer,
            discrepancy: answer === 'no' ? discrepancy : '',
            instructions: instructions,
            remove_image: removeImage
        });

        if (imageInput && imageInput.files && imageInput.files[0]) {
            formData.append('images[' + i + ']', imageInput.files[0]);
        }
    }

    formData.append('items', JSON.stringify(items));
    formData.append('claim_links', JSON.stringify(qcAuditContext.claimLinks || []));

    const saveBtn = document.getElementById('qc-audit-save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving…';

    fetch(qcAuditSaveUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j }; }); })
    .then(function(res) {
        if (!res.ok || !res.j.success) {
            alert((res.j && res.j.message) || 'Failed to save QC audit.');
            return;
        }
        if (qcAuditContext.rowRef) {
            try {
                qcAuditContext.rowRef.update({
                    has_qc_audit: true,
                    has_qc_discrepancy: !!res.j.has_qc_discrepancy
                });
            } catch (_) {}
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('qcAuditModal')).hide();
        alert('QC audit saved.');
    })
    .catch(function() {
        alert('Something went wrong while saving QC audit.');
    })
    .finally(function() {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Save Audit';
    });
});

document.body.style.zoom = "90%"; 

</script>

@endsection
