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
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'On Sea Transit', 'sub_title' => 'On Sea Transit'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">
                        On Sea Transit 
                        <span class="badge bg-warning text-dark ms-2" style="font-size: 0.9rem;">
                            <i class="fas fa-clipboard-list me-1"></i>Container Planning: {{ $planningCount }}
                        </span>
                        <span class="badge bg-info text-white ms-2" style="font-size: 0.9rem;">
                            <i class="fas fa-calculator me-1"></i>Remaining: {{ $remainingCount }}
                        </span>
                        <span class="badge bg-success text-white ms-2" style="font-size: 0.9rem;">
                            <i class="fas fa-dollar-sign me-1"></i>Total Value: $<span id="totalValueBadge">{{ number_format($totalInvoiceValue, 2) }}</span>
                        </span>
                        <span class="badge bg-danger text-white ms-2" style="font-size: 0.9rem;">
                            <i class="fas fa-exclamation-circle me-1"></i>Pending: $<span id="totalPendingBadge">{{ number_format($totalPendingAmount ?? 0, 2) }}</span>
                        </span>
                    </h4>
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
        const totalCount = tableData.length;
        const planningCount = tableData.filter(item => item.status === 'Planning').length;
        const arrivedCount = tableData.filter(item => item.status === 'Arrived').length;
        const remainingCount = totalCount - (arrivedCount + planningCount);
        
        // Calculate total invoice value for containers excluding Arrived and Planning
        const totalInvoiceValue = tableData
            .filter(item => item.status !== 'Arrived' && item.status !== 'Planning')
            .reduce((sum, item) => {
                const value = parseFloat(item.invoice_value) || 0;
                console.log(`Container ${item.container_sl_no}: status=${item.status}, invoice_value=${item.invoice_value}, parsed=${value}`);
                return sum + value;
            }, 0);
        
        // Calculate total pending amount (balance) for containers excluding Arrived and Planning
        const totalPendingAmount = tableData
            .filter(item => item.status !== 'Arrived' && item.status !== 'Planning')
            .reduce((sum, item) => sum + (parseFloat(item.balance) || 0), 0);
        
        console.log('Total Invoice Value:', totalInvoiceValue);
        console.log('Total Pending Amount:', totalPendingAmount);
        
        const planningBadge = document.querySelector('.badge.bg-warning');
        if (planningBadge) {
            planningBadge.innerHTML = `<i class="fas fa-clipboard-list me-1"></i>Container Planning: ${planningCount}`;
        }
        
        const remainingBadge = document.querySelector('.badge.bg-info');
        if (remainingBadge) {
            remainingBadge.innerHTML = `<i class="fas fa-calculator me-1"></i>Remaining: ${remainingCount}`;
        }
        
        const totalValueBadge = document.getElementById('totalValueBadge');
        if (totalValueBadge) {
            totalValueBadge.textContent = totalInvoiceValue.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        const totalPendingBadge = document.getElementById('totalPendingBadge');
        if (totalPendingBadge) {
            totalPendingBadge.textContent = totalPendingAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    }
    
    const table = new Tabulator("#on-sea-transit-table", {
        data: tableData,
        layout: "fitDataFill",
        placeholder: "No records available",
        pagination: "local",
        paginationSize: 10,
        movableColumns: true,
        resizableColumns: true,
        height: "550px",
        rowFormatter: function (row) {
            const data = row.getData();
            if (data.status === "On Sea Done") {
                row.getElement().style.backgroundColor = "#e2f0cb";
                row.getElement().style.opacity = "0.7";
            }
        },
        columns: [
            {
                title: "Cont. Sl No.",
                field: "container_sl_no",
                formatter: function(cell) {
                    const slNo = cell.getValue();
                    return `
                        ${slNo} <i class="fas fa-info-circle ms-1 text-primary open-modal-btn" data-sl="${slNo}"></i>
                    `;
                },
                headerSort: false,
                width: 120
            },
            {
                title: "MBL",
                field: "mbl",
                headerSort: false,
                width: 150,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? `<span class="text-dark fw-bold">${value}</span>` : '<span class="text-muted">-</span>';
                }
            },
            {
                title: "OBL",
                field: "obl",
                headerSort: false,
                width: 150,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? `<span class="text-dark fw-bold">${value}</span>` : '<span class="text-muted">-</span>';
                }
            },
            {
                title: "Container No",
                field: "container_no",
                headerSort: false,
                width: 150,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? `<span class="text-dark fw-bold">${value}</span>` : '<span class="text-muted">-</span>';
                }
            },
            {
                title: "Item",
                field: "item",
                headerSort: false,
                width: 200,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value ? `<span class="text-dark fw-bold">${value}</span>` : '<span class="text-muted">-</span>';
                }
            },
            {
                title: "BL check",
                field: "bl_check",
                headerSort: false,
                formatter: function (cell) {
                    const value = cell.getValue();
                    let style = '';
                    if (value === 'Issued') {
                        style = 'background-color: #ffff00; color: black;';
                    } else if (value === 'Verified') {
                        style = 'background-color: #00ff00; color: black;';
                    }
                    return `
                        <select class="form-select form-select-sm auto-save"
                            data-column="bl_check"
                            style="width: 90px; ${style}">
                            <option value="">Select</option>
                            <option value="Issued" ${value === 'Issued' ? 'selected' : ''}>Issued</option>
                            <option value="Verified" ${value === 'Verified' ? 'selected' : ''}>Verified</option>
                        </select>
                    `;
                },
            },
            {
                title: "BL link",
                field: "bl_link",
                width: 150,
                headerSort: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value
                        ? `<a href="${value}" target="_blank" class="text-primary"><i class="fas fa-link"></i> View</a>`
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
                formatter: function (cell) {
                    const value = cell.getValue();
                    let style = '';
                    if (value === 'China Done') {
                        style = 'background-color: #ffff00; color: black;';
                    } else if (value === 'USA Done') {
                        style = 'background-color: #00ff00; color: black;';
                    }
                    return `
                        <select class="form-select form-select-sm auto-save"
                            data-column="isf"
                            style="width: 90px; ${style}">
                            <option value="">Select</option>
                            <option value="China Done" ${value === 'China Done' ? 'selected' : ''}>China Done</option>
                            <option value="USA Done" ${value === 'USA Done' ? 'selected' : ''}>USA Done</option>
                        </select>
                    `;
                },
            },
            {
                title: "ETD",
                field: "etd",
                headerSort: false,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return `
                        <input type="date" 
                            class="form-control form-control-sm auto-save" 
                            data-column="etd" 
                            value="${value ?? ''}"
                            style="width: 88px;"
                            onfocus="this.showPicker()"
                            placeholder="YYYY">
                    `;
                }
            },
            { title: "Port Arrival", field: "port_arrival", formatter: function(cell) {
                const value = cell.getValue();  
                let style = value ? 'background-color: #00ff00; color: black; width: 90px;' : 'width: 90px;';
                return `<select class="form-select form-select-sm auto-save" data-column="port_arrival" style="${style}">
                <option value="">Select</option>
                <option value="NYC" ${value==='NYC'?'selected':''}>NYC</option>
                <option value="LA" ${value==='LA'?'selected':''}>LA</option>
                <option value="PRINCE RUPERT" ${value==='PRINCE RUPERT'?'selected':''}>PRINCE RUPERT</option>
                <option value="NORFOLK" ${value==='NORFOLK'?'selected':''}>NORFOLK</option></select>`;
            } },
            { title: "ETA Date<br>Ohio", field: "eta_date_ohio", formatter: function(cell) {
                const value = cell.getValue();
                return `<input type="date" class="form-control form-control-sm auto-save" data-column="eta_date_ohio" value="${value ?? ''}" style="width: 88px;">`;
            } },
            // { title: "ISF <br>(usa agent)", field: "isf_usa_agent", formatter: function(cell) {
            //     const value = cell.getValue();
            //     let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
            //     return `<select class="form-select form-select-sm auto-save" data-column="isf_usa_agent" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            // } },
            { title: "DUTY <br>calcu.", field: "duty_calcu", formatter: function(cell) {
                const value = cell.getValue();
                let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
                return `<select class="form-select form-select-sm auto-save" data-column="duty_calcu" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: `INVOICE send <br> to dominic`, field: "invoice_send_to_dominic", formatter: function(cell) {
                const value = cell.getValue();
                let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
                return `<select class="form-select form-select-sm auto-save" data-column="invoice_send_to_dominic" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "arrival notice <br> email", field: "arrival_notice_email", formatter: function(cell) {
                const value = cell.getValue();
                let style = value === 'Pending' ? 'background-color: #ffff00; color: black;width: 90px;' : value === 'Done' ? 'background-color: #00ff00; color: black;width: 90px;' : 'width: 90px;';
                return `<select class="form-select form-select-sm auto-save" data-column="arrival_notice_email" style="${style}"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { 
                title: "Remarks", 
                field: "remarks", 
                formatter: function(cell) {
                    const value = cell.getValue();
                    if (value) {
                        return `
                            <button class="btn btn-sm btn-info" onclick="alert('${value.replace(/'/g, "\\'")}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            <input type="text" class="form-control form-control-sm auto-save" 
                                data-column="remarks" value="${value}" placeholder="Enter remarks" style="display: none;width: 90px;">
                        `;
                    } else {
                        return `<input type="text" class="form-control form-control-sm auto-save" 
                            data-column="remarks" value="" placeholder="Enter remarks" style="width: 90px;">`;
                    }
                }
            },
            { 
                title: "Invoice<br>Value ($)", 
                field: "invoice_value", 
                headerSort: false,
                width: 120,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return `<input type="number" 
                        class="form-control form-control-sm auto-save" 
                        data-column="invoice_value" 
                        value="${value ?? ''}" 
                        placeholder="0.00"
                        step="0.01"
                        style="width: 100px;">`;
                }
            },
            { 
                title: "Paid ($)", 
                field: "paid", 
                headerSort: false,
                width: 120,
                formatter: function(cell) {
                    const value = cell.getValue();
                    return `<input type="number" 
                        class="form-control form-control-sm auto-save" 
                        data-column="paid" 
                        value="${value ?? ''}" 
                        placeholder="0.00"
                        step="0.01"
                        style="width: 100px;">`;
                }
            },
            { 
                title: "Pending<br>Payments ($)", 
                field: "balance", 
                headerSort: false,
                width: 130,
                formatter: function(cell) {
                    const value = cell.getValue();
                    const displayValue = value ?? 0;
                    const colorClass = displayValue > 0 ? 'text-danger' : 'text-success';
                    return `<span class="fw-bold ${colorClass}">$${parseFloat(displayValue).toFixed(2)}</span>`;
                }
            },
            { 
                title: "Status",
                field: "status",
                headerSort: false,
                formatter: function (cell) {
                    const value = cell.getValue();
                    return `
                        <select class="form-select form-select-sm auto-save"
                            data-column="status"
                            style="min-width: 90px; background-color: #00ff00; color: black;width: 90px;">
                            <option value="">Select</option>
                            <option value="Arrived" ${value === 'Arrived' ? 'selected' : ''}>Arrived</option>
                            <option value="Planning" ${value === 'Planning' ? 'selected' : ''}>Planning</option>
                            <option value="Landed" ${value === 'Landed' ? 'selected' : ''}>Landed</option>
                            <option value="On Sea Done" ${value === 'On Sea Done' ? 'selected' : ''}>On Sea Done</option>
                        </select>
                    `;
                },
            }
        ],
    });

    // Initialize badge counts on page load
    console.log('Table Data:', tableData);
    updateBadgeCounts();

    // table.setFilter(function(data) {
    //     return data.status !== 'On Sea Done';
    // });

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

                    // Color logic for different columns
                    if (['bl_check', 'isf'].includes(column)) {
                        if (value === 'Issued' || value === 'China Done') {
                            e.target.style.backgroundColor = '#ffff00';
                            e.target.style.color = 'black';
                        } else if (value === 'Verified' || value === 'USA Done') {
                            e.target.style.backgroundColor = '#00ff00';
                            e.target.style.color = 'black';
                        } else {
                            e.target.style.backgroundColor = '';
                            e.target.style.color = '';
                        }
                    }

                    if (['port_arrival', 'status'].includes(column)) {
                        if (value) {
                            e.target.style.backgroundColor = '#00ff00';
                            e.target.style.color = 'black';
                        } else {
                            e.target.style.backgroundColor = '';
                            e.target.style.color = '';
                        }
                    }

                    if (['isf_usa_agent', 'duty_calcu', 'invoice_send_to_dominic', 'arrival_notice_email'].includes(column)) {
                        if (value === 'Pending') {
                            e.target.style.backgroundColor = '#ffff00';
                            e.target.style.color = 'black';
                        } else if (value === 'Done') {
                            e.target.style.backgroundColor = '#00ff00';
                            e.target.style.color = 'black';
                        } else {
                            e.target.style.backgroundColor = '';
                            e.target.style.color = '';
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

});

</script>