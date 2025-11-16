@extends('layouts.vertical', ['title' => 'Upcoming Container'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
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
    @include('layouts.shared.page-title', [
        'page_title' => 'Upcoming Container',
        'sub_title' => 'Upcoming Container',
    ])

    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #169e28 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-nowrap">
                        <!-- Search -->
                        <div class="input-group" style="max-width: 225px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" id="container-planning-search" class="form-control border-start-0"
                                placeholder="Search Container No...">
                        </div>

                        <!-- Container Filter -->
                        <select id="filter-container" class="form-select" style="width: 180px;">
                            <option value="">Filter by Container</option>
                            @foreach ($containers as $container)
                                <option value="{{ $container->tab_name }}">{{ $container->tab_name }}</option>
                            @endforeach
                        </select>

                        <!-- Supplier Filter -->
                        <select id="filter-payment-term" class="form-select" style="width: 180px;">
                            <option value="">Filter pay term</option>
                            <option value="balance_against">Balance against B/L</option>
                            <option value="balance_before_shipping">BALANCE BEFORE SHIPPING</option>
                        </select>

                        <!-- Balance Info -->
                        <div class="d-flex align-items-center gap-4">

                            <!-- Total Balance -->
                            <span class="fw-bold">
                                Total Balance: 
                                <span id="total-balance" class="text-primary">0.00</span>
                            </span>

                            <!-- Payment Term Balance -->
                            <span id="payment-term-balance-container" class="fw-bold d-none">
                                Payment Term Balance: 
                                <span id="payment-term-balance" class="text-success">0.00</span>
                            </span>

                        </div>


                        <!-- Buttons -->
                        <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                            <i class="fas fa-trash-alt me-1"></i> Delete Selected
                        </button>
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal"
                            data-bs-target="#createUpComingContainer">
                            <i class="fas fa-plus-circle me-1"></i>Upcoming Cont.
                        </button>
                    </div>

                    <div id="container-planning"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createUpComingContainer" tabindex="-1" aria-labelledby="createUpComingContainerLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold text-black" id="createUpComingContainerLabel">
                        <i class="fas fa-file-invoice me-2"></i> Create Upcoming Container
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <form id="containerPlanningForm" method="POST" action="{{ route('upcoming.container.save') }}" enctype="multipart/form-data"
                    autocomplete="off">
                    @csrf
                    <input type="hidden" name="id" id="record_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Container Number -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Container Number</label>
                                <select name="container_number" class="form-select" required>
                                    <option value="">Select Container</option>
                                    @foreach ($containers as $container)
                                        <option value="{{ $container->tab_name }}">{{ $container->tab_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Supplier Name -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Supplier Name</label>
                                <select name="supplier_id" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Area</label>
                                <select name="area" id="area" class="form-select" required>
                                    <option value="">Select area</option>
                                    <option value="GUZ">GUZ</option>
                                    <option value="NIN">NIN</option>
                                    <option value="TJ">TJ</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Order Link</label>
                                <input type="url" name="order_link" class="form-control" placeholder="order link">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Invoice Value</label>
                                <input type="number" step="0.01" name="invoice_value" id="invoice_value" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Paid</label>
                                <input type="number" step="0.01" name="paid" id="paid" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Balance</label>
                                <input type="number" step="0.01" name="balance" id="balance" class="form-control" readonly>
                            </div>

                            <!-- Pay Term -->
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Payment Term</label>
                                <select name="payment_terms" class="form-select">
                                    <option value="">Select Term</option>
                                    <option value="balance_against">Balance against B/L</option>
                                    <option value="balance_before_shipping">BALANCE BEFORE SHIPPING</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Save</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const table = new Tabulator("#container-planning", {
                ajaxURL: "/upcoming-container/data",
                ajaxConfig: "GET",
                layout: "fitColumns",
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "500px",
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Sr. No",
                        formatter: "rownum",
                        hozAlign: "center",
                        width: 90,
                        visible: false
                    },
                    {
                        title: "Container No",
                        field: "container_number"
                    },
                    {
                        title: "Supplier",
                        field: "supplier_name"
                    },
                    {
                        title: "Area",
                        field: "area"
                    },
                    {
                        title: "Order Link",
                        field: "order_link",
                        hozAlign: "center",
                        formatter: function(cell){
                            let url = cell.getValue();
                            if (url) {
                                return `<a href="${url}" class="btn btn-sm btn-outline-primary" target="_blank" title="Open Packing List">
                                            <i class="fa fa-link"></i> Open
                                        </a>`;
                            } else {
                                return "";
                            }
                        }
                    },
                    {
                        title: "Invoice",
                        field: "invoice_value",
                        formatter: function(cell){
                            let value = cell.getValue() ?? 0; 
                            return `<span>${parseFloat(value).toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "Paid",
                        field: "paid",
                        formatter: function(cell){
                            let value = cell.getValue() ?? 0; 
                            return `<span>${parseFloat(value).toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "Balance",
                        field: "balance",
                        formatter: function(cell){
                            let value = cell.getValue() ?? 0; 
                            return `<span>${parseFloat(value).toFixed(0)}</span>`;
                        }
                    },
                    {
                        title: "Pay Terms",
                        field: "payment_terms"
                    },
                    {
                        title: "Action",
                        hozAlign: "center",
                        formatter: function(cell){
                            return `
                                <button class="btn btn-sm btn-primary edit-btn">
                                    <i class="fa fa-edit me-1"></i>Edit
                                </button>
                            `;
                        },
                        cellClick: function(e, cell){
                            let rowData = cell.getRow().getData();
                            openEditModal(rowData);
                        }
                    }

                ],
                ajaxResponse: function(url, params, response) {
                    updateBalances();
                    return response;
                }
            });

            function getVisibleRowsData() {
                return table.getRows(true).map(r => r.getData());
            }

            function updateBalances() {
                const visibleRows = getVisibleRowsData();
                const paymentTerm = document.getElementById("filter-payment-term").value;

                let totalBalance = 0;
                let termBalance = 0;

                visibleRows.forEach(row => {
                    let bal = parseFloat(row.balance || 0);
                    totalBalance += bal;

                    if (paymentTerm && row.payment_terms === paymentTerm) {
                        termBalance += bal;
                    }
                });

                // Total balance show
                document.getElementById("total-balance").innerText = totalBalance.toFixed(0);

                // Payment term balance show/hide
                const termBox = document.getElementById("payment-term-balance-container");

                if (paymentTerm) {
                    termBox.classList.remove("d-none");
                    document.getElementById("payment-term-balance").innerText = termBalance.toFixed(0);
                } else {
                    termBox.classList.add("d-none");
                }
            }

            function applyFilters() {
                const container = document.getElementById("filter-container").value;
                const paymentTerm = document.getElementById("filter-payment-term").value;
                const keyword = document.getElementById("container-planning-search").value.toLowerCase();

                let filters = [];

                // Container filter
                if (container) {
                    filters.push({ field: "container_number", type: "=", value: container });
                }

                // Payment term filter
                if (paymentTerm) {
                    filters.push({ field: "payment_terms", type: "=", value: paymentTerm });
                }

                // Search filter
                if (keyword) {
                    filters.push([
                        { field: "container_number", type: "like", value: keyword }
                    ]);
                }

                table.setFilter(filters);
                updateBalances();
            }

            // --------------- EVENT LISTENERS ------------------
            document.getElementById("filter-container").addEventListener("change", applyFilters);
            document.getElementById("filter-payment-term").addEventListener("change", applyFilters);
            document.getElementById("container-planning-search").addEventListener("input", applyFilters);

            // Run on table load
            table.on("dataLoaded", updateBalances);
            table.on("dataFiltered", updateBalances);



            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            $('#delete-selected-btn').on('click', function() {
                const selectedData = table.getSelectedData();

                if (selectedData.length === 0) {
                    alert('Please select at least one record to delete.');
                    return;
                }

                if (!confirm(`Are you sure you want to delete ${selectedData.length} selected records?`)) {
                    return;
                }

                const ids = selectedData.map(row => row.id);

                $.ajax({
                    url: '/upcoming-container/delete',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ids: ids
                    },
                    success: function(response) {
                        table.deleteRow(ids);
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            function openEditModal(rowData) {
                // Hidden ID set
                document.getElementById("record_id").value = rowData.id;

                // Form ke saare fields set karna
                document.querySelector('select[name="container_number"]').value = rowData.container_number;
                document.querySelector('select[name="supplier_id"]').value = rowData.supplier_id;
                document.querySelector('select[name="area"]').value = rowData.area;
                document.querySelector('input[name="order_link"]').value = rowData.order_link || '';
                document.querySelector('input[name="invoice_value"]').value = rowData.invoice_value || '';
                document.querySelector('input[name="paid"]').value = rowData.paid || '';
                document.querySelector('input[name="balance"]').value = rowData.balance || '';
                document.querySelector('select[name="payment_terms"]').value = rowData.payment_terms || '';

                // Modal ka title change
                document.getElementById("createUpComingContainerLabel").innerText = "Edit Upcoming Container";

                // Modal open karo
                let modal = new bootstrap.Modal(document.getElementById("createUpComingContainer"));
                modal.show();
            }
            

        });
    </script>
    <script>
        async function updateBalance() {
            let invoice = parseFloat(document.getElementById('invoice_value').value) || 0;
            let paid = parseFloat(document.getElementById('paid').value) || 0;

            if (!invoice && !paid) {
                document.getElementById('balance').value = 0;
                return;
            }

            let balance = invoice - paid;
            document.getElementById('balance').value = balance.toFixed(2);
        }

        document.getElementById('invoice_value').addEventListener('blur', updateBalance);
        document.getElementById('paid').addEventListener('blur', updateBalance);
    </script>


@endsection
