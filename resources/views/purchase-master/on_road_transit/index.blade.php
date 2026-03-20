@extends('layouts.vertical', ['title' => 'On Road Transit'])
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

    .tabulator-row {
        background-color: #ffffff !important;
        /* default white for all rows */
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
@include('layouts.shared.page-title', ['page_title' => 'On Road Transit',  'sub_title' => 'On Road Transit'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">On Road Transit</h4>
                </div>

                <div id="on-road-transit-table"></div>

            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.setAttribute("data-sidenav-size", "condensed");
    const tableData = @json($onRoadTransitData);
    const table = new Tabulator("#on-road-transit-table", {
        data: tableData,
        layout: "fitDataFill",
        placeholder: "No records available",
        pagination: "local",
        paginationSize: 10,
        movableColumns: true,
        resizableColumns: true,
        rowFormatter: function (row) {
            const data = row.getData();
            if (data.status === "Recevied") {
                row.getElement().style.backgroundColor = "#e2f0cb";
                row.getElement().style.opacity = "0.7";
            }
        },
        columns: [
            { title: "Cont. Sl No.", field: "container_sl_no" },
            { title: "Supplier pay <br> against BL / 7 <br> days before ETA", field: "supplier_pay_against_bl", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="supplier_pay_against_bl" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "CHA china PAY/ 7 <br> days before ETA", field: "cha_china_pay", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="cha_china_pay" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "duty", field: "duty", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="duty" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Freight Due", field: "freight_due", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="freight_due" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "FWDR USA <br> due", field: "fwdr_usa_due", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="fwdr_usa_due" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "CBP Form 7501", field: "cbp_form_7501", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="cbp_form_7501" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Approved" ${value==='Approved'?'selected':''}>Approved</option></select>`;
            } },
            { title: "transport RFQ", field: "transport_rfq", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="transport_rfq" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Approved" ${value==='Approved'?'selected':''}>Approved</option></select>`;
            } },
            { title: "Freight Hold", field: "freight_hold", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="freight_hold" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Customs Hold", field: "customs_hold", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="customs_hold" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "pay usa CHA", field: "pay_usa_cha", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="pay_usa_cha" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Inform SAM", field: "inform_sam", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="inform_sam" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "date of cont. <br> return", field: "date_of_cont_return", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="date_of_cont_return" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Inv Verification", field: "inv_verification", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="inv_verification" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "QC Verification", field: "qc_verification", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="qc_verification" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Claims if Any", field: "claims_if_any", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Pending' ? 'background-color: #ffff00;width: 90px;' : 'background-color: #00ff00;width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="claims_if_any" style="${bgColor} color: black;"><option value="">Select</option><option value="Pending" ${value==='Pending'?'selected':''}>Pending</option><option value="Done" ${value==='Done'?'selected':''}>Done</option></select>`;
            } },
            { title: "Status", field: "status", formatter: function(cell) {
            const value = cell.getValue();
            const bgColor = value === 'Recevied' ? 'background-color: #00ff00;width: 90px;' : 'width: 90px;';
            return `<select class="form-select form-select-sm auto-save" data-column="status" style="${bgColor} color: black;">
                <option value="">Select Status</option>
                <option value="Recevied" ${value==='Recevied'?'selected':''}>Recevied</option>
                </select>`;
            } },
        ],
    });

    // table.setFilter(function(data) {
    //     return data.status !== 'Recevied';
    // });

    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('auto-save')) {
            const column = e.target.dataset.column;
            const value = e.target.value;
            const rowElement = e.target.closest('.tabulator-row');
            const row = table.getRow(rowElement);
            const rowData = row.getData();

            if (['supplier_pay_against_bl', 'cha_china_pay', 'duty', 'freight_due', 'fwdr_usa_due', 'cbp_form_7501', 'transport_rfq', 'freight_hold', 'customs_hold', 'pay_usa_cha', 'inform_sam', 'date_of_cont_return', 'inv_verification', 'qc_verification', 'claims_if_any'].includes(column)) {
                if (value === 'Pending') {
                    e.target.style.backgroundColor = '#ffff00';
                    e.target.style.color = 'black';
                } else if (value === 'Done' || value === 'Approved') {
                    e.target.style.backgroundColor = '#00ff00';
                    e.target.style.color = 'black';
                } else {
                    e.target.style.backgroundColor = '';
                    e.target.style.color = '';
                }
            } else if (column === 'status') {
                if (value === 'Recevied') {
                    e.target.style.backgroundColor = '#00ff00';
                    e.target.style.color = 'black';
                } else {
                    e.target.style.backgroundColor = '';
                    e.target.style.color = '';
                }
            }

            fetch('/on-road-transit/inline-update-or-create', {
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
            });
        }
    });

    document.body.style.zoom = "80%";

});
</script>
@endsection
