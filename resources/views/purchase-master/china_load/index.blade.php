@extends('layouts.vertical', ['title' => 'China Load', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
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
        padding: 16px 10px;
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

        
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'China Load', 'sub_title' => 'China Load'])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">China Load</h4>
                    <button id="add-new-row" class="btn btn-success">
                        <i class="fas fa-plus-circle me-1"></i> Add New
                    </button>
                </div>

                <div id="china-load-table"></div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
    document.documentElement.setAttribute("data-sidenav-size", "condensed");
        const tableData = @json($chinaLoads);

        const table = new Tabulator("#china-load-table", {
            data: tableData,
            layout: "fitDataFill",
            placeholder: "No records available",
            pagination: "local",
            paginationSize: 10,
            height: "700px",
            rowFormatter: function (row) {
                const data = row.getData();
                if (data.status === "Load Done") {
                    row.getElement().style.backgroundColor = "#e2f0cb";
                    row.getElement().style.opacity = "0.7";
                }
            },
            columns: [
                { title: "Con. SL No.", field: "container_sl_no", width: 150 },
                {
                    title: "Load",
                    field: "load",
                    headerSort: false,
                    formatter: function (cell) {
                        const value = cell.getValue();
                        let style = '';
                        if (value === 'Pending') {
                            style = 'background-color: #ffff00; color: black;';
                        } else if (value === 'Done') {
                            style = 'background-color: #00ff00; color: black;';
                        }
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="load"
                                style="min-width: 90px; ${style}">
                                <option value="Pending" ${value === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Done" ${value === 'Done' ? 'selected' : ''}>Done</option>
                            </select>
                        `;
                    },
                },
                { title: "LIST OF GOODS", field: "list_of_goods" },
                { 
                    title: "Shut Out",
                    field: "shut_out",
                    headerSort: false,
                    formatter: function (cell) {
                        const value = cell.getValue();
                        let style = '';
                        if (value === 'Leftover') {
                            style = 'background-color: red; color: black;';
                        } else if (value === 'All In') {
                            style = 'background-color: #00ff00; color: black;';
                        }
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="shut_out"
                                style="min-width: 90px; ${style}">
                                <option value="">Select</option>
                                <option value="Leftover" ${value === 'Leftover' ? 'selected' : ''}>Leftover</option>
                                <option value="All In" ${value === 'All In' ? 'selected' : ''}>All In</option>
                            </select>
                        `;
                    },
                },
                { 
                    title: "OBL", 
                    field: "obl",
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `
                            <input type="text" 
                                class="form-control form-control-sm auto-save" 
                                data-column="obl" 
                                value="${value ?? ''}"
                                maxlength="30"
                                oninput="if(this.value.length >= 30) alert('Maximum 30 digits allowed')"
                                style="width: 160px;">
                        `;
                    }
                },
                { 
                    title: "MBL", 
                    field: "mbl",
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `
                            <input type="text" 
                                class="form-control form-control-sm auto-save" 
                                data-column="mbl" 
                                value="${value ?? ''}"
                                maxlength="30"
                                oninput="if(this.value.length >= 30) alert('Maximum 30 digits allowed')"
                                style="width: 130px;">
                        `;
                    }
                },
                { 
                    title: "Cont. No", 
                    field: "container_no",
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `
                            <input type="text" 
                                class="form-control form-control-sm auto-save" 
                                data-column="container_no" 
                                value="${value ?? ''}"
                                maxlength="15"
                                oninput="if(this.value.length >= 15) alert('Maximum 15 digits allowed')"
                                style="width: 90px;">
                        `;
                    }
                },
                { 
                    title: "Item",
                    field: "item",
                    headerSort: false,
                    formatter: function (cell) {
                        const value = cell.getValue();
                        return `
                            <input type="text" 
                                class="form-control form-control-sm auto-save" 
                                data-column="item" 
                                value="${value ?? ''}"
                                maxlength="20"
                                oninput="if(this.value.length >= 20) alert('Maximum 20 characters allowed')"
                                style="min-width: 90px;">
                        `;
                    },

                },
                { 
                    title: "CHA China",
                    field: "cha_china",
                    headerSort: false,
                    formatter: function (cell) {
                        const value = cell.getValue();
                        const forwarders = @json($forwarders);
                        let options = '<option value="">Select</option>';
                        forwarders.forEach(function(forwarder) {
                            const selected = value === forwarder.name ? 'selected' : '';
                            options += `<option value="${forwarder.name}" ${selected}>${forwarder.name}</option>`;
                        });
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="cha_china"
                                style="min-width: 90px; background-color: #00ff00; color: black;">
                                ${options}
                            </select>
                        `;
                    },
                },
                { 
                    title: "Consignee",
                     field: "consignee",
                     headerSort: false,
                    formatter: function (cell) {
                        const value = cell.getValue();
                        return `
                            <select class="form-select form-select-sm auto-save"
                                data-column="consignee"
                                style="min-width: 90px; background-color: #00ff00; color: black;">
                                <option value="5C" ${value === '5C' ? 'selected' : ''}>5C</option>
                                <option value="KCube" ${value === 'KCube' ? 'selected' : ''}>KCube</option>
                            </select>
                        `;
                    },
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
                                style="min-width: 90px; background-color: #00ff00; color: black;">
                                <option value="">Select</option>
                                <option value="Load Done" ${value === 'Load Done' ? 'selected' : ''}>Load Done</option>
                            </select>
                        `;
                    },
                }
            ],
        });

        // table.setFilter(function(data) {
        //     return data.status !== 'Load Done';
        // });

        window.table = table;

        document.getElementById('add-new-row').addEventListener('click', function () {
            const tableData = table.getData();
            let maxSlNo = 66;

            if (tableData.length > 0) {
                const maxExisting = Math.max(...tableData.map(row => parseInt(row.container_sl_no) || 66));
                maxSlNo = maxExisting + 1;
            }

            const newRow = {
                container_sl_no: maxSlNo,
                load: 'Pending'
            };

            table.addRow(newRow, false);

            fetch('/china-load/inline-update-by-sl', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(newRow)
            });
        });

        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('auto-save')) {
                const cell = e.target.closest('.tabulator-cell');
                const rowComponent = table.getRow(cell.parentElement);
                const rowData = rowComponent.getData();

                const column = e.target.dataset.column;
                const value = e.target.value;

                const payload = Object.assign({}, rowData, { column, value });

                fetch('/china-load/inline-update-by-sl', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                });

                if (column === 'load') {
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
                } else if (column === 'shut_out') {
                    if (value === 'Leftover') {
                        e.target.style.backgroundColor = 'red';
                        e.target.style.color = 'black';
                    } else if (value === 'All In') {
                        e.target.style.backgroundColor = '#00ff00';
                        e.target.style.color = 'black';
                    } else {
                        e.target.style.backgroundColor = '';
                        e.target.style.color = '';
                    }
                }
            }
        });
        document.body.style.zoom = "80%";
    });
</script>
@endsection