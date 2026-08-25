@extends('layouts.vertical', ['title' => 'On Sea Transit', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    /* Resizer styling */
    .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
        width: 5px;
        background-color: #dee2e6;
        cursor: ew-resize;
    }

    /* Header styling */
    .tabulator .tabulator-header {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    .tabulator .tabulator-header .tabulator-col {
        text-align: center;
        background: #1a2942;
        border-right: 1px solid #ffffff;
        color: #fff;
        font-weight: bold;
        padding: 12px 8px;
    }
    
    /* Hide sorting arrows */
    .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter,
    .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
        display: none !important;
    }

    .tabulator-tableholder{
        height: calc(100% - 100px) !important;
    }

    .tabulator-row {
        background-color: #ffffff !important;
        /* default black for all rows */
    }

    /* Cell styling */
    .tabulator .tabulator-cell {
        text-align: center;
        padding: 12px 8px;
        border-right: 1px solid #dee2e6;
        border-bottom: 1px solid #dee2e6;
        font-weight: bolder;
        color: #000000;
    }

     .tabulator .tabulator-cell input,
    .tabulator .tabulator-cell select,
    .tabulator .tabulator-cell .form-select,
    .tabulator .tabulator-cell .form-control {
        font-weight: bold !important;
        color: #000000 !important;
    }

    /* Row hover effect */
    .tabulator-row:hover {
        background-color: rgba(0, 0, 0, .075) !important;
    }

    /* Parent row styling */
    .parent-row {
        background-color: #DFF0FF !important;
        font-weight: 600;
    }

    /* Pagination styling */
    .tabulator .tabulator-footer {
        background: #f4f7fa;
        border-top: 1px solid #e5e7eb;
        font-size: 1rem;
        color: #4b5563;
        padding: 5px;
        height: 100px;
    }
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
    
    /* Green dot and hover container styling */
    .hover-container {
        position: relative;
        display: inline-block;
    }
    
    .green-dot {
        cursor: pointer;
        font-size: 14px;
        display: inline-block;
        transition: transform 0.2s;
    }
    
    .hover-container:hover .green-dot {
        transform: scale(1.3);
    }
    
    /* Hover popup */
    .hover-popup {
        display: none;
        position: fixed;
        background: white;
        border: 3px solid #28a745;
        border-radius: 10px;
        padding: 15px 20px;
        white-space: nowrap;
        z-index: 99999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        font-size: 20px;
        font-weight: 700;
        max-width: 90vw;
        pointer-events: none;
    }
    
    .hover-container.active .hover-popup {
        display: block;
        animation: fadeIn 0.2s;
    }
    
    .hover-popup span {
        font-size: 20px;
        font-weight: 700;
        color: #000;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateX(-50%) translateY(-5px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    
    .copy-btn {
        padding: 2px 8px;
        font-size: 12px;
    }
    
    .copy-btn.copied {
        background-color: #28a745 !important;
        border-color: #28a745 !important;
    }
    
    /* Status container for BL check dots */
    .status-container {
        display: inline-block;
        position: relative;
    }
    
    .status-container .fa-circle,
    .status-container .fa-ship {
        transition: transform 0.2s;
    }
    
    .status-container .fa-circle:hover,
    .status-container .fa-ship:hover {
        transform: scale(1.3);
    }
    
    /* Port container styling */
    .port-container {
        display: inline-block;
        position: relative;
    }
    
    .port-container .fa-circle {
        transition: transform 0.2s;
    }
    
    .port-container .fa-circle:hover {
        transform: scale(1.3);
    }
    
    .port-select {
        background: white;
        border: 2px solid #28a745;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    /* Modal styling */
    .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5) !important;
    }
    
    .modal-dialog {
        z-index: 1050 !important;
    }
    
    .modal {
        z-index: 1055 !important;
    }
    
    .modal-content {
        z-index: 1060 !important;
    }

    /* Badge strip: one line, equal width, original colors */
    .ost-badge-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.35rem;
        width: 100%;
        overflow: hidden;
        min-width: 0;
    }

    .ost-badge-row .ost-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 1 1 0;
        width: 0;
        min-width: 0;
        font-size: 0.8rem;
        font-weight: 700;
        padding: 0.45rem 0.4rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: center;
        border-radius: 0.375rem;
    }

    .ost-badge-row .ost-sop-group {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        flex: 0 0 auto;
    }

    .ost-badge-row .ost-sop-group .btn {
        font-size: 0.8rem;
        padding: 0.35rem 0.55rem;
        white-space: nowrap;
    }

    /* Edit modal — China Load section with distinct background */
    .ost-china-load-section {
        background: #eef6ff;
        border: 1px solid #cfe2ff;
    }

    .ost-china-load-section .ost-china-load-box {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 0.75rem 0.5rem;
    }

    /* Edit modal — top status strip (one line, light yellow) */
    .ost-edit-top-strip {
        background: #fff8dc;
        border: 1px solid #f0e6a8;
    }

    .ost-edit-top-strip .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.2rem;
        white-space: nowrap;
    }

    .ost-edit-top-strip .form-control,
    .ost-edit-top-strip .form-select {
        font-size: 0.85rem;
        padding: 0.3rem 0.45rem;
        min-width: 0;
    }

    /* Edit modal — 3rd block: Duty / Invoice / Arrival Notice */
    .ost-edit-duty-strip {
        background: #e8f8ef;
        border: 1px solid #b7e4c7;
    }

    /* Edit modal — payments strip: Agent / Due / enter amounts */
    .ost-edit-pay-strip {
        background: #f3e8ff;
        border: 1px solid #d8b4fe;
    }

    .ost-edit-pay-strip .form-label {
        font-size: 0.75rem;
        margin-bottom: 0.2rem;
        white-space: nowrap;
    }

    .ost-edit-pay-strip .form-control,
    .ost-edit-pay-strip .form-select {
        font-size: 0.85rem;
        padding: 0.3rem 0.45rem;
        min-width: 0;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'On Sea Transit', 'sub_title' => 'On Sea Transit'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="ost-badge-row mb-3">
                    @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'on_sea_transit', 'wrapperClass' => 'flex-shrink-0'])
                    <span class="badge ost-badge bg-warning text-dark">
                        Pre-Load:<span id="planningBadge">{{ $planningCount }}</span>
                    </span>
                    <span class="badge ost-badge bg-primary text-white">
                        On Sea:<span id="onSeaBadge">0</span>
                    </span>
                    <span class="badge ost-badge text-white" style="background-color:#654321;">
                        Landed:<span id="landedBadge">0</span>
                    </span>
                    <span class="badge ost-badge bg-info text-white">
                        Transit:<span id="remainingBadge">{{ $remainingCount }}</span>
                    </span>
                    <span class="badge ost-badge bg-success text-white">
                        $<span id="totalValueBadge">{{ number_format($totalInvoiceValue, 0) }}</span>
                    </span>
                    <span class="badge ost-badge bg-danger text-white">
                        Due:$<span id="totalPendingBadge">{{ number_format($totalPendingAmount ?? 0, 0) }}</span>
                    </span>
                    {{-- "Value" badge — total of the table's Value column
                         (invoice_value) across every visible row (Arrived
                         excluded, same filter as the Tabulator view). --}}
                    <span class="badge ost-badge text-white" style="background-color:#6366f1;">
                        Value:$<span id="totalColumnValueBadge">{{ number_format($totalColumnValue ?? 0, 0) }}</span>
                    </span>
                    <div class="ost-sop-group">
                        <a href="#" id="sopButton" class="btn btn-primary" target="_blank">
                            <i class="fas fa-book"></i> SOP
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="openSopModal()" title="Edit SOP link">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>

                <div id="on-sea-transit-table"></div>
            </div>
        </div>
    </div>
</div>

<!-- China Load Modal -->
<div class="modal fade" id="chinaLoadModal" tabindex="-1" aria-labelledby="chinaLoadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">China Load Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="chinaLoadModalBody">
        <!-- Content dynamically filled -->
      </div>
    </div>
  </div>
</div>

<!-- Change History Modal -->
<div class="modal fade" id="detailsHistoryModal" tabindex="-1" aria-labelledby="detailsHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="detailsHistoryModalLabel"><i class="fas fa-history me-2"></i>Change History</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="detailsHistoryModalBody" style="max-height: 70vh; overflow-y: auto;">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Details Edit Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea class="form-control" id="detailsTextarea" rows="5" placeholder="Enter details..."></textarea>
        <input type="hidden" id="detailsContainer">
        <input type="hidden" id="detailsRecordId">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveDetailsBtn">Save</button>
      </div>
    </div>
  </div>
</div>

<!-- SOP Link Edit Modal -->
<div class="modal fade" id="sopModal" tabindex="-1" aria-labelledby="sopModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-link me-2"></i>Edit SOP Link</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="sopLinkInput" class="form-label">SOP Link URL</label>
        <input type="url" class="form-control" id="sopLinkInput" placeholder="https://example.com/sop" required>
        <small class="text-muted">Enter the full URL including https://</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveSopLinkBtn">Save</button>
      </div>
    </div>
  </div>
</div>

