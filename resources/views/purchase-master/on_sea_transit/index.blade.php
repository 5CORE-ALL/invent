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
    
    .status-container .fa-circle {
        transition: transform 0.2s;
    }
    
    .status-container .fa-circle:hover {
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
    
    /* Remove modal backdrop completely */
    .modal-backdrop {
        display: none !important;
    }
    
    /* Add shadow to modal for depth without backdrop */
    .modal-dialog {
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }
    
    /* Ensure modal content is on top */
    .modal {
        background-color: transparent !important;
    }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'On Sea Transit', 'sub_title' => 'On Sea Transit'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-start align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex flex-wrap gap-3 w-100">
                        <span class="badge bg-warning text-dark" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px;">
                            <i class="fas fa-clipboard-list me-2"></i>Pre-Load: {{ $planningCount }}
                        </span>
                        <span class="badge bg-primary text-white" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px;">
                            <i class="fas fa-ship me-2"></i>On Sea: <span id="onSeaBadge">0</span>
                        </span>
                        <span class="badge text-white" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px; background-color: #654321;">
                            <i class="fas fa-plane-arrival me-2"></i>Landed: <span id="landedBadge">0</span>
                        </span>
                        <span class="badge bg-info text-white" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px;">
                            <i class="fas fa-calculator me-2"></i>transit: {{ $remainingCount }}
                        </span>
                        <span class="badge bg-success text-white" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px;">
                            <i class="fas fa-dollar-sign me-2"></i>$<span id="totalValueBadge">{{ number_format($totalInvoiceValue, 0) }}</span>
                        </span>
                        <span class="badge bg-danger text-white" style="font-size: 1.1rem; padding: 0.75rem 1.25rem; flex: 1; min-width: 150px;">
                            <i class="fas fa-exclamation-circle me-2"></i>Pending: $<span id="totalPendingBadge">{{ number_format($totalPendingAmount ?? 0, 0) }}</span>
                        </span>
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

<!-- Details History Modal -->
<div class="modal fade" id="detailsHistoryModal" tabindex="-1" aria-labelledby="detailsHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-history me-2"></i>Details History</h5>
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

<!-- Remarks Edit Modal -->
<div class="modal fade" id="remarksModal" tabindex="-1" aria-labelledby="remarksModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>Remarks</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <textarea class="form-control" id="remarksTextarea" rows="5" placeholder="Enter remarks..."></textarea>
        <input type="hidden" id="remarksContainer">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveRemarksBtn">Save</button>
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
        
        console.log('Total Invoice Value:', totalInvoiceValue);
        console.log('Total Pending Amount:', totalPendingAmount);
        
        const planningBadge = document.querySelector('.badge.bg-warning');
        if (planningBadge) {
            planningBadge.innerHTML = `<i class="fas fa-clipboard-list me-1"></i>Pre-Load: ${planningCount}`;
        }
        
        const onSeaBadge = document.getElementById('onSeaBadge');
        if (onSeaBadge) {
            onSeaBadge.textContent = onSeaCount;
        }
        
        const landedBadge = document.getElementById('landedBadge');
        if (landedBadge) {
            landedBadge.textContent = landedCount;
        }
        
        const remainingBadge = document.querySelector('.badge.bg-info');
        if (remainingBadge) {
            remainingBadge.innerHTML = `<i class="fas fa-calculator me-1"></i>transit: ${remainingCount}`;
        }
        
        const totalValueBadge = document.getElementById('totalValueBadge');
        if (totalValueBadge) {
            totalValueBadge.textContent = Math.round(totalInvoiceValue).toLocaleString();
        }
        
        const totalPendingBadge = document.getElementById('totalPendingBadge');
        if (totalPendingBadge) {
            totalPendingBadge.textContent = Math.round(totalPendingAmount).toLocaleString();
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
        height: "550px",
        rowFormatter: function (row) {
            const data = row.getData();
            if (data.status === "On Sea") {
                row.getElement().style.backgroundColor = "#e2f0cb";
                row.getElement().style.opacity = "0.7";
            }
        },
        columns: [
            {
                title: "Cont",
                field: "container_sl_no",
                formatter: function(cell) {
                    const slNo = cell.getValue();
                    return `
                        <span class="badge bg-primary text-white" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">${slNo}</span>
                        <i class="fas fa-info-circle ms-1 text-primary open-modal-btn" data-sl="${slNo}" style="cursor: pointer;"></i>
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
                title: "BL check",
                field: "bl_check",
                headerSort: false,
                minWidth: 80,
                formatter: function (cell) {
                    const value = cell.getValue();
                    const rowData = cell.getRow().getData();
                    
                    if (value === 'Verified') {
                        return `
                            <div class="status-container">
                                <i class="fas fa-check-circle" style="font-size: 16px; cursor: pointer; color: #28a745;"></i>
                                <select class="form-select form-select-sm auto-save status-select"
                                    data-column="bl_check"
                                    style="display: none; width: 90px;">
                                    <option value="">Select</option>
                                    <option value="Issued">Issued</option>
                                    <option value="Verified" selected>Verified</option>
                                </select>
                            </div>
                        `;
                    } else if (value === 'Issued') {
                        return `
                            <div class="status-container">
                                <i class="fas fa-circle" style="font-size: 14px; cursor: pointer; color: #ffc107;"></i>
                                <select class="form-select form-select-sm auto-save status-select"
                                    data-column="bl_check"
                                    style="display: none; width: 90px;">
                                    <option value="">Select</option>
                                    <option value="Issued" selected>Issued</option>
                                    <option value="Verified">Verified</option>
                                </select>
                            </div>
                        `;
                    } else {
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="bl_check"
                                style="width: 90px;">
                                <option value="">Select</option>
                                <option value="Issued">Issued</option>
                                <option value="Verified">Verified</option>
                            </select>
                        `;
                    }
                },
            },
            {
                title: "BL",
                field: "bl_link",
                minWidth: 80,
                headerSort: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value
                        ? `<a href="${value}" target="_blank" class="text-primary"><i class="fas fa-link"></i></a>`
                        : '';
                },
                editor: "input",
                cellEdited: function(cell) {
                    const newValue = cell.getValue();
                    const rowData = cell.getRow().getData();

                    fetch('/on-sea-transit/inline-update-or-create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            container_sl_no: rowData.container_sl_no,
                            column: 'bl_link',
                            value: newValue
                        })
                    }).then(response => {
                        if (!response.ok) {
                            alert('Failed to save BL link');
                        }
                    });
                }
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
                title: "ETD",
                field: "etd",
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) {
                        return `
                            <input type="date" 
                                class="form-control form-control-sm auto-save" 
                                data-column="etd" 
                                value=""
                                style="width: 100%;"
                                onfocus="this.showPicker()">
                        `;
                    }
                    
                    // Format date as "1 Apr"
                    const date = new Date(value);
                    const day = date.getDate();
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const month = monthNames[date.getMonth()];
                    const formattedDate = `${day} ${month}`;
                    
                    return `
                        <div class="date-display" style="cursor: pointer; padding: 6px; text-align: center; font-weight: 600;">
                            ${formattedDate}
                            <input type="date" 
                                class="form-control form-control-sm auto-save date-input" 
                                data-column="etd" 
                                value="${value}"
                                style="display: none; width: 100%;"
                                onfocus="this.showPicker()">
                        </div>
                    `;
                }
            },
            {
                title: "ETA<br>Port",
                field: "eta_port",
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value || value === '' || value === 'dd/mm/yyyy') {
                        return `
                            <input type="date" 
                                class="form-control form-control-sm auto-save" 
                                data-column="eta_port" 
                                value=""
                                style="width: 100%;"
                                onfocus="this.showPicker()">
                        `;
                    }
                    
                    // Format date as "1 Apr"
                    const date = new Date(value);
                    const day = date.getDate();
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const month = monthNames[date.getMonth()];
                    const formattedDate = `${day} ${month}`;
                    
                    return `
                        <div class="date-display" style="cursor: pointer; padding: 6px; text-align: center; font-weight: 600;">
                            ${formattedDate}
                            <input type="date" 
                                class="form-control form-control-sm auto-save date-input" 
                                data-column="eta_port" 
                                value="${value}"
                                style="display: none; width: 100%;"
                                onfocus="this.showPicker()">
                        </div>
                    `;
                }
            },
            { 
                title: "Port Arrival", 
                field: "port_arrival",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    
                    if (value) {
                        return `
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
                    } else {
                        return `
                            <select class="form-select form-select-sm auto-save" 
                                data-column="port_arrival" 
                                style="width: 120px;">
                                <option value="">Select</option>
                                <option value="NYC">NYC</option>
                                <option value="LA">LA</option>
                                <option value="PRINCE RUPERT">PRINCE RUPERT</option>
                                <option value="NORFOLK">NORFOLK</option>
                            </select>
                        `;
                    }
                }
            },
            { 
                title: "ETA<br>OHIO", 
                field: "eta_date_ohio",
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (!value) {
                        return `
                            <input type="date" 
                                class="form-control form-control-sm auto-save" 
                                data-column="eta_date_ohio" 
                                value=""
                                style="width: 100%;"
                                onfocus="this.showPicker()">
                        `;
                    }
                    
                    // Format date as "1 Apr"
                    const date = new Date(value);
                    const day = date.getDate();
                    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    const month = monthNames[date.getMonth()];
                    const formattedDate = `${day} ${month}`;
                    
                    return `
                        <div class="date-display" style="cursor: pointer; padding: 6px; text-align: center; font-weight: 600;">
                            ${formattedDate}
                            <input type="date" 
                                class="form-control form-control-sm auto-save date-input" 
                                data-column="eta_date_ohio" 
                                value="${value}"
                                style="display: none; width: 100%;"
                                onfocus="this.showPicker()">
                        </div>
                    `;
                }
            },
            // { title: "ISF <br>(usa agent)", field: "isf_usa_agent", formatter: function(cell) {
            //     const value = cell.getValue();
            //     let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
            //     return `<select class="form-select form-select-sm auto-save" data-column="isf_usa_agent" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            // } },
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
                title: "Arrival<br>Notice", 
                field: "arrival_notice_email",
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
                title: "CHA Work", 
                field: "invoice_send_to_dominic",
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
                title: "Remarks", 
                field: "remarks", 
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue() || '';
                    const rowData = cell.getRow().getData();
                    const containerSlNo = rowData.container_sl_no;
                    
                    // Show icon with different color if has content
                    const iconClass = value ? 'btn-info' : 'btn-secondary';
                    const icon = value ? 'fa-file-alt' : 'fa-file';
                    
                    return `
                        <button class="btn btn-sm ${iconClass} view-remarks-btn" 
                            data-container="${containerSlNo}"
                            data-value="${value.replace(/"/g, '&quot;')}"
                            title="${value ? 'View/Edit Remarks' : 'Add Remarks'}"
                            style="padding: 4px 10px;">
                            <i class="fas ${icon}"></i>
                        </button>
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
                    const roundedValue = value ? Math.round(value) : '';
                    const hasBadge = roundedValue > 0;
                    
                    if (hasBadge) {
                        return `
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-success text-white" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">$${Math.round(roundedValue).toLocaleString()}</span>
                                <input type="number" 
                                    class="form-control form-control-sm auto-save" 
                                    data-column="invoice_value" 
                                    value="${roundedValue}" 
                                    placeholder="0"
                                    step="1"
                                    style="width: 70px; font-size: 0.85rem;">
                            </div>`;
                    }
                    return `<input type="number" 
                        class="form-control form-control-sm auto-save" 
                        data-column="invoice_value" 
                        value="${roundedValue}" 
                        placeholder="0"
                        step="1"
                        style="width: 100%;">`;
                }
            },
            { 
                title: "Paid ($)", 
                field: "paid", 
                headerSort: false,
                minWidth: 120,
                formatter: function(cell) {
                    const value = cell.getValue();
                    const roundedValue = value ? Math.round(parseFloat(value)) : 0;
                    const hasBadge = roundedValue > 0;
                    
                    if (hasBadge) {
                        return `
                            <div class="d-flex align-items-center gap-1">
                                <span class="badge bg-info text-white" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">$${Math.round(roundedValue).toLocaleString()}</span>
                                <input type="number" 
                                    class="form-control form-control-sm auto-save" 
                                    data-column="paid" 
                                    value="${roundedValue}" 
                                    placeholder="0"
                                    step="1"
                                    style="width: 70px; font-size: 0.85rem;">
                            </div>`;
                    }
                    return `<input type="number" 
                        class="form-control form-control-sm auto-save" 
                        data-column="paid" 
                        value="${roundedValue}" 
                        placeholder="0"
                        step="1"
                        style="width: 100%;">`;
                }
            },
            { 
                title: "Pending", 
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
                title: "Details", 
                field: "details", 
                headerSort: false,
                minWidth: 80,
                formatter: function(cell) {
                    const value = cell.getValue() || '';
                    const rowData = cell.getRow().getData();
                    const rowId = rowData.id;
                    const containerSlNo = rowData.container_sl_no;
                    
                    // Show icon with different color if has content
                    const iconClass = value ? 'btn-primary' : 'btn-secondary';
                    const icon = value ? 'fa-edit' : 'fa-plus';
                    
                    return `
                        <div style="display: flex; align-items: center; gap: 5px; justify-content: center;">
                            <button class="btn btn-sm ${iconClass} view-details-btn" 
                                data-id="${rowId}"
                                data-container="${containerSlNo}"
                                data-value="${value.replace(/"/g, '&quot;')}"
                                title="${value ? 'View/Edit Details' : 'Add Details'}"
                                style="padding: 4px 10px;">
                                <i class="fas ${icon}"></i>
                            </button>
                            <button class="btn btn-sm btn-warning view-history-btn" 
                                data-id="${rowId}"
                                title="View History"
                                style="padding: 4px 10px;">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>
                    `;
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
                        cell.setValue('Planning'); // Update the cell value
                    }
                    
                    {
                        let bgColor = '';
                        let textColor = 'black';
                        
                        if (value === 'Planning') {
                            bgColor = '#ffff00'; // Yellow
                        } else if (value === 'On Sea') {
                            bgColor = '#007bff'; // Blue
                            textColor = 'white';
                        } else if (value === 'Landed') {
                            bgColor = '#654321'; // Dark brown
                            textColor = 'white';
                        } else if (value === 'Arrived') {
                            bgColor = '#00ff00'; // Green
                        } else {
                            bgColor = '#ffff00'; // Default to yellow (Planning)
                        }
                        
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="status"
                                style="min-width: 90px; background-color: ${bgColor}; color: ${textColor}; width: 120px;">
                                <option value="Planning" ${value === 'Planning' ? 'selected' : ''}>Pre-Load</option>
                                <option value="On Sea" ${value === 'On Sea' ? 'selected' : ''}>On Sea</option>
                                <option value="Landed" ${value === 'Landed' ? 'selected' : ''}>Landed</option>
                                <option value="Arrived" ${value === 'Arrived' ? 'selected' : ''}>Arrived</option>
                            </select>
                        `;
                    }
                },
            }
        ],
    });

    // Initialize badge counts on page load
    console.log('Table Data:', tableData);
    updateBadgeCounts();
    
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
    
    // Handle clicking on status dots to show dropdown
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('fa-circle') && e.target.closest('.status-container')) {
            const container = e.target.closest('.status-container');
            const icon = container.querySelector('.fa-circle');
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
                    
                    if (column === 'bl_link') {
                        // Manually convert input to link icon after save
                        const linkHtml = `
                            <a href="${value}" target="_blank" class="text-primary">
                                <i class="fas fa-link"></i>
                            </a>
                        `;
                        const cell = table.getRow(rowElement).getCell(column);
                        cell.setValue(value); // updates internal data
                        cell.getElement().innerHTML = linkHtml; // updates visible cell
                    }

                    // Update BL check cell to show dot after value change
                    if (column === 'bl_check') {
                        const cell = table.getRow(rowElement).getCell(column);
                        if (cell) {
                            let dotHtml = '';
                            if (value === 'Verified') {
                                dotHtml = `
                                    <div class="status-container">
                                        <i class="fas fa-check-circle text-success" style="font-size: 16px; cursor: pointer;"></i>
                                        <select class="form-select form-select-sm auto-save status-select"
                                            data-column="bl_check"
                                            style="display: none; width: 90px;">
                                            <option value="">Select</option>
                                            <option value="Issued">Issued</option>
                                            <option value="Verified" selected>Verified</option>
                                        </select>
                                    </div>
                                `;
                            } else if (value === 'Issued') {
                                dotHtml = `
                                    <div class="status-container">
                                        <i class="fas fa-circle text-warning" style="font-size: 14px; cursor: pointer;"></i>
                                        <select class="form-select form-select-sm auto-save status-select"
                                            data-column="bl_check"
                                            style="display: none; width: 90px;">
                                            <option value="">Select</option>
                                            <option value="Issued" selected>Issued</option>
                                            <option value="Verified">Verified</option>
                                        </select>
                                    </div>
                                `;
                            } else {
                                dotHtml = `
                                    <select class="form-select form-select-sm auto-save"
                                        data-column="bl_check"
                                        style="width: 90px;">
                                        <option value="">Select</option>
                                        <option value="Issued">Issued</option>
                                        <option value="Verified">Verified</option>
                                    </select>
                                `;
                            }
                            cell.getElement().innerHTML = dotHtml;
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
                        {
                            // Set default to Planning if empty
                            if (!value) {
                                value = 'Planning';
                            }
                            
                            // Apply color based on status
                            if (value === 'Planning') {
                                e.target.style.backgroundColor = '#ffff00'; // Yellow
                                e.target.style.color = 'black';
                            } else if (value === 'On Sea') {
                                e.target.style.backgroundColor = '#007bff'; // Blue
                                e.target.style.color = 'white';
                            } else if (value === 'Landed') {
                                e.target.style.backgroundColor = '#654321'; // Dark brown
                                e.target.style.color = 'white';
                            } else if (value === 'Arrived') {
                                e.target.style.backgroundColor = '#00ff00'; // Green
                                e.target.style.color = 'black';
                            }
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
                            
                            const dateHtml = `
                                <div class="date-display" style="cursor: pointer; padding: 6px; text-align: center; font-weight: 600;">
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
    
    // Handle view remarks button click
    document.addEventListener("click", function (e) {
        if (e.target.closest('.view-remarks-btn')) {
            const button = e.target.closest('.view-remarks-btn');
            const containerSlNo = button.getAttribute("data-container");
            const currentValue = button.getAttribute("data-value");
            
            // Set modal values
            document.getElementById('remarksTextarea').value = currentValue;
            document.getElementById('remarksContainer').value = containerSlNo;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("remarksModal"));
            modal.show();
        }
        
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
    
    // Save Remarks
    document.getElementById('saveRemarksBtn').addEventListener('click', function() {
        const containerSlNo = document.getElementById('remarksContainer').value;
        const value = document.getElementById('remarksTextarea').value;
        
        fetch('/on-sea-transit/inline-update-or-create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                container_sl_no: containerSlNo,
                column: 'remarks',
                value: value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the tableData
                const dataIndex = tableData.findIndex(item => item.container_sl_no == containerSlNo);
                if (dataIndex !== -1) {
                    tableData[dataIndex].remarks = value;
                }
                
                // Reload table data
                table.setData(tableData);
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('remarksModal')).hide();
                
                // Show success message
                alert('Remarks saved successfully!');
            } else {
                alert('Failed to save remarks');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to save remarks');
        });
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
                    let historyHtml = '<div class="table-responsive"><table class="table table-striped table-bordered table-hover">';
                    historyHtml += '<thead class="table-dark"><tr><th style="width: 15%;">Date & Time</th><th style="width: 12%;">User</th><th style="width: 36%;">Old Value</th><th style="width: 36%;">New Value</th></tr></thead>';
                    historyHtml += '<tbody>';
                    
                    data.history.forEach(item => {
                        const date = new Date(item.changed_at);
                        const formattedDate = date.toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                        
                        historyHtml += `
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.9rem;">${formattedDate}</td>
                                <td style="font-weight: 600; font-size: 0.95rem;">${item.user_name || 'Unknown'}</td>
                                <td style="word-break: break-word; white-space: pre-wrap; font-size: 0.9rem; padding: 12px;">${item.old_value || '<span class="text-muted fst-italic">Empty</span>'}</td>
                                <td style="word-break: break-word; white-space: pre-wrap; font-size: 0.9rem; padding: 12px; background-color: #f0f8ff;">${item.new_value || '<span class="text-muted fst-italic">Empty</span>'}</td>
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

});

</script>