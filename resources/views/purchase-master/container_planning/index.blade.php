@extends('layouts.vertical', ['title' => 'Container Planning'])
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
        'page_title' => 'Container Planning',
        'sub_title' => 'Container Planning',
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
                                placeholder="Search Container Number, PO Number...">
                        </div>

                        <!-- Container Filter -->
                        <select id="filter-container" class="form-select" style="width: 180px;">
                            <option value="">Filter by Container</option>
                            @foreach ($containers as $container)
                                <option value="{{ $container->tab_name }}">{{ $container->tab_name }}</option>
                            @endforeach
                        </select>

                        <!-- Supplier Filter -->
                        <select id="filter-supplier" class="form-select" style="width: 180px;">
                            <option value="">Filter by Supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>

                        <!-- Balance Info -->
                        <div class="d-flex align-items-center gap-3">
                            <span id="supplier-balance-container" class="fw-bold d-none">Supplier Balance: <span id="supplier-balance" class="text-primary">0.00</span></span>
                            <span class="fw-bold">Total Balance: <span id="total-balance" class="text-primary">0.00</span></span>
                        </div>

                        <!-- Buttons -->
                        <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                            <i class="fas fa-trash-alt me-1"></i> Delete Selected
                        </button>
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal"
                            data-bs-target="#createContainerPlanning">
                            <i class="fas fa-plus-circle me-1"></i> Add Container Planning
                        </button>
                    </div>

                    <div id="container-planning"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createContainerPlanning" tabindex="-1" aria-labelledby="createContainerPlanningLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="createContainerPlanningLabel">
                        <i class="fas fa-file-invoice me-2"></i> Create Container Planning
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <form id="containerPlanningForm" method="POST" action="{{ route('container.planning.save') }}" enctype="multipart/form-data"
                    autocomplete="off">
                    @csrf
                    <input type="hidden" name="id" id="record_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Container Number -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Container Number</label>
                                <select name="container_number" class="form-select" required>
                                    <option value="">Select Container</option>
                                    @foreach ($containers as $container)
                                        <option value="{{ $container->tab_name }}">{{ $container->tab_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- PO Number Link -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">PO Number</label>
                                <select name="po_number" class="form-select" required>
                                    <option value="">Select PO Number</option>
                                    @foreach ($purchaseOrders as $order)
                                        <option value="{{ $order->po_number }}">{{ $order->po_number }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Supplier Name -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Supplier Name</label>
                                <select name="supplier_id" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Area -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Area</label>
                                <input type="text" name="area" class="form-control">
                            </div>

                            <!-- Packing List Link -->
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Packing List Link</label>
                                <input type="url" name="packing_list_link" class="form-control">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <select name="currency" id="currency" class="form-select" required>
                                    <option value="">Select currency</option>
                                    <option value="USD">USD</option>
                                    <option value="CNY">RMB</option>
                                </select>
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
                                <label class="form-label fw-semibold">Pay Term</label>
                                <select name="pay_term" class="form-select">
                                    <option value="">Select Term</option>
                                    <option value="EXW">EXW</option>
                                    <option value="FOB">FOB</option>
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
                ajaxURL: "/container-planning/data",
                ajaxConfig: "GET",
                layout: "fitData",
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "500px",
                columns: [{
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
                        title: "PO Number",
                        field: "po_number"
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
                        title: "Packing List",
                        field: "packing_list_link",
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
                        title: "Pay Term",
                        field: "pay_term"
                    },
                    {
                        title: "Created At",
                        field: "created_at"
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

            // Update balances function
            function updateBalances() {
                const allData = table.getData();
                const filteredData = table.getData("active");

                let totalBalance = 0;
                let supplierBalance = 0;

                const supplierId = document.getElementById("filter-supplier").value;

                allData.forEach(row => {
                    totalBalance += parseFloat(row.balance || 0);
                });

                filteredData.forEach(row => {
                    if (supplierId && row.supplier_id == supplierId) {
                        supplierBalance += parseFloat(row.balance || 0);
                    }
                });

                document.getElementById("total-balance").innerText = totalBalance.toFixed(0);

                const supplierContainer = document.getElementById("supplier-balance-container");
                if (supplierId) {
                    supplierContainer.classList.remove("d-none");
                    document.getElementById("supplier-balance").innerText = supplierBalance.toFixed(0);
                } else {
                    supplierContainer.classList.add("d-none");
                }
            }

            // Apply filters
            function applyFilters() {
                const container = document.getElementById("filter-container").value;
                const supplier = document.getElementById("filter-supplier").value;
                const keyword = document.getElementById("container-planning-search").value.toLowerCase();

                const filters = [];

                if (container) {
                    filters.push({ field: "container_number", type: "=", value: container });
                }

                if (supplier) {
                    filters.push({ field: "supplier_id", type: "=", value: supplier });
                }

                if (keyword) {
                    filters.push([
                        { field: "container_number", type: "like", value: keyword },
                        { field: "po_number", type: "like", value: keyword },
                    ]);
                }

                table.setFilter(filters);
                updateBalances();
            }

            // Container filter: update supplier dropdown dynamically
            document.getElementById("filter-container").addEventListener("change", function() {
                const container = this.value;
                const supplierSelect = document.getElementById("filter-supplier");

                // Reset supplier dropdown
                supplierSelect.innerHTML = '<option value="">Select Supplier</option>';

                if (container) {
                    // Get all suppliers in this container
                    const containerRows = table.getData().filter(row => row.container_number === container);
                    const uniqueSuppliers = [...new Set(containerRows.map(r => r.supplier_id))];

                    uniqueSuppliers.forEach(supplierId => {
                        const supplierName = containerRows.find(r => r.supplier_id == supplierId).supplier_name;
                        const option = document.createElement("option");
                        option.value = supplierId;
                        option.text = supplierName;
                        supplierSelect.appendChild(option);
                    });
                }

                // Apply container filter (without forcing supplier)
                applyFilters();
            });

            // Supplier filter
            document.getElementById("filter-supplier").addEventListener("change", applyFilters);

            // Search input
            document.getElementById("container-planning-search").addEventListener("input", applyFilters);

            // Update balances on table events
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
                    url: '/container-planning/delete',
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

            fetchPurchaseDetails();

            function fetchPurchaseDetails() {
                const poSelect = document.querySelector('select[name="po_number"]');
                const supplierSelect = document.querySelector('select[name="supplier_id"]');
                const invoiceValue = document.querySelector('input[name="invoice_value"]');
                const paid = document.querySelector('input[name="paid"]');
                const balance = document.querySelector('input[name="balance"]');

                poSelect.addEventListener('change', function() {
                    let poId = this.value;
                    if (!poId) return;

                    fetch(`/container-planning/po-details/${poId}`)
                        .then(res => res.json())
                        .then(data => {
                            supplierSelect.value = data.supplier_id;
                            invoiceValue.value = data.total_amount;
                            paid.value = data.advance_amount;
                            balance.value = data.balance;
                        })
                        .catch(err => console.error(err));
                });
            }

            function openEditModal(rowData) {
                // Hidden ID set
                document.getElementById("record_id").value = rowData.id;
                console.log("Container Number from row:", rowData.container_number);

                // Form ke saare fields set karna
                document.querySelector('select[name="container_number"]').value = rowData.container_number;
                document.querySelector('select[name="po_number"]').value = rowData.po_number;
                document.querySelector('select[name="supplier_id"]').value = rowData.supplier_id;
                document.querySelector('input[name="area"]').value = rowData.area || '';
                document.querySelector('input[name="packing_list_link"]').value = rowData.packing_list_link || '';
                document.querySelector('input[name="invoice_value"]').value = rowData.invoice_value || '';
                document.querySelector('input[name="paid"]').value = rowData.paid || '';
                document.querySelector('input[name="balance"]').value = rowData.balance || '';
                document.querySelector('select[name="pay_term"]').value = rowData.pay_term || '';

                // Modal ka title change
                document.getElementById("createContainerPlanningLabel").innerText = "Edit Container Planning";

                // Modal open karo
                let modal = new bootstrap.Modal(document.getElementById("createContainerPlanning"));
                modal.show();
            }
            

        });
    </script>
    <script>
        async function updateBalance() {
            let invoice = parseFloat(document.getElementById('invoice_value').value) || 0;
            let paid = parseFloat(document.getElementById('paid').value) || 0;
            let currency = document.getElementById('currency').value;

            if (!invoice && !paid) {
                document.getElementById('balance').value = 0;
                return;
            }

            if (currency === "CNY") {
                try {
                    // Convert invoice
                    let resInvoice = await fetch(`/convert-currency?amount=${invoice}&from=CNY&to=USD`);
                    let dataInvoice = await resInvoice.json();

                    if (dataInvoice?.rates?.USD) {
                        invoice = dataInvoice.rates.USD;
                        document.getElementById('invoice_value').value = invoice.toFixed(2);
                    }

                    // Convert paid
                    let resPaid = await fetch(`/convert-currency?amount=${paid}&from=CNY&to=USD`);
                    let dataPaid = await resPaid.json();

                    if (dataPaid?.rates?.USD) {
                        paid = dataPaid.rates.USD;
                        document.getElementById('paid').value = paid.toFixed(2);
                    }
                } catch (e) {
                    alert("❌ Conversion API failed");
                    return;
                }
            }

            // Always calculate in USD
            let balance = invoice - paid;
            document.getElementById('balance').value = balance.toFixed(2);
        }

        document.getElementById('invoice_value').addEventListener('blur', updateBalance);
        document.getElementById('paid').addEventListener('blur', updateBalance);
        document.getElementById('currency').addEventListener('change', updateBalance);
    </script>


@endsection