{{--
    Row-level "Edit All Columns" modal opened by the Action column pencil
    button.  Pre-populated from the Tabulator row data; submit POSTs the
    whole form to /on-sea-transit/update-row which writes only the fields
    that are present (so leaving a field untouched still works).
--}}
<div class="modal fade" id="ostEditRowModal" tabindex="-1" aria-labelledby="ostEditRowModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="ostEditRowModalLabel">
          <i class="fas fa-pen me-2"></i>Edit row — <span id="ostEditRowSlLabel">—</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="ostEditRowForm" class="row g-3">
          <input type="hidden" name="id" id="ostEditRowId">
          <input type="hidden" name="container_sl_no" id="ostEditRowContainerSlNo">

          {{-- Top strip: SL / Status / BL / BL link / ISF / ISF agent — one line, light yellow --}}
          <div class="col-12">
            <div class="ost-edit-top-strip rounded-3 px-2 py-2">
              <div class="d-flex flex-nowrap align-items-end gap-2">
                <div style="flex:0 0 7rem;min-width:0;">
                  <label class="form-label fw-semibold">Container SL No.</label>
                  <input type="text" class="form-control" id="ostEditRowContainerSlNoDisplay" disabled>
                </div>
                <div style="flex:0 0 7.5rem;min-width:0;">
                  <label class="form-label fw-semibold">Status</label>
                  <select name="status" class="form-select">
                    <option value="">—</option>
                    <option value="Planning">Pre-Load</option>
                    <option value="On Sea">On Sea</option>
                    <option value="Landed">Landed</option>
                    <option value="Arrived">Arrived</option>
                  </select>
                </div>
                <div style="flex:0 0 7rem;min-width:0;">
                  <label class="form-label fw-semibold">BL</label>
                  <select name="bl_check" class="form-select">
                    <option value="">—</option>
                    <option value="Issued">Issued</option>
                    <option value="Verified">Verified</option>
                  </select>
                </div>
                <div style="flex:1 1 auto;min-width:8rem;">
                  <label class="form-label fw-semibold">BL link</label>
                  <input type="url" name="bl_link" class="form-control" placeholder="https://…">
                </div>
                <div style="flex:0 0 8rem;min-width:0;">
                  <label class="form-label fw-semibold">ISF</label>
                  <select name="isf" class="form-select">
                    <option value="">—</option>
                    <option value="China Done">China Done</option>
                    <option value="USA Done">USA Done</option>
                  </select>
                </div>
                <div style="flex:0 0 8.5rem;min-width:0;">
                  <label class="form-label fw-semibold">ISF (USA agent)</label>
                  <select name="isf_usa_agent" class="form-select">
                    <option value="">—</option>
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {{-- China Load details (editable) — saved to china_load on row save --}}
          <div class="col-12">
            <div class="ost-china-load-section rounded-3 p-3">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <h6 class="mb-0 fw-semibold text-secondary">
                  <i class="fas fa-ship me-1 text-primary"></i>China Load Details
                </h6>
                <small class="text-muted">Editable</small>
              </div>
              <div class="row g-2">
                <div class="col-md-3 col-6">
                  <div class="ost-china-load-box text-center h-100">
                    <div class="fw-semibold text-secondary small text-uppercase mb-1">
                      <i class="fa-solid fa-ship me-1 text-primary"></i>MBL
                    </div>
                    <input type="text" name="mbl" id="ostEditChinaMbl"
                           class="form-control form-control-sm text-center text-primary fw-semibold"
                           placeholder="N/A">
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="ost-china-load-box text-center h-100">
                    <div class="fw-semibold text-secondary small text-uppercase mb-1">
                      <i class="fa-solid fa-file-lines me-1 text-success"></i>OBL
                    </div>
                    <input type="text" name="obl" id="ostEditChinaObl"
                           class="form-control form-control-sm text-center text-success fw-semibold"
                           placeholder="N/A">
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="ost-china-load-box text-center h-100">
                    <div class="fw-semibold text-secondary small text-uppercase mb-1">
                      <i class="fa-solid fa-boxes-stacked me-1 text-warning"></i>Container No
                    </div>
                    <input type="text" name="container_no" id="ostEditChinaContainerNo"
                           class="form-control form-control-sm text-center text-warning fw-semibold"
                           placeholder="N/A">
                  </div>
                </div>
                <div class="col-md-3 col-6">
                  <div class="ost-china-load-box text-center h-100">
                    <div class="fw-semibold text-secondary small text-uppercase mb-1">
                      <i class="fa-solid fa-cube me-1 text-info"></i>Item
                    </div>
                    <input type="text" name="item" id="ostEditChinaItem"
                           class="form-control form-control-sm text-center text-info fw-semibold"
                           placeholder="N/A">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">ETD</label>
            <input type="date" name="etd" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">ETA Port</label>
            <input type="date" name="eta_port" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Port Arr</label>
            <select name="port_arrival" class="form-select">
              <option value="">—</option>
              <option value="NYC">NYC</option>
              <option value="LA">LA</option>
              <option value="PRINCE RUPERT">PRINCE RUPERT</option>
              <option value="NORFOLK">NORFOLK</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">ETA Ohio</label>
            <input type="date" name="eta_date_ohio" class="form-control">
          </div>

          {{-- 3rd block: Duty / Invoice → Dominic / Arrival Notice — light green --}}
          <div class="col-12">
            <div class="ost-edit-duty-strip rounded-3 px-3 py-3">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Duty</label>
                  <select name="duty_calcu" class="form-select">
                    <option value="">—</option>
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Invoice → Dominic</label>
                  <select name="invoice_send_to_dominic" class="form-select">
                    <option value="">—</option>
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Arrival Notice</label>
                  <select name="arrival_notice_email" class="form-select">
                    <option value="">—</option>
                    <option value="Pending">Pending</option>
                    <option value="Done">Done</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {{-- Payments strip — one line, light purple --}}
          <div class="col-12">
            <div class="ost-edit-pay-strip rounded-3 px-3 py-2">
              <div class="d-flex flex-nowrap align-items-end gap-3">
                <div class="flex-shrink-0">
                  <div class="fw-semibold" style="font-size:0.85rem;line-height:1.2;">Supplier payments</div>
                  <small class="text-muted" style="font-size:0.7rem;">Due follows Grand Total when payments are entered</small>
                </div>
                <div class="flex-shrink-0">
                  <button type="button" class="btn btn-sm btn-outline-primary" id="ostOpenSupplierPaymentsBtn">
                    <i class="fas fa-list me-1"></i>Enter supplier amounts
                  </button>
                </div>
                <div style="flex:1 1 10rem;min-width:8rem;">
                  <label class="form-label fw-semibold">Agent</label>
                  <select name="agent" id="ostAgentSelect" class="form-select">
                    <option value="">—</option>
                    <option value="Roman">Roman</option>
                    <option value="Stephen">Stephen</option>
                    <option value="__add_more__">+ Add more…</option>
                  </select>
                </div>
                <div style="flex:1 1 10rem;min-width:8rem;">
                  <label class="form-label fw-semibold">Due ($)</label>
                  <input type="number" id="ostEditRowBalanceDisplay" class="form-control" disabled>
                </div>
              </div>
            </div>
          </div>
          {{-- Value/Freight/Paid stay hidden; Value from Transit Inv, Freight/Paid from payments modal --}}
          <input type="hidden" name="invoice_value" id="ostEditInvoiceValueHidden" value="0">
          <input type="hidden" name="freight" id="ostEditFreightHidden" value="0">
          <input type="hidden" name="paid" id="ostEditPaidHidden" value="0">
          <input type="hidden" name="supplier_payments_json" id="ostSupplierPaymentsJson" value="[]">

          <div class="col-12">
            <label class="form-label fw-semibold">Details</label>
            <textarea name="details" rows="3" class="form-control"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ostEditRowSaveBtn">
          <i class="fas fa-save me-1"></i>Save changes
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Payments modal — Supplier + Agent categories + freight row --}}
<div class="modal fade" id="ostSupplierPaymentsModal" tabindex="-1" aria-labelledby="ostSupplierPaymentsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="ostSupplierPaymentsModalLabel">
          <i class="fas fa-truck me-2"></i>Payments — <span id="ostSupplierPaymentsSlLabel">—</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        {{-- Supplier category --}}
        <div class="mb-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0 fw-semibold text-secondary">
              <i class="fas fa-industry me-1"></i>Supplier payments
            </h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ostAddSupplierPaymentRowBtn">
              <i class="fas fa-plus me-1"></i>Add row
            </button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="min-width:160px;">Supplier name</th>
                  <th style="width:120px;">Amount</th>
                  <th style="width:120px;">Paid</th>
                  <th style="width:120px;">Balance</th>
                  <th style="width:50px;"></th>
                </tr>
              </thead>
              <tbody id="ostSupplierPayTbody"></tbody>
            </table>
          </div>
        </div>

        {{-- Agent category --}}
        <div class="mb-3">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <h6 class="mb-0 fw-semibold text-secondary">
              <i class="fas fa-user-tie me-1"></i>Agent payments
            </h6>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="ostAddAgentPaymentRowBtn">
              <i class="fas fa-plus me-1"></i>Add row
            </button>
          </div>
          <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th style="min-width:160px;">Agent</th>
                  <th style="width:120px;">Amount</th>
                  <th style="width:120px;">Paid</th>
                  <th style="width:120px;">Balance</th>
                  <th style="width:50px;"></th>
                </tr>
              </thead>
              <tbody id="ostAgentPayTbody"></tbody>
            </table>
          </div>
        </div>

        {{-- Freight row + grand total --}}
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle mb-0">
            <tbody>
              <tr class="align-middle">
                <td class="fw-semibold" style="min-width:160px;">Freight</td>
                <td style="width:120px;">
                  <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ostSpFreightRowInput" value="0">
                </td>
                <td colspan="2" class="text-muted small">Added into Due total</td>
                <td style="width:50px;"></td>
              </tr>
              <tr class="fw-semibold table-light">
                <td>Grand Total</td>
                <td id="ostSpTotalAmount">0.00</td>
                <td id="ostSpTotalPaid">0.00</td>
                <td id="ostSpTotalBalance">0.00</td>
                <td></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="ostApplySupplierPaymentsBtn">
          <i class="fas fa-check me-1"></i>Apply to Due
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.setAttribute("data-sidenav-size", "condensed");

    const tableData = @json($onSeaTransitData);
    const chinaLoadMap = @json($chinaLoadMap);
    
    // Function to update badge counts
    function updateBadgeCounts() {
        // Filter out 'Arrived' status to match table display
        const visibleData = tableData.filter(item => item.status !== 'Arrived');
        console.log('Visible Data:', visibleData);
        console.log('All statuses:', visibleData.map(item => item.status));
        
        const totalCount = visibleData.length;
        // Count Planning and null/empty status as Pre-Load (since formatter defaults to Planning)
        const planningCount = visibleData.filter(item => !item.status || item.status === 'Planning').length;
        const onSeaCount = visibleData.filter(item => item.status === 'On Sea').length;
        const landedCount = visibleData.filter(item => item.status === 'Landed').length;
        const remainingCount = totalCount - planningCount;
        
        console.log('Planning Count:', planningCount);
        console.log('On Sea Count:', onSeaCount);
        console.log('Landed Count:', landedCount);
        
        // Calculate total invoice value for containers excluding Arrived and Planning
        const totalInvoiceValue = visibleData
            .filter(item => item.status !== 'Planning' && item.status !== null && item.status !== '')
            .reduce((sum, item) => {
                const value = parseFloat(item.invoice_value) || 0;
                console.log(`Container ${item.container_sl_no}: status=${item.status}, invoice_value=${item.invoice_value}, parsed=${value}`);
                return sum + value;
            }, 0);
        
        // Calculate total pending amount (balance) for containers excluding Arrived and Planning
        const totalPendingAmount = visibleData
            .filter(item => item.status !== 'Planning' && item.status !== null && item.status !== '')
            .reduce((sum, item) => sum + (parseFloat(item.balance) || 0), 0);

        // "Value" badge — sum of every visible row's invoice_value (Arrived is
        // already filtered out of visibleData above, so this matches what the
        // user actually sees in the Value column at the row level).
        const totalColumnValue = visibleData
            .reduce((sum, item) => sum + (parseFloat(item.invoice_value) || 0), 0);

        console.log('Total Invoice Value:', totalInvoiceValue);
        console.log('Total Pending Amount:', totalPendingAmount);
        console.log('Total Column Value:', totalColumnValue);
        
        const planningBadge = document.getElementById('planningBadge');
        if (planningBadge) {
            planningBadge.textContent = planningCount;
        }
        
        const onSeaBadge = document.getElementById('onSeaBadge');
        if (onSeaBadge) {
            onSeaBadge.textContent = onSeaCount;
        }
        
        const landedBadge = document.getElementById('landedBadge');
        if (landedBadge) {
            landedBadge.textContent = landedCount;
        }
        
        const remainingBadge = document.getElementById('remainingBadge');
        if (remainingBadge) {
            remainingBadge.textContent = remainingCount;
        }
        
        const totalValueBadge = document.getElementById('totalValueBadge');
        if (totalValueBadge) {
            totalValueBadge.textContent = Math.round(totalInvoiceValue).toLocaleString();
        }
        
        const totalPendingBadge = document.getElementById('totalPendingBadge');
        if (totalPendingBadge) {
            totalPendingBadge.textContent = Math.round(totalPendingAmount).toLocaleString();
        }

        const totalColumnValueBadge = document.getElementById('totalColumnValueBadge');
        if (totalColumnValueBadge) {
            totalColumnValueBadge.textContent = Math.round(totalColumnValue).toLocaleString();
        }
    }
    
    const table = new Tabulator("#on-sea-transit-table", {
        data: tableData,
        layout: "fitDataStretch",
        placeholder: "No records available",
        pagination: "local",
        paginationSize: 10,
        movableColumns: true,
        resizableColumns: true,
        headerSort: false,
        // Earliest ETA Ohio first (null dates last)
        initialSort: [
            { column: "eta_date_ohio", dir: "asc" }
        ],
        height: "550px",
        rowFormatter: function (row) {
            const data = row.getData();
            const el = row.getElement();
            const position = row.getPosition(true); // 1-based among filtered/sorted rows

            // Reset styles so re-sort/refilter doesn't leave stale colors
            el.style.backgroundColor = '';
            el.style.color = '';
            el.style.opacity = '';

            // Lowest ETA Ohio: 1st row red, 2nd row yellow
            if (position === 1) {
                el.style.backgroundColor = '#f8d7da';
                el.style.color = '#842029';
                return;
            }
            if (position === 2) {
                el.style.backgroundColor = '#fff3cd';
                el.style.color = '#664d03';
                return;
            }

            // Check if ETA Port date has arrived or passed → highlight entire row red
            if (data.eta_port && data.eta_port !== '' && data.eta_port !== 'dd/mm/yyyy') {
                const etaDate = new Date(data.eta_port);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                etaDate.setHours(0, 0, 0, 0);
                if (etaDate <= today) {
                    el.style.backgroundColor = '#fde8ea';
                    el.style.color = '#b02030';
                    return;
                }
            }

            if (data.status === "On Sea") {
                el.style.backgroundColor = "#e2f0cb";
                el.style.opacity = "0.7";
            }
        },
        columns: [
            {
                title: "Cont",
                field: "container_sl_no",
                formatter: function(cell) {
                    const slNo = cell.getValue();
                    const rowData = cell.getRow().getData();
                    const china = chinaLoadMap[slNo] || {};
                    const mbl = rowData.mbl || china.mbl;
                    const obl = rowData.obl || china.obl;
                    const containerNo = rowData.container_no || china.container_no;
                    const item = rowData.item || china.item;
                    const missingChina =
                        !mbl || !obl || !containerNo || !item;
                    const infoClass = missingChina ? 'text-danger' : 'text-primary';
                    const infoTitle = missingChina
                        ? 'China Load data incomplete'
                        : 'China Load Details';

                    return `
                        <span class="badge bg-primary text-white" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">${slNo}</span>
                        <i class="fas fa-info-circle ms-1 ${infoClass} open-modal-btn" data-sl="${slNo}" title="${infoTitle}" style="cursor: pointer;"></i>
                    `;
                },
                headerSort: false,
                minWidth: 120
            },
            {
                title: "MBL",
                field: "mbl",
                headerSort: false,
                minWidth: 80,
                visible: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '<span class="text-muted">-</span>';
                    const uniqueId = 'mbl-' + Math.random().toString(36).substr(2, 9);
                    return `
                        <div class="hover-container">
                            <i class="fas fa-circle green-dot" style="color: #28a745;"></i>
                            <div class="hover-popup">
                                <span class="fw-bold text-dark">${value}</span>
                                <button class="btn btn-sm btn-primary ms-2 copy-btn" onclick="copyToClipboard('${value}', this); event.stopPropagation();">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }
            },
            {
                title: "OBL",
                field: "obl",
                headerSort: false,
                minWidth: 80,
                visible: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '<span class="text-muted">-</span>';
                    return `
                        <div class="hover-container">
                            <i class="fas fa-circle green-dot" style="color: #28a745;"></i>
                            <div class="hover-popup">
                                <span class="fw-bold text-dark">${value}</span>
                                <button class="btn btn-sm btn-primary ms-2 copy-btn" onclick="copyToClipboard('${value}', this); event.stopPropagation();">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }
            },
            {
                title: "Cont No.",
                field: "container_no",
                headerSort: false,
                minWidth: 80,
                visible: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '<span class="text-muted">-</span>';
                    return `
                        <div class="hover-container">
                            <i class="fas fa-circle green-dot" style="color: #28a745;"></i>
                            <div class="hover-popup">
                                <span class="fw-bold text-dark">${value}</span>
                                <button class="btn btn-sm btn-primary ms-2 copy-btn" onclick="copyToClipboard('${value}', this); event.stopPropagation();">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }
            },
            {
                title: "Size",
                field: "item",
                headerSort: false,
                minWidth: 80,
                visible: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) return '<span class="text-muted">-</span>';
                    const displayValue = value.substring(0, 4);
                    return `<span class="text-dark fw-bold">${displayValue}</span>`;
                }
            },
            {
                // Merged BL check + BL link — edit via the row Edit modal.
                title: "BL",
                field: "bl_check",
                headerTooltip: "BL check + BL link",
                headerSort: false,
                minWidth: 80,
                formatter: function (cell) {
                    const rowData = cell.getRow().getData();
                    const check = rowData.bl_check;
                    const link = rowData.bl_link;

                    let checkHtml;
                    if (check === 'Verified') {
                        checkHtml = `<i class="fas fa-check-circle" title="Verified" style="font-size: 16px; color: #28a745;"></i>`;
                    } else if (check === 'Issued') {
                        checkHtml = `<i class="fas fa-circle" title="Issued" style="font-size: 14px; color: #ffc107;"></i>`;
                    } else {
                        checkHtml = `<i class="fas fa-circle" title="BL check missing" style="font-size: 14px; color: #dc3545;"></i>`;
                    }

                    let linkHtml;
                    if (link) {
                        linkHtml = `<a href="${link}" target="_blank" class="text-primary" title="Open BL link" style="text-decoration:none;"><i class="fas fa-link"></i></a>`;
                    } else {
                        linkHtml = `<i class="fas fa-link" title="BL link missing" style="color: #dc3545;"></i>`;
                    }

                    return `
                        <div class="d-flex justify-content-center align-items-center gap-2">
                            ${checkHtml}
                            ${linkHtml}
                        </div>
                    `;
                },
            },
            {
                title: "ISF",
                field: "isf",
                headerSort: false,
                minWidth: 80,
                formatter: function (cell) {
                    const value = cell.getValue();
                    const rowData = cell.getRow().getData();
                    
                    if (value === 'USA Done') {
                        return `
                            <div class="status-container">
                                <i class="fas fa-check-circle" style="font-size: 16px; cursor: pointer; color: #28a745;"></i>
                                <select class="form-select form-select-sm auto-save status-select"
                                    data-column="isf"
                                    style="display: none; width: 90px;">
                                    <option value="">Select</option>
                                    <option value="China Done">China Done</option>
                                    <option value="USA Done" selected>USA Done</option>
                                </select>
                            </div>
                        `;
                    } else if (value === 'China Done') {
                        return `
                            <div class="status-container">
                                <i class="fas fa-check-circle" style="font-size: 16px; cursor: pointer; color: #ffc107;"></i>
                                <select class="form-select form-select-sm auto-save status-select"
                                    data-column="isf"
                                    style="display: none; width: 90px;">
                                    <option value="">Select</option>
                                    <option value="China Done" selected>China Done</option>
                                    <option value="USA Done">USA Done</option>
                                </select>
                            </div>
                        `;
                    } else {
                        return `
                            <div class="status-container">
                                <i class="fas fa-circle" style="font-size: 14px; cursor: pointer; color: #dc3545;"></i>
                                <select class="form-select form-select-sm auto-save status-select"
                                    data-column="isf"
                                    style="display: none; width: 90px;">
                                    <option value="">Select</option>
                                    <option value="China Done">China Done</option>
                                    <option value="USA Done">USA Done</option>
                                </select>
                            </div>
                        `;
                    }
                },
            },
            {
                // Display-only — edit via the row Edit modal (etd field).
                title: "ETD",
                field: "etd",
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) {
                        return `<span class="text-muted">—</span>`;
                    }

                    const date = new Date(value);
                    const day = date.getDate();
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const formattedDate = `${day} ${monthNames[date.getMonth()]}`;

                    return `<span style="font-weight:600;">${formattedDate}</span>`;
                }
            },
            {
                // Display-only — edit via the row Edit modal (port_arrival field).
                // Outside table: short labels; Edit modal keeps full names.
                title: "Port Arr",
                field: "port_arrival",
                headerTooltip: "Port Arrival",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) {
                        return `<span class="text-muted">—</span>`;
                    }
                    const shortMap = {
                        'PRINCE RUPERT': 'PR',
                        'NORFOLK': 'NFLK'
                    };
                    const label = shortMap[value] || value;
                    return `<span style="font-weight:600;" title="${value}">${label}</span>`;
                }
            },
            {
                // Display-only — edit via the row Edit modal (eta_port field).
                title: "ETA Port",
                field: "eta_port",
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value || value === '' || value === 'dd/mm/yyyy') {
                        return `<span class="text-muted">—</span>`;
                    }

                    const date = new Date(value);
                    const day = date.getDate();
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const formattedDate = `${day} ${monthNames[date.getMonth()]}`;

                    const etaDate = new Date(value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    etaDate.setHours(0, 0, 0, 0);
                    const isOverdue = etaDate <= today;

                    if (isOverdue) {
                        return `
                            <span style="display:inline-block;padding:4px 10px;font-weight:700;color:#fff;background-color:#dc3545;border-radius:4px;">
                                ${formattedDate}
                            </span>
                        `;
                    }
                    return `<span style="font-weight:600;">${formattedDate}</span>`;
                }
            },
            {
                // Display-only — edit via the row Edit modal (eta_date_ohio field).
                // Top row (earliest ETA) = red bg; 2nd = yellow bg.
                title: "ETA OH",
                field: "eta_date_ohio",
                headerTooltip: "ETA OHIO",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    const position = cell.getRow().getPosition(true);
                    let bg = '';
                    let color = '#000';
                    if (position === 1) {
                        bg = '#dc3545';
                        color = '#fff';
                    } else if (position === 2) {
                        bg = '#ffc107';
                        color = '#000';
                    }

                    let label = '—';
                    if (value) {
                        const date = new Date(value);
                        const day = date.getDate();
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        label = `${day} ${monthNames[date.getMonth()]}`;
                    }

                    if (bg) {
                        return `<span style="display:inline-block;min-width:3.5rem;padding:4px 10px;font-weight:700;border-radius:4px;background:${bg};color:${color};">${label}</span>`;
                    }
                    return value
                        ? `<span style="font-weight:600;">${label}</span>`
                        : `<span class="text-muted">—</span>`;
                }
            },
            // { title: "ISF <br>(usa agent)", field: "isf_usa_agent", formatter: function(cell) {
            //     const value = cell.getValue();
            //     let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
            //     return `<select class="form-select form-select-sm auto-save" data-column="isf_usa_agent" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            // } },
            { 
                title: "Arr Not",
                field: "arrival_notice_email",
                headerTooltip: "Arrival Notice",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue() || 'Pending';
                    const color = value === 'Done' ? '#28a745' : '#dc3545';
                    const iconClass = value === 'Done' ? 'fa-check-circle' : 'fa-circle';
                    
                    return `
                        <div class="status-container">
                            <i class="fas ${iconClass}" style="font-size: ${value === 'Done' ? '16px' : '14px'}; cursor: pointer; color: ${color};"></i>
                            <select class="form-select form-select-sm auto-save status-select"
                                data-column="arrival_notice_email"
                                style="display: none; width: 90px;">
                                <option value="Pending" ${value==='Pending'?'selected':''}>Pending</option>
                                <option value="Done" ${value==='Done'?'selected':''}>Done</option>
                            </select>
                        </div>
                    `;
                }
            },
            { 
                title: "CHA",
                field: "invoice_send_to_dominic",
                headerTooltip: "CHA Work",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue() || 'Pending';
                    const color = value === 'Done' ? '#28a745' : '#dc3545';
                    const iconClass = value === 'Done' ? 'fa-check-circle' : 'fa-circle';
                    
                    return `
                        <div class="status-container">
                            <i class="fas ${iconClass}" style="font-size: ${value === 'Done' ? '16px' : '14px'}; cursor: pointer; color: ${color};"></i>
                            <select class="form-select form-select-sm auto-save status-select"
                                data-column="invoice_send_to_dominic"
                                style="display: none; width: 90px;">
                                <option value="Pending" ${value==='Pending'?'selected':''}>Pending</option>
                                <option value="Done" ${value==='Done'?'selected':''}>Done</option>
                            </select>
                        </div>
                    `;
                }
            },
            { 
                title: "Duty", 
                field: "duty_calcu",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue() || 'Pending';
                    const color = value === 'Done' ? '#28a745' : '#dc3545';
                    const iconClass = value === 'Done' ? 'fa-check-circle' : 'fa-circle';
                    
                    return `
                        <div class="status-container">
                            <i class="fas ${iconClass}" style="font-size: ${value === 'Done' ? '16px' : '14px'}; cursor: pointer; color: ${color};"></i>
                            <select class="form-select form-select-sm auto-save status-select"
                                data-column="duty_calcu"
                                style="display: none; width: 90px;">
                                <option value="Pending" ${value==='Pending'?'selected':''}>Pending</option>
                                <option value="Done" ${value==='Done'?'selected':''}>Done</option>
                            </select>
                        </div>
                    `;
                }
            },
            {
                title: "Value",
                field: "invoice_value",
                headerSort: false,
                minWidth: 120,
                formatter: function(cell) {
                    const value = cell.getValue();
                    const roundedValue = value ? Math.round(value) : 0;

                    if (roundedValue > 0) {
                        return `
                            <div class="d-flex justify-content-center align-items-center">
                                <span class="badge bg-success text-white fw-bold" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">
                                    $${roundedValue.toLocaleString()}
                                </span>
                            </div>`;
                    }
                    return `<div class="text-center text-muted">-</div>`;
                }
            },
            {
                title: "Due",
                field: "balance",
                headerSort: false,
                minWidth: 100,
                formatter: function(cell) {
                    const value = cell.getValue();
                    const displayValue = value ?? 0;
                    const roundedValue = Math.round(displayValue);
                    const badgeClass = displayValue > 0 ? 'bg-danger' : 'bg-success';
                    return `<span class="badge ${badgeClass} text-white" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">$${roundedValue.toLocaleString()}</span>`;
                }
            },
            { 
                title: "Status",
                field: "status",
                headerSort: false,
                minWidth: 80,
                formatter: function (cell) {
                    let value = cell.getValue();

                    // Set default to Planning if empty
                    if (!value) {
                        value = 'Planning';
                        cell.setValue('Planning');
                    }

                    // On Sea → ship icon (same fa-ship as China Load); other statuses keep dots
                    let statusIcon;
                    if (value === 'On Sea') {
                        statusIcon = `<i class="fas fa-ship" style="font-size: 16px; cursor: pointer; color: #0d6efd;"></i>`;
                    } else {
                        let dotColor = '#ffff00'; // Planning / Pre-Load
                        if (value === 'Landed') {
                            dotColor = '#654321';
                        } else if (value === 'Arrived') {
                            dotColor = '#00ff00';
                        }
                        statusIcon = `<i class="fas fa-circle" style="font-size: 14px; cursor: pointer; color: ${dotColor};"></i>`;
                    }

                    return `
                        <div class="status-container" title="${value === 'Planning' ? 'Pre-Load' : value}">
                            ${statusIcon}
                            <select class="form-select form-select-sm auto-save status-select"
                                data-column="status"
                                style="display: none; min-width: 90px; width: 120px;">
                                <option value="Planning" ${value === 'Planning' ? 'selected' : ''}>Pre-Load</option>
                                <option value="On Sea" ${value === 'On Sea' ? 'selected' : ''}>On Sea</option>
                                <option value="Landed" ${value === 'Landed' ? 'selected' : ''}>Landed</option>
                                <option value="Arrived" ${value === 'Arrived' ? 'selected' : ''}>Arrived</option>
                            </select>
                        </div>
                    `;
                },
            },
            {
                /* Action column — edit, history, archive. */
                title: "Action",
                field: "_action",
                headerSort: false,
                hozAlign: "center",
                minWidth: 90,
                width: 100,
                frozen: false,
                formatter: function(cell) {
                    const rowData = cell.getRow().getData();
                    const id  = rowData.id ?? '';
                    const sl  = rowData.container_sl_no ?? '';
                    return `
                        <div class="d-flex justify-content-center align-items-center gap-2">
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 ost-edit-btn text-primary"
                                    data-id="${id}"
                                    data-sl="${sl}"
                                    title="Edit all columns for container ${sl}"
                                    style="border:none;line-height:1;">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 view-history-btn text-warning"
                                    data-id="${id}"
                                    title="Change history"
                                    style="border:none;line-height:1;">
                                <i class="fas fa-history"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 ost-archive-btn text-danger"
                                    data-id="${id}"
                                    data-sl="${sl}"
                                    title="Archive this row"
                                    style="border:none;line-height:1;">
                                <i class="fas fa-box-archive"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
    });

    // Initialize badge counts on page load
    console.log('Table Data:', tableData);
    updateBadgeCounts();

    /* ──────────────────────────────────────────────────────────────────────
       Action column wiring — Edit (pencil) & Archive (box) buttons.
       Uses event delegation off the table container so newly-rendered rows
       (after pagination, filter, etc.) keep working without rebinding.
       ────────────────────────────────────────────────────────────────────── */
    const ostCsrf       = '{{ csrf_token() }}';
    const ostArchiveUrl = '{{ route('on.sea.transit.archive') }}';
    const ostUpdateUrl  = '{{ route('on.sea.transit.update-row') }}';
    const ostEditModalEl = document.getElementById('ostEditRowModal');
    const ostEditModal   = ostEditModalEl ? new bootstrap.Modal(ostEditModalEl) : null;
    const ostEditForm    = document.getElementById('ostEditRowForm');

    function ostFormatDateForInput(raw) {
        if (!raw) return '';
        // Accept either "YYYY-MM-DD …" or full datetime — take the date prefix.
        const str = String(raw);
        return str.length >= 10 ? str.substring(0, 10) : '';
    }

    function ostOpenEditModal(rowData) {
        if (!ostEditModal) return;
        document.getElementById('ostEditRowId').value                  = rowData.id ?? '';
        document.getElementById('ostEditRowContainerSlNo').value       = rowData.container_sl_no ?? '';
        document.getElementById('ostEditRowContainerSlNoDisplay').value= rowData.container_sl_no ?? '';
        document.getElementById('ostEditRowSlLabel').textContent       = rowData.container_sl_no ?? '—';

        // China Load fields (editable) — prefer row data, fall back to chinaLoadMap.
        const china = chinaLoadMap[rowData.container_sl_no] || {};
        document.getElementById('ostEditChinaMbl').value = rowData.mbl || china.mbl || '';
        document.getElementById('ostEditChinaObl').value = rowData.obl || china.obl || '';
        document.getElementById('ostEditChinaContainerNo').value = rowData.container_no || china.container_no || '';
        document.getElementById('ostEditChinaItem').value = rowData.item || china.item || '';

        // Populate every form field that has a matching `name="<column>"`.
        const fieldMap = {
            status: rowData.status,
            bl_check: rowData.bl_check,
            bl_link: rowData.bl_link,
            isf: rowData.isf,
            isf_usa_agent: rowData.isf_usa_agent,
            etd: ostFormatDateForInput(rowData.etd),
            eta_port: ostFormatDateForInput(rowData.eta_port),
            port_arrival: rowData.port_arrival ?? '',
            eta_date_ohio: ostFormatDateForInput(rowData.eta_date_ohio),
            duty_calcu: rowData.duty_calcu,
            invoice_send_to_dominic: rowData.invoice_send_to_dominic,
            arrival_notice_email: rowData.arrival_notice_email,
            freight: rowData.freight ?? '',
            paid: rowData.paid ?? '',
            details: rowData.details ?? ''
        };
        for (const [name, val] of Object.entries(fieldMap)) {
            const el = ostEditForm.querySelector(`[name="${name}"]`);
            if (el) el.value = val ?? '';
        }

        // Value from Transit Container Inv (hidden; used for Due calc)
        const transitInv = parseFloat(rowData.transit_inv_value ?? rowData.invoice_value) || 0;
        document.getElementById('ostEditInvoiceValueHidden').value = transitInv;

        // Payments payload: { supplier: [...], agent: [...] }
        const payments = ostNormalizePaymentsPayload(rowData.supplier_payments, rowData);
        ostSetPaymentsPayload(payments);
        document.getElementById('ostSpFreightRowInput').value = parseFloat(rowData.freight) || 0;
        ostSyncMoneyFieldsFromPayments();
        ostRebuildAgentSelect(rowData.agent || '');

        ostEditModal.show();
    }

    /* ── Payments modal (Supplier + Agent categories) ───────────────────── */
    const ostSupplierModalEl = document.getElementById('ostSupplierPaymentsModal');
    const ostSupplierModal = ostSupplierModalEl ? new bootstrap.Modal(ostSupplierModalEl) : null;
    const ostSupplierPayTbody = document.getElementById('ostSupplierPayTbody');
    const ostAgentPayTbody = document.getElementById('ostAgentPayTbody');

    function ostEmptyPaymentsPayload() {
        return { supplier: [], agent: [] };
    }

    function ostNormalizePaymentsPayload(raw, rowData) {
        const empty = ostEmptyPaymentsPayload();
        if (!raw) {
            return empty;
        }
        // New shape
        if (!Array.isArray(raw) && typeof raw === 'object') {
            empty.supplier = Array.isArray(raw.supplier) ? raw.supplier.map(p => ({
                name: p.name || p.supplier_name || '',
                amount: p.amount ?? 0,
                paid: p.paid ?? 0,
                balance: p.balance ?? ((parseFloat(p.amount) || 0) - (parseFloat(p.paid) || 0))
            })) : [];
            empty.agent = Array.isArray(raw.agent) ? raw.agent.map(p => ({
                agent: p.agent || p.name || '',
                amount: p.amount ?? 0,
                paid: p.paid ?? 0,
                balance: p.balance ?? ((parseFloat(p.amount) || 0) - (parseFloat(p.paid) || 0))
            })) : [];
            return empty;
        }
        // Legacy flat array
        if (Array.isArray(raw)) {
            raw.forEach(p => {
                if (p.agent || p.category === 'agent') {
                    empty.agent.push({
                        agent: p.agent || p.name || '',
                        amount: p.amount ?? 0,
                        paid: p.paid ?? 0,
                        balance: p.balance ?? ((parseFloat(p.amount) || 0) - (parseFloat(p.paid) || 0))
                    });
                } else {
                    empty.supplier.push({
                        name: p.name || p.supplier_name || '',
                        amount: p.amount ?? 0,
                        paid: p.paid ?? 0,
                        balance: p.balance ?? ((parseFloat(p.amount) || 0) - (parseFloat(p.paid) || 0))
                    });
                }
            });
        }
        return empty;
    }

    function ostParsePaymentsFromHidden() {
        try {
            const raw = document.getElementById('ostSupplierPaymentsJson').value || '{}';
            return ostNormalizePaymentsPayload(JSON.parse(raw), null);
        } catch (e) {
            return ostEmptyPaymentsPayload();
        }
    }

    function ostSetPaymentsPayload(payments) {
        document.getElementById('ostSupplierPaymentsJson').value = JSON.stringify(payments || ostEmptyPaymentsPayload());
    }

    function ostGetFreightRowValue() {
        return parseFloat(document.getElementById('ostSpFreightRowInput')?.value) || 0;
    }

    function ostAllPaymentLines(payload) {
        const p = payload || ostEmptyPaymentsPayload();
        return [...(p.supplier || []), ...(p.agent || [])];
    }

    function ostHasPaymentLines(payload) {
        return ostAllPaymentLines(payload).some(line => {
            const name = String(line.name || line.agent || '').trim();
            const amount = parseFloat(line.amount) || 0;
            const paid = parseFloat(line.paid) || 0;
            return name !== '' || amount !== 0 || paid !== 0;
        });
    }

    function ostPaymentsTotals(payload, freightOverride) {
        let amount = 0, paid = 0;
        ostAllPaymentLines(payload).forEach(line => {
            amount += parseFloat(line.amount) || 0;
            paid += parseFloat(line.paid) || 0;
        });
        const freight = freightOverride != null ? freightOverride : ostGetFreightRowValue();
        return {
            amount: Math.round(amount * 100) / 100,
            freight: Math.round(freight * 100) / 100,
            paid: Math.round(paid * 100) / 100,
            balance: Math.round((amount + freight - paid) * 100) / 100
        };
    }

    function ostComputeDue(transitInv, paymentTotals, payload) {
        if (ostHasPaymentLines(payload)) {
            return Math.round(paymentTotals.balance);
        }
        return Math.round(transitInv + paymentTotals.freight - paymentTotals.paid);
    }

    function ostSetDueDisplay(due) {
        const el = document.getElementById('ostEditRowBalanceDisplay');
        if (el) el.value = due;
    }

    function ostSyncMoneyFieldsFromPayments(payloadOverride) {
        // Value stays Transit Container Inv; Freight/Paid/Due come from payments
        const transitInv = parseFloat(document.getElementById('ostEditInvoiceValueHidden')?.value) || 0;
        const payload = payloadOverride || ostParsePaymentsFromHidden();
        const paymentTotals = ostPaymentsTotals(payload, ostGetFreightRowValue());
        const frEl = ostEditForm.querySelector('[name="freight"]');
        const pdEl = ostEditForm.querySelector('[name="paid"]');
        if (frEl) frEl.value = paymentTotals.freight.toFixed(2);
        if (pdEl) pdEl.value = paymentTotals.paid.toFixed(2);
        ostSetDueDisplay(ostComputeDue(transitInv, paymentTotals, payload));
    }

    /* Agent dropdown — defaults + user-added options (localStorage) */
    const OST_AGENT_DEFAULTS = ['Roman', 'Stephen'];
    const OST_AGENT_STORAGE_KEY = 'ost_custom_agents';

    function ostGetCustomAgents() {
        try {
            const raw = localStorage.getItem(OST_AGENT_STORAGE_KEY);
            const list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list.filter(a => typeof a === 'string' && a.trim()) : [];
        } catch (e) {
            return [];
        }
    }

    function ostSaveCustomAgent(name) {
        const trimmed = String(name || '').trim();
        if (!trimmed) return null;
        const customs = ostGetCustomAgents();
        if (!OST_AGENT_DEFAULTS.includes(trimmed) && !customs.includes(trimmed)) {
            customs.push(trimmed);
            localStorage.setItem(OST_AGENT_STORAGE_KEY, JSON.stringify(customs));
        }
        return trimmed;
    }

    function ostAgentOptionsHtml(selected) {
        const current = selected || '';
        const customs = ostGetCustomAgents();
        const names = [...OST_AGENT_DEFAULTS];
        customs.forEach(n => { if (!names.includes(n)) names.push(n); });
        if (current && !names.includes(current)) names.push(current);
        let html = '<option value="">—</option>';
        names.forEach(name => {
            html += `<option value="${name.replace(/"/g, '&quot;')}" ${name === current ? 'selected' : ''}>${name}</option>`;
        });
        html += '<option value="__add_more__">+ Add more…</option>';
        return html;
    }

    function ostRefreshAllRowAgentSelects() {
        ostAgentPayTbody.querySelectorAll('.ost-sp-agent').forEach(sel => {
            const cur = sel.value === '__add_more__' ? (sel.dataset.prevValue || '') : sel.value;
            sel.innerHTML = ostAgentOptionsHtml(cur);
            if (cur) sel.value = cur;
        });
    }

    function ostRebuildAgentSelect(selected) {
        const sel = document.getElementById('ostAgentSelect');
        if (!sel) return;
        const current = selected || '';
        const customs = ostGetCustomAgents();
        if (current && !OST_AGENT_DEFAULTS.includes(current) && !customs.includes(current)) {
            customs.push(current);
        }
        sel.innerHTML = '<option value="">—</option>';
        [...OST_AGENT_DEFAULTS, ...customs].forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === current) opt.selected = true;
            sel.appendChild(opt);
        });
        const addOpt = document.createElement('option');
        addOpt.value = '__add_more__';
        addOpt.textContent = '+ Add more…';
        sel.appendChild(addOpt);
        if (current) sel.value = current;
    }

    document.getElementById('ostAgentSelect').addEventListener('change', function () {
        if (this.value !== '__add_more__') return;
        const prev = this.dataset.prevValue || '';
        const name = prompt('Enter new agent name:');
        if (name && name.trim()) {
            const saved = ostSaveCustomAgent(name.trim());
            ostRebuildAgentSelect(saved);
            ostRefreshAllRowAgentSelects();
        } else {
            this.value = prev;
        }
    });

    document.getElementById('ostAgentSelect').addEventListener('focus', function () {
        this.dataset.prevValue = this.value === '__add_more__' ? '' : this.value;
    });

    function ostLineBalance(amount, paid) {
        return ((parseFloat(amount) || 0) - (parseFloat(paid) || 0)).toFixed(2);
    }

    function ostReadSupplierCategoryRows() {
        const rows = [];
        ostSupplierPayTbody.querySelectorAll('tr').forEach(tr => {
            const name = (tr.querySelector('.ost-sp-name')?.value || '').trim();
            const amount = parseFloat(tr.querySelector('.ost-sp-amount')?.value) || 0;
            const paid = parseFloat(tr.querySelector('.ost-sp-paid')?.value) || 0;
            if (!name && amount === 0 && paid === 0) return;
            rows.push({
                name,
                amount: Math.round(amount * 100) / 100,
                paid: Math.round(paid * 100) / 100,
                balance: Math.round((amount - paid) * 100) / 100
            });
        });
        return rows;
    }

    function ostReadAgentCategoryRows() {
        const rows = [];
        ostAgentPayTbody.querySelectorAll('tr').forEach(tr => {
            const agentSel = tr.querySelector('.ost-sp-agent');
            let agent = (agentSel?.value || '').trim();
            if (agent === '__add_more__') agent = '';
            const amount = parseFloat(tr.querySelector('.ost-sp-amount')?.value) || 0;
            const paid = parseFloat(tr.querySelector('.ost-sp-paid')?.value) || 0;
            if (!agent && amount === 0 && paid === 0) return;
            rows.push({
                agent,
                amount: Math.round(amount * 100) / 100,
                paid: Math.round(paid * 100) / 100,
                balance: Math.round((amount - paid) * 100) / 100
            });
        });
        return rows;
    }

    function ostReadPaymentsFromTables() {
        return {
            supplier: ostReadSupplierCategoryRows(),
            agent: ostReadAgentCategoryRows()
        };
    }

    function ostUpdatePaymentsFooter() {
        const payload = ostReadPaymentsFromTables();
        const totals = ostPaymentsTotals(payload, ostGetFreightRowValue());
        document.getElementById('ostSpTotalAmount').textContent = totals.amount.toFixed(2);
        document.getElementById('ostSpTotalPaid').textContent = totals.paid.toFixed(2);
        document.getElementById('ostSpTotalBalance').textContent = totals.balance.toFixed(2);
        const transitInv = parseFloat(document.getElementById('ostEditInvoiceValueHidden')?.value) || 0;
        ostSetDueDisplay(ostComputeDue(transitInv, totals, payload));
    }

    function ostAddSupplierCategoryRow(line) {
        const data = line || { name: '', amount: '', paid: '' };
        const amount = data.amount === '' || data.amount == null ? '' : Number(data.amount);
        const paid = data.paid === '' || data.paid == null ? '' : Number(data.paid);
        const hasAny = amount !== '' || paid !== '';
        const bal = hasAny ? ostLineBalance(amount, paid) : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" class="form-control form-control-sm ost-sp-name" value="${(data.name || '').replace(/"/g, '&quot;')}" placeholder="Supplier name"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm ost-sp-amount" value="${amount}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm ost-sp-paid" value="${paid}"></td>
            <td><input type="number" class="form-control form-control-sm ost-sp-balance" value="${bal}" readonly tabindex="-1"></td>
            <td class="text-center">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 ost-sp-remove" title="Remove row">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        ostSupplierPayTbody.appendChild(tr);
    }

    function ostAddAgentCategoryRow(line) {
        const data = line || { agent: '', amount: '', paid: '' };
        const agent = data.agent || '';
        const amount = data.amount === '' || data.amount == null ? '' : Number(data.amount);
        const paid = data.paid === '' || data.paid == null ? '' : Number(data.paid);
        const hasAny = amount !== '' || paid !== '';
        const bal = hasAny ? ostLineBalance(amount, paid) : '';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select class="form-select form-select-sm ost-sp-agent">${ostAgentOptionsHtml(agent)}</select></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm ost-sp-amount" value="${amount}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm ost-sp-paid" value="${paid}"></td>
            <td><input type="number" class="form-control form-control-sm ost-sp-balance" value="${bal}" readonly tabindex="-1"></td>
            <td class="text-center">
                <button type="button" class="btn btn-link btn-sm text-danger p-0 ost-sp-remove" title="Remove row">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        ostAgentPayTbody.appendChild(tr);
    }

    function ostRenderPaymentsTables(payload) {
        const data = ostNormalizePaymentsPayload(payload, null);
        ostSupplierPayTbody.innerHTML = '';
        ostAgentPayTbody.innerHTML = '';
        const suppliers = data.supplier.length ? data.supplier : [{ name: '', amount: '', paid: '' }];
        const agents = data.agent.length ? data.agent : [{ agent: '', amount: '', paid: '' }];
        suppliers.forEach(p => ostAddSupplierCategoryRow(p));
        agents.forEach(p => ostAddAgentCategoryRow(p));
        ostUpdatePaymentsFooter();
    }

    function ostOnPayTbodyInput(e) {
        const tr = e.target.closest('tr');
        if (!tr) return;
        if (e.target.classList.contains('ost-sp-amount') || e.target.classList.contains('ost-sp-paid')) {
            const amount = parseFloat(tr.querySelector('.ost-sp-amount').value) || 0;
            const paid = parseFloat(tr.querySelector('.ost-sp-paid').value) || 0;
            tr.querySelector('.ost-sp-balance').value = ostLineBalance(amount, paid);
        }
        ostUpdatePaymentsFooter();
    }

    function ostOnPayTbodyClick(tbody, addEmptyRowFn, e) {
        const btn = e.target.closest('.ost-sp-remove');
        if (!btn) return;
        const tr = btn.closest('tr');
        if (tr) tr.remove();
        if (!tbody.querySelector('tr')) addEmptyRowFn();
        ostUpdatePaymentsFooter();
    }

    let ostPaymentsAppliedThisOpen = false;

    document.getElementById('ostOpenSupplierPaymentsBtn').addEventListener('click', function () {
        const sl = document.getElementById('ostEditRowContainerSlNo').value || '—';
        document.getElementById('ostSupplierPaymentsSlLabel').textContent = sl;
        const freightEl = ostEditForm.querySelector('[name="freight"]');
        document.getElementById('ostSpFreightRowInput').value = parseFloat(freightEl?.value) || 0;
        ostPaymentsAppliedThisOpen = false;
        ostRenderPaymentsTables(ostParsePaymentsFromHidden());
        if (ostSupplierModal) ostSupplierModal.show();
    });

    if (ostSupplierModalEl) {
        ostSupplierModalEl.addEventListener('hidden.bs.modal', function () {
            if (!ostPaymentsAppliedThisOpen) {
                ostSyncMoneyFieldsFromPayments();
            }
        });
    }

    document.getElementById('ostAddSupplierPaymentRowBtn').addEventListener('click', function () {
        ostAddSupplierCategoryRow();
        ostUpdatePaymentsFooter();
    });

    document.getElementById('ostAddAgentPaymentRowBtn').addEventListener('click', function () {
        ostAddAgentCategoryRow();
        ostUpdatePaymentsFooter();
    });

    document.getElementById('ostSpFreightRowInput').addEventListener('input', function () {
        ostUpdatePaymentsFooter();
    });

    ostSupplierPayTbody.addEventListener('input', ostOnPayTbodyInput);
    ostAgentPayTbody.addEventListener('input', ostOnPayTbodyInput);
    ostSupplierPayTbody.addEventListener('click', (e) => ostOnPayTbodyClick(ostSupplierPayTbody, ostAddSupplierCategoryRow, e));
    ostAgentPayTbody.addEventListener('click', (e) => ostOnPayTbodyClick(ostAgentPayTbody, ostAddAgentCategoryRow, e));

    ostAgentPayTbody.addEventListener('change', function (e) {
        const sel = e.target.closest('.ost-sp-agent');
        if (!sel) return;
        if (sel.value === '__add_more__') {
            const prev = sel.dataset.prevValue || '';
            const name = prompt('Enter new agent name:');
            if (name && name.trim()) {
                const saved = ostSaveCustomAgent(name.trim());
                ostRefreshAllRowAgentSelects();
                ostRebuildAgentSelect(document.getElementById('ostAgentSelect').value || saved);
                sel.value = saved;
            } else {
                sel.value = prev;
            }
            return;
        }
        sel.dataset.prevValue = sel.value;
    });

    ostAgentPayTbody.addEventListener('focusin', function (e) {
        const sel = e.target.closest('.ost-sp-agent');
        if (!sel) return;
        sel.dataset.prevValue = sel.value === '__add_more__' ? '' : sel.value;
    });

    document.getElementById('ostApplySupplierPaymentsBtn').addEventListener('click', function () {
        // Full replace of previous payments (not merge) + persist immediately.
        const payments = ostReadPaymentsFromTables();
        ostSetPaymentsPayload(payments);
        const firstAgent = payments.agent.find(p => p.agent)?.agent || '';
        if (firstAgent) {
            ostRebuildAgentSelect(firstAgent);
        }
        ostPaymentsAppliedThisOpen = true;
        ostSyncMoneyFieldsFromPayments(payments);

        const sl = document.getElementById('ostEditRowContainerSlNo').value;
        if (!sl) {
            if (ostSupplierModal) ostSupplierModal.hide();
            return;
        }

        const freight = ostGetFreightRowValue();
        const paid = parseFloat(document.getElementById('ostEditPaidHidden')?.value) || 0;
        const invoiceValue = parseFloat(document.getElementById('ostEditInvoiceValueHidden')?.value) || 0;
        const applyBtn = document.getElementById('ostApplySupplierPaymentsBtn');
        applyBtn.disabled = true;

        fetch(ostUpdateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': ostCsrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                container_sl_no: sl,
                supplier_payments: payments,
                freight: freight,
                paid: paid,
                invoice_value: invoiceValue
            })
        })
        .then(r => r.json())
        .then(resp => {
            if (!resp || resp.success === false) {
                alert(resp && resp.message ? resp.message : 'Failed to apply payments');
                return;
            }
            const idx = tableData.findIndex(r => String(r.container_sl_no) === String(sl));
            if (idx !== -1) {
                tableData[idx].supplier_payments = resp.supplier_payments ?? payments;
                tableData[idx].freight = resp.freight ?? freight;
                tableData[idx].paid = resp.paid ?? paid;
                tableData[idx].invoice_value = resp.invoice_value ?? invoiceValue;
                tableData[idx].balance = resp.balance ?? tableData[idx].balance;
                const row = table.getRows().find(rr => String(rr.getData().container_sl_no) === String(sl));
                if (row) row.update(tableData[idx]);
            }
            updateBadgeCounts();
            if (ostSupplierModal) ostSupplierModal.hide();
        })
        .catch(() => alert('Failed to apply payments'))
        .finally(() => { applyBtn.disabled = false; });
    });

    document.getElementById('ostEditRowSaveBtn').addEventListener('click', function () {
        const sl = document.getElementById('ostEditRowContainerSlNo').value;
        if (!sl) return;
        const formData = new FormData(ostEditForm);
        const payload = {};
        formData.forEach((val, key) => {
            if (key === 'supplier_payments_json') return;
            payload[key] = val;
        });
        payload.supplier_payments = ostParsePaymentsFromHidden();

        fetch(ostUpdateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': ostCsrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(resp => {
            if (!resp || resp.success === false) {
                alert(resp && resp.message ? resp.message : 'Failed to save row');
                return;
            }
            // Merge fresh values back into tableData and refresh the row in-place.
            const idx = tableData.findIndex(r => r.container_sl_no === sl);
            if (idx !== -1) {
                const chinaResp = resp.china_load || {};
                Object.assign(tableData[idx], payload, {
                    balance: resp.balance ?? tableData[idx].balance,
                    invoice_value: resp.invoice_value ?? payload.invoice_value,
                    freight: resp.freight ?? payload.freight,
                    paid: resp.paid ?? payload.paid,
                    supplier_payments: resp.supplier_payments ?? payload.supplier_payments,
                    mbl: chinaResp.mbl ?? payload.mbl ?? tableData[idx].mbl,
                    obl: chinaResp.obl ?? payload.obl ?? tableData[idx].obl,
                    container_no: chinaResp.container_no ?? payload.container_no ?? tableData[idx].container_no,
                    item: chinaResp.item ?? payload.item ?? tableData[idx].item
                });
                chinaLoadMap[sl] = {
                    ...(chinaLoadMap[sl] || {}),
                    mbl: tableData[idx].mbl,
                    obl: tableData[idx].obl,
                    container_no: tableData[idx].container_no,
                    item: tableData[idx].item
                };
                const row = table.getRows().find(rr => rr.getData().container_sl_no === sl);
                if (row) row.update(tableData[idx]);
            }
            updateBadgeCounts();
            ostEditModal.hide();
        })
        .catch(() => alert('Failed to save row'));
    });

    // Delegated click handler — works for both Edit and Archive icons.
    document.getElementById('on-sea-transit-table').addEventListener('click', function (e) {
        const editBtn    = e.target.closest('.ost-edit-btn');
        const archiveBtn = e.target.closest('.ost-archive-btn');
        if (!editBtn && !archiveBtn) return;

        const btn  = editBtn || archiveBtn;
        const sl   = btn.getAttribute('data-sl');
        const id   = btn.getAttribute('data-id');
        const rowData = tableData.find(r => String(r.container_sl_no) === String(sl));
        if (!rowData) return;

        if (editBtn) {
            ostOpenEditModal(rowData);
            return;
        }

        // Archive flow — confirm, post, then drop the row out of the table.
        if (!confirm(`Archive container ${sl}? It will disappear from this board (still in the database).`)) {
            return;
        }
        fetch(ostArchiveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': ostCsrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ id: id, container_sl_no: sl })
        })
        .then(r => r.json())
        .then(resp => {
            if (!resp || resp.success === false) {
                alert(resp && resp.message ? resp.message : 'Failed to archive');
                return;
            }
            const idx = tableData.findIndex(r => String(r.container_sl_no) === String(sl));
            if (idx !== -1) tableData.splice(idx, 1);
            const row = table.getRows().find(rr => String(rr.getData().container_sl_no) === String(sl));
            if (row) row.delete();
            updateBadgeCounts();
        })
        .catch(() => alert('Failed to archive'));
    });
    
    // Copy to clipboard function
    window.copyToClipboard = function(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            const icon = button.querySelector('i');
            const originalClass = icon.className;
            
            // Change to check icon
            icon.className = 'fas fa-check';
            button.classList.add('copied');
            
            // Reset after 2 seconds
            setTimeout(function() {
                icon.className = originalClass;
                button.classList.remove('copied');
            }, 2000);
        }).catch(function(err) {
            console.error('Failed to copy text: ', err);
            alert('Failed to copy to clipboard');
        });
    };
    
    // Handle hovering on port arrival dots to show dropdown
    document.addEventListener('mouseenter', function(e) {
        if (e.target.classList.contains('fa-circle') && e.target.closest('.port-container')) {
            const container = e.target.closest('.port-container');
            const select = container.querySelector('.port-select');
            
            if (select) {
                select.style.display = 'inline-block';
                
                // Hide when mouse leaves the container
                container.addEventListener('mouseleave', function() {
                    select.style.display = 'none';
                }, { once: true });
            }
        }
    }, true);
    
    // Handle clicking on status dots / ship icon to show dropdown
    document.addEventListener('click', function(e) {
        if ((e.target.classList.contains('fa-circle') || e.target.classList.contains('fa-check-circle') || e.target.classList.contains('fa-ship')) && e.target.closest('.status-container')) {
            const container = e.target.closest('.status-container');
            const icon = container.querySelector('.fa-circle, .fa-check-circle, .fa-ship');
            const select = container.querySelector('.status-select');
            
            if (select) {
                // Hide icon, show select
                icon.style.display = 'none';
                select.style.display = 'inline-block';
                select.focus();
                
                // When select loses focus or changes, show icon again
                select.addEventListener('blur', function() {
                    setTimeout(function() {
                        if (select) {
                            select.style.display = 'none';
                            if (icon) icon.style.display = 'inline-block';
                        }
                    }, 200);
                }, { once: true });
            }
        }
        
        // Handle clicking on date display to show date input
        if (e.target.classList.contains('date-display') || e.target.closest('.date-display')) {
            const container = e.target.classList.contains('date-display') ? e.target : e.target.closest('.date-display');
            const display = container.querySelector('.date-display') || container;
            const input = container.querySelector('.date-input');
            
            if (input) {
                display.style.display = 'none';
                input.style.display = 'inline-block';
                input.focus();
                input.showPicker();
                
                // When input loses focus or changes, show display again
                const hideInput = function() {
                    setTimeout(function() {
                        if (input && input.value) {
                            // Update display with new formatted date
                            const date = new Date(input.value);
                            const day = date.getDate();
                            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            const month = monthNames[date.getMonth()];
                            const textNode = display.childNodes[0];
                            if (textNode) {
                                textNode.textContent = `${day} ${month}`;
                            }
                        }
                        input.style.display = 'none';
                        display.style.display = 'block';
                    }, 200);
                };
                
                input.addEventListener('blur', hideInput, { once: true });
                input.addEventListener('change', hideInput, { once: true });
            }
        }
    });
    
    // Position hover popups dynamically
    let currentActiveContainer = null;
    
    document.addEventListener('mouseover', function(e) {
        const hoverContainer = e.target.closest('.hover-container');
        const dot = e.target.closest('.green-dot');
        
        // Only activate if hovering directly over the dot
        if (hoverContainer && dot && hoverContainer.contains(dot)) {
            // Remove active class from all other containers
            document.querySelectorAll('.hover-container.active').forEach(function(container) {
                if (container !== hoverContainer) {
                    container.classList.remove('active');
                }
            });
            
            const popup = hoverContainer.querySelector('.hover-popup');
            
            if (popup) {
                // Add active class to show popup
                hoverContainer.classList.add('active');
                currentActiveContainer = hoverContainer;
                
                // First, make it visible to calculate its size
                popup.style.display = 'block';
                popup.style.visibility = 'hidden';
                
                const rect = dot.getBoundingClientRect();
                const popupRect = popup.getBoundingClientRect();
                
                let left = rect.left + (rect.width / 2);
                let top = rect.top - 15;
                
                // Check if popup goes off the right edge
                if (left + (popupRect.width / 2) > window.innerWidth) {
                    left = window.innerWidth - (popupRect.width / 2) - 20;
                }
                
                // Check if popup goes off the left edge
                if (left - (popupRect.width / 2) < 0) {
                    left = (popupRect.width / 2) + 20;
                }
                
                // Check if popup goes off the top
                if (top - popupRect.height < 0) {
                    // Show below the dot instead
                    top = rect.bottom + 15;
                    popup.style.transform = 'translate(-50%, 0)';
                } else {
                    popup.style.transform = 'translate(-50%, -100%)';
                }
                
                popup.style.left = left + 'px';
                popup.style.top = top + 'px';
                popup.style.visibility = 'visible';
            }
        }
    });
    
    document.addEventListener('mouseout', function(e) {
        const hoverContainer = e.target.closest('.hover-container');
        if (hoverContainer && !hoverContainer.contains(e.relatedTarget)) {
            hoverContainer.classList.remove('active');
            if (currentActiveContainer === hoverContainer) {
                currentActiveContainer = null;
            }
        }
    });

    table.setFilter(function(data) {
        return data.status !== 'Arrived';
    });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('auto-save')) {
            const column = e.target.dataset.column;
            const value = e.target.value;
            const rowElement = e.target.closest('.tabulator-row');
            const row = table.getRow(rowElement);
            const rowData = row.getData();

            fetch('/on-sea-transit/inline-update-or-create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    container_sl_no: rowData.container_sl_no,
                    column,
                    value
                })
            }).then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the row data in tableData array
                    const dataIndex = tableData.findIndex(item => item.container_sl_no === rowData.container_sl_no);
                    if (dataIndex !== -1) {
                        tableData[dataIndex][column] = value;
                        
                        // Update balance in tableData if invoice_value or paid changed
                        if ((column === 'invoice_value' || column === 'paid') && data.balance !== undefined) {
                            tableData[dataIndex].balance = data.balance;
                            
                            // Update the balance cell in the table
                            const balanceCell = row.getCell('balance');
                            if (balanceCell) {
                                const displayValue = data.balance ?? 0;
                                const colorClass = displayValue > 0 ? 'text-danger' : 'text-success';
                                balanceCell.getElement().innerHTML = `<span class="fw-bold ${colorClass}">$${parseFloat(displayValue).toFixed(2)}</span>`;
                            }
                        }
                    }
                    
                    // Update badge counts if status, invoice_value, or paid column was changed
                    if (column === 'status' || column === 'invoice_value' || column === 'paid') {
                        updateBadgeCounts();
                    }
                    
                    // Refresh merged BL column (check + link) after either field saves
                    if (column === 'bl_check' || column === 'bl_link') {
                        const blCell = table.getRow(rowElement).getCell('bl_check');
                        if (blCell) {
                            blCell.getRow().update({ [column]: value });
                        }
                    }
                    
                    // Update ISF cell to show icon after value change
                    if (column === 'isf') {
                        const cell = table.getRow(rowElement).getCell(column);
                        if (cell) {
                            let iconHtml = '';
                            if (value === 'USA Done') {
                                iconHtml = `
                                    <div class="status-container">
                                        <i class="fas fa-check-circle" style="font-size: 16px; cursor: pointer; color: #28a745;"></i>
                                        <select class="form-select form-select-sm auto-save status-select"
                                            data-column="isf"
                                            style="display: none; width: 90px;">
                                            <option value="">Select</option>
                                            <option value="China Done">China Done</option>
                                            <option value="USA Done" selected>USA Done</option>
                                        </select>
                                    </div>
                                `;
                            } else if (value === 'China Done') {
                                iconHtml = `
                                    <div class="status-container">
                                        <i class="fas fa-check-circle" style="font-size: 16px; cursor: pointer; color: #ffc107;"></i>
                                        <select class="form-select form-select-sm auto-save status-select"
                                            data-column="isf"
                                            style="display: none; width: 90px;">
                                            <option value="">Select</option>
                                            <option value="China Done" selected>China Done</option>
                                            <option value="USA Done">USA Done</option>
                                        </select>
                                    </div>
                                `;
                            } else {
                                iconHtml = `
                                    <div class="status-container">
                                        <i class="fas fa-circle" style="font-size: 14px; cursor: pointer; color: #dc3545;"></i>
                                        <select class="form-select form-select-sm auto-save status-select"
                                            data-column="isf"
                                            style="display: none; width: 90px;">
                                            <option value="">Select</option>
                                            <option value="China Done">China Done</option>
                                            <option value="USA Done">USA Done</option>
                                        </select>
                                    </div>
                                `;
                            }
                            cell.getElement().innerHTML = iconHtml;
                        }
                    }

                    // Update port_arrival cell to show green dot after value change
                    if (column === 'port_arrival') {
                        const cell = table.getRow(rowElement).getCell(column);
                        if (cell && value) {
                            const portHtml = `
                                <div class="port-container" style="position: relative; display: inline-block;">
                                    <i class="fas fa-circle text-success" style="font-size: 14px; cursor: pointer;"></i>
                                    <select class="form-select form-select-sm auto-save port-select"
                                        data-column="port_arrival"
                                        style="display: none; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 120px; z-index: 1000;">
                                        <option value="">Select</option>
                                        <option value="NYC" ${value==='NYC'?'selected':''}>NYC</option>
                                        <option value="LA" ${value==='LA'?'selected':''}>LA</option>
                                        <option value="PRINCE RUPERT" ${value==='PRINCE RUPERT'?'selected':''}>PRINCE RUPERT</option>
                                        <option value="NORFOLK" ${value==='NORFOLK'?'selected':''}>NORFOLK</option>
                                    </select>
                                </div>
                            `;
                            cell.getElement().innerHTML = portHtml;
                        }
                    }
                    
                    if (column === 'status') {
                        const statusValue = value || 'Planning';
                        let statusIcon;
                        if (statusValue === 'On Sea') {
                            statusIcon = `<i class="fas fa-ship" style="font-size: 16px; cursor: pointer; color: #0d6efd;"></i>`;
                        } else {
                            let dotColor = '#ffff00';
                            if (statusValue === 'Landed') {
                                dotColor = '#654321';
                            } else if (statusValue === 'Arrived') {
                                dotColor = '#00ff00';
                            }
                            statusIcon = `<i class="fas fa-circle" style="font-size: 14px; cursor: pointer; color: ${dotColor};"></i>`;
                        }

                        const statusCell = table.getRow(rowElement).getCell('status');
                        if (statusCell) {
                            statusCell.getElement().innerHTML = `
                                <div class="status-container" title="${statusValue === 'Planning' ? 'Pre-Load' : statusValue}">
                                    ${statusIcon}
                                    <select class="form-select form-select-sm auto-save status-select"
                                        data-column="status"
                                        style="display: none; min-width: 90px; width: 120px;">
                                        <option value="Planning" ${statusValue === 'Planning' ? 'selected' : ''}>Pre-Load</option>
                                        <option value="On Sea" ${statusValue === 'On Sea' ? 'selected' : ''}>On Sea</option>
                                        <option value="Landed" ${statusValue === 'Landed' ? 'selected' : ''}>Landed</option>
                                        <option value="Arrived" ${statusValue === 'Arrived' ? 'selected' : ''}>Arrived</option>
                                    </select>
                                </div>
                            `;
                        }
                    }

                    // Update duty_calcu, invoice_send_to_dominic, arrival_notice_email cells to show dots
                    if (['duty_calcu', 'invoice_send_to_dominic', 'arrival_notice_email'].includes(column)) {
                        const cell = table.getRow(rowElement).getCell(column);
                        if (cell) {
                            const displayValue = value || 'Pending';
                            const color = displayValue === 'Done' ? '#28a745' : '#dc3545';
                            const iconClass = displayValue === 'Done' ? 'fa-check-circle' : 'fa-circle';
                            const fontSize = displayValue === 'Done' ? '16px' : '14px';
                            
                            const dotHtml = `
                                <div class="status-container">
                                    <i class="fas ${iconClass}" style="font-size: ${fontSize}; cursor: pointer; color: ${color};"></i>
                                    <select class="form-select form-select-sm auto-save status-select"
                                        data-column="${column}"
                                        style="display: none; width: 90px;">
                                        <option value="Pending" ${displayValue==='Pending'?'selected':''}>Pending</option>
                                        <option value="Done" ${displayValue==='Done'?'selected':''}>Done</option>
                                    </select>
                                </div>
                            `;
                            cell.getElement().innerHTML = dotHtml;
                        }
                    }
                    
                    // Update date columns (etd, eta_port, eta_date_ohio) to show formatted date
                    if (['etd', 'eta_port', 'eta_date_ohio'].includes(column) && value) {
                        const cell = table.getRow(rowElement).getCell(column);
                        if (cell) {
                            // Format date as "1 Apr"
                            const date = new Date(value);
                            const day = date.getDate();
                            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            const month = monthNames[date.getMonth()];
                            const formattedDate = `${day} ${month}`;
                            
                            // Check if ETA Port date has arrived or passed (turn red with background)
                            let textColor = '#000';
                            let bgColor = 'transparent';
                            let borderRadius = '0';
                            let fontWeight = '600';
                            
                            if (column === 'eta_port') {
                                const etaDate = new Date(value);
                                const today = new Date();
                                today.setHours(0, 0, 0, 0);
                                etaDate.setHours(0, 0, 0, 0);
                                const isOverdue = etaDate <= today;
                                textColor = isOverdue ? '#fff' : '#000';
                                bgColor = isOverdue ? '#dc3545' : 'transparent';
                                borderRadius = isOverdue ? '4px' : '0';
                                fontWeight = isOverdue ? '700' : '600';

                                // Also update the entire row background
                                if (isOverdue) {
                                    rowElement.style.backgroundColor = '#fde8ea';
                                    rowElement.style.color = '#b02030';
                                } else {
                                    rowElement.style.backgroundColor = '';
                                    rowElement.style.color = '';
                                    // Re-apply "On Sea" green if applicable
                                    const currentData = table.getRow(rowElement).getData();
                                    if (currentData.status === 'On Sea') {
                                        rowElement.style.backgroundColor = '#e2f0cb';
                                        rowElement.style.opacity = '0.7';
                                    }
                                }
                            }
                            
                            const dateHtml = `
                                <div class="date-display" style="cursor: pointer; padding: 6px; text-align: center; font-weight: ${fontWeight}; color: ${textColor}; background-color: ${bgColor}; border-radius: ${borderRadius};">
                                    ${formattedDate}
                                    <input type="date" 
                                        class="form-control form-control-sm auto-save date-input" 
                                        data-column="${column}" 
                                        value="${value}"
                                        style="display: none; width: 100%;"
                                        onfocus="this.showPicker()">
                                </div>
                            `;
                            cell.getElement().innerHTML = dateHtml;
                        }
                    }
                }
            });
        }
    });

    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("open-modal-btn")) {
            const slNo = e.target.getAttribute("data-sl");
            const data = chinaLoadMap[slNo];

            if (data) {
                const html = `
                    <div class="d-flex flex-row justify-content-center align-items-stretch gap-4 mb-0" style="flex-wrap:nowrap;">
                        <div class="border rounded-3 p-3 flex-fill text-center shadow-sm" style="min-width:160px;">
                            <div class="fw-semibold text-secondary small text-uppercase mb-1">
                                <i class="fa-solid fa-ship me-1 text-primary"></i>MBL
                            </div>
                            <div class="fs-6 text-primary">${data.mbl || 'N/A'}</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center shadow-sm" style="min-width:160px;">
                            <div class="fw-semibold text-secondary small text-uppercase mb-1">
                                <i class="fa-solid fa-file-lines me-1 text-success"></i>OBL
                            </div>
                            <div class="fs-6 text-success">${data.obl || 'N/A'}</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center shadow-sm" style="min-width:160px;">
                            <div class="fw-semibold text-secondary small text-uppercase mb-1">
                                <i class="fa-solid fa-boxes-stacked me-1 text-warning"></i>Container No
                            </div>
                            <div class="fs-6 text-warning">${data.container_no || 'N/A'}</div>
                        </div>
                        <div class="border rounded-3 p-3 flex-fill text-center shadow-sm" style="min-width:160px;">
                            <div class="fw-semibold text-secondary small text-uppercase mb-1">
                                <i class="fa-solid fa-cube me-1 text-info"></i>Item
                            </div>
                            <div class="fs-6 text-info">${data.item || 'N/A'}</div>
                        </div>
                    </div>
                    `;
                    document.getElementById("chinaLoadModalBody").innerHTML = html;
                    } else {
                        document.getElementById("chinaLoadModalBody").innerHTML = '<div class="alert alert-danger py-2 m-0">No data found</div>';
                    }

            const modal = new bootstrap.Modal(document.getElementById("chinaLoadModal"));
            modal.show();
        }
    });

    document.body.style.zoom = "90%";
    
    // Handle view details button click
    document.addEventListener("click", function (e) {
        if (e.target.closest('.view-details-btn')) {
            const button = e.target.closest('.view-details-btn');
            const recordId = button.getAttribute("data-id");
            const containerSlNo = button.getAttribute("data-container");
            const currentValue = button.getAttribute("data-value");
            
            // Set modal values
            document.getElementById('detailsTextarea').value = currentValue;
            document.getElementById('detailsContainer').value = containerSlNo;
            document.getElementById('detailsRecordId').value = recordId;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("detailsModal"));
            modal.show();
        }
    });
    
    // Save Details
    document.getElementById('saveDetailsBtn').addEventListener('click', function() {
        const containerSlNo = document.getElementById('detailsContainer').value;
        const recordId = document.getElementById('detailsRecordId').value;
        const value = document.getElementById('detailsTextarea').value;
        
        fetch('/on-sea-transit/inline-update-or-create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                container_sl_no: containerSlNo,
                column: 'details',
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the tableData
                const dataIndex = tableData.findIndex(item => item.container_sl_no == containerSlNo);
                if (dataIndex !== -1) {
                    tableData[dataIndex].details = value;
                }
                
                // Reload table data
                table.setData(tableData);
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('detailsModal')).hide();
                
                // Show success message
                alert('Details saved successfully!');
            } else {
                alert('Failed to save details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to save details');
        });
    });
    
    // Handle view history button click
    document.addEventListener("click", function (e) {
        if (e.target.closest('.view-history-btn')) {
            const button = e.target.closest('.view-history-btn');
            const recordId = button.getAttribute("data-id");
            
            // Show modal with loading state
            const modal = new bootstrap.Modal(document.getElementById("detailsHistoryModal"));
            modal.show();
            
            // Reset modal body to loading state
            document.getElementById("detailsHistoryModalBody").innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch history
            fetch(`/on-sea-transit/details-history/${recordId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.history.length > 0) {
                    const esc = (v) => String(v ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    const cellVal = (v) => v
                        ? `<span style="white-space:pre-wrap;word-break:break-word;">${esc(v)}</span>`
                        : '<span class="text-muted fst-italic">Empty</span>';

                    let historyHtml = '<div class="table-responsive"><table class="table table-striped table-bordered table-hover">';
                    historyHtml += '<thead class="table-dark"><tr><th style="width:10%;">Date</th><th style="width:12%;">User</th><th style="width:16%;">Field</th><th style="width:31%;">Old Value</th><th style="width:31%;">New Value</th></tr></thead><tbody>';

                    data.history.forEach(item => {
                        historyHtml += `
                            <tr>
                                <td style="white-space:nowrap;font-weight:700;">${esc(item.date_label || '')}</td>
                                <td style="font-weight:600;">${esc(item.user_name || 'Unknown')}</td>
                                <td>${esc(item.field_label || item.field || '—')}</td>
                                <td style="font-size:0.9rem;padding:10px;">${cellVal(item.old_value)}</td>
                                <td style="font-size:0.9rem;padding:10px;background-color:#f0f8ff;">${cellVal(item.new_value)}</td>
                            </tr>
                        `;
                    });

                    historyHtml += '</tbody></table></div>';
                    document.getElementById("detailsHistoryModalBody").innerHTML = historyHtml;
                } else {
                    document.getElementById("detailsHistoryModalBody").innerHTML = 
                        '<div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>No history available for this record.</div>';
                }
            })
            .catch(error => {
                console.error('Error fetching history:', error);
                document.getElementById("detailsHistoryModalBody").innerHTML = 
                    '<div class="alert alert-danger mb-0"><i class="fas fa-exclamation-circle me-2"></i>Failed to load history.</div>';
            });
        }
    });
    
    // SOP Link Management
    window.loadSopLink = function() {
        const sopLink = localStorage.getItem('onSeaTransit_sopLink');
        const sopButton = document.getElementById('sopButton');
        
        if (sopLink) {
            sopButton.href = sopLink;
            sopButton.style.display = 'inline-flex';
        } else {
            sopButton.href = '#';
            sopButton.style.display = 'inline-flex';
            sopButton.onclick = function(e) {
                e.preventDefault();
                alert('Please set the SOP link by clicking the edit button.');
            };
        }
    }
    
    // Function to open SOP modal
    window.openSopModal = function() {
        const sopLink = localStorage.getItem('onSeaTransit_sopLink') || '';
        document.getElementById('sopLinkInput').value = sopLink;
        const sopModal = new bootstrap.Modal(document.getElementById('sopModal'));
        sopModal.show();
    }
    
    // Save SOP Link
    document.getElementById('saveSopLinkBtn').addEventListener('click', function() {
        const sopLink = document.getElementById('sopLinkInput').value.trim();
        
        if (!sopLink) {
            alert('Please enter a valid URL');
            return;
        }
        
        // Basic URL validation
        try {
            new URL(sopLink);
        } catch (e) {
            alert('Please enter a valid URL (e.g., https://example.com)');
            return;
        }
        
        localStorage.setItem('onSeaTransit_sopLink', sopLink);
        
        const sopButton = document.getElementById('sopButton');
        sopButton.href = sopLink;
        sopButton.onclick = null; // Remove the alert onclick if it was set
        
        const sopModal = bootstrap.Modal.getInstance(document.getElementById('sopModal'));
        sopModal.hide();
        
        alert('SOP link saved successfully!');
    });
    
    // Load SOP link on page load
    loadSopLink();

});

</script>