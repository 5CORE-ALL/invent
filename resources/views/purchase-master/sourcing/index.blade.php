@extends('layouts.vertical', ['title' => 'Sourcing'])
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
    @include('layouts.shared.page-title', ['page_title' => 'Sourcing', 'sub_title' => 'Sourcing'])

    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #03a744 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                        <!-- Left: Total Pending -->
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="text-black mb-0">Total Pending:</h5>
                            <span id="total-pending" class="text-black fw-bold fs-4 px-3 py-1 bg-warning rounded shadow-sm">0</span>
                        </div>

                        <!-- Center: Issue Date Filter -->
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-semibold mb-0">Filter by Issue Date:</label>
                            <select id="issue-date-filter" class="form-select form-select-sm w-auto">
                                <option value="all">All</option>
                                <option value="red">Red</option>
                                <option value="yellow">Yellow</option>
                                <option value="green">Green</option>
                            </select>
                        </div>

                        <!-- Right: Actions -->
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-danger d-none" id="delete-selected-btn">
                                <i class="fas fa-trash-alt me-1"></i> Delete Selected
                            </button>
                            <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal"
                                data-bs-target="#createSourcingModal">
                                <i class="fas fa-plus-circle me-1"></i> Create Sourcing
                            </button>
                        </div>
                    </div>

                    <div id="sourcing-table"></div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="createSourcingModal" tabindex="-1" aria-labelledby="createSourcingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
            <div class="modal-content shadow-lg rounded-4 border-0">
                <form action="{{ route('sourcing.save') }}" method="POST" id="sourcingForm" class="p-3">
                    @csrf
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold" id="createSourcingModalLabel">
                            <i class="fas fa-boxes me-2 text-primary"></i> Add New Sourcing
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body pt-2">
                        <div class="row g-3">
                            <!-- Target Item -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Target Item</label>
                                <input type="text" name="target_item" class="form-control"
                                    placeholder="Enter Target Item">
                            </div>

                            <!-- Target Link 1 -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Target Link 1 <span
                                        class="text-danger">*</span></label>
                                <input type="url" name="target_link1" class="form-control" required
                                    placeholder="Enter Target Link 1">
                            </div>

                            <!-- Target Link 2 -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Target Link 2 </label>
                                <input type="url" name="target_link2" class="form-control"
                                    placeholder="Enter Target Link 2">
                            </div>

                            <!-- Product Description -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Product Description</label>
                                <input type="text" name="product_description" class="form-control"
                                    placeholder="Enter Product Description">
                            </div>

                            <!-- RFQ Form -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">RFQ Form</label>
                                <input type="text" name="rfq_form" class="form-control" placeholder="Enter RFQ Form">
                            </div>

                            <!-- RFQ Report -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">RFQ Report</label>
                                <input type="text" name="rfq_report" class="form-control" placeholder="Enter RFQ Report">
                            </div>

                            <!-- Status -->
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="hold">Hold</option>
                                    <option value="working">Working</option>
                                    <option value="done">Done</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 pt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="fas fa-save me-2"></i> Save Sourcing
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>



@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const table = new Tabulator("#sourcing-table", {
                ajaxURL: "/sourcing-data/list",
                ajaxConfig: "GET",
                layout: "fitColumns",
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
                        width: 50,
                        headerSort: false
                    },
                    {
                        title: "Sr. No.",
                        formatter: "rownum",
                        hozAlign: "center",
                        width: 90,
                        visible: false
                    },
                    {
                        title: "Issue Date",
                        field: "created_at",
                        hozAlign: "center",
                        width: "120",
                        formatter: function(cell) {
                            let date = cell.getValue();
                            if (!date) return "";

                            let d = new Date(date);
                            let today = new Date();
                            let diffTime = today - d; 
                            let diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                            let day = d.getDate();
                            let month = d.toLocaleString('default', { month: 'short' });
                            let formattedDate = `${day} ${month}`;

                            let color = '';
                            if (diffDays >= 30) {
                                color = 'red';
                            } else if (diffDays >= 15) {
                                color = '#F6B703';
                            }else{
                                color = 'green';

                            }
                            return `<div style="line-height:1.5;">
                                <span style="color: ${color}; font-weight:600;">${formattedDate}</span><br>
                                <small style="color:#555;">${diffDays} day${diffDays > 1 ? 's' : ''} ago</small>
                            </div>`;
                        }
                    },
                    {
                        title: "Target Item",
                        field: "target_item",
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: 'Target1 <i class="fas fa-link"></i>',
                        field: "target_link1",
                        formatter: function(cell) {
                            let url = cell.getValue();
                            if (url) {
                                return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary p-1">
                                    <i class="fas fa-link"></i>
                                </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        hozAlign: "center",
                        width: "115"
                    },
                    {
                        title: 'Target2 <i class="fas fa-link"></i>',
                        field: "target_link2",
                        formatter: function(cell) {
                            let url = cell.getValue();
                            if (url) {
                                return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary p-1">
                                    <i class="fas fa-link"></i>
                                </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        hozAlign: "center",
                        width: "115"
                    },
                    {
                        title: "Product Description",
                        field: "product_description",
                        editor: "textarea",
                        hozAlign: "center",
                        formatter: function(cell) {
                            let value = cell.getValue() || "";
                            return `<span title="${value.replace(/"/g, '&quot;')}">${value}</span>`;
                        }
                    },
                    {
                        title: "RFQ Form",
                        field: "rfq_form",
                        formatter: function(cell) {
                            let url = cell.getValue();
                            if (url) {
                                return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "RFQ Report",
                        field: "rfq_report",
                        formatter: function(cell) {
                            let url = cell.getValue();
                            if (url) {
                                return `<a href="${url}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>`;
                            }
                            return "";
                        },
                        editor: "input",
                        hozAlign: "center"
                    },
                    {
                        title: "Status",
                        field: "status",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const value = cell.getValue() ?? '';
                            const sourcingID = row.getData().id;

                            return `
                                <select class="form-select form-select-sm editable-select"
                                    data-id="${sourcingID}"
                                    data-field="status">
                                    <option value="">Select</option>
                                    <option value="hold" ${value === 'hold' ? 'selected' : ''}>Hold</option>
                                    <option value="working" ${value === 'working' ? 'selected' : ''}>Working</option>
                                    <option value="done" ${value === 'done' ? 'selected' : ''}>Done</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    }

                ],
            });
            table.on("dataLoaded", function(){
                setTimeout(updatePendingCount, 50);
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            table.on("cellEdited", function(cell){
                let id = cell.getRow().getData().id;   // row ka id
                let field = cell.getField();           // kaunsa column edit hua
                let value = cell.getValue();           // new value

                $.ajax({
                    url: `/sourcing/update/${id}`,
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        field: field,
                        value: value
                    },
                    success: function(res) {
                        console.log("Updated successfully", res);
                    },
                    error: function(xhr) {
                        console.error("Update failed", xhr);
                    }
                });
            });

            $(document).on("change", ".editable-select", function() {
                let id = $(this).data("id");       
                let field = $(this).data("field"); 
                let value = $(this).val();         

                if (!id || !field) return;

                $.ajax({
                    url: `/sourcing/update/${id}`,
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        field: field,
                        value: value
                    },
                    success: function(res) {
                    },
                    error: function(xhr) {
                    }
                });
            });
            
            deleteWithSelect();

            function deleteWithSelect() {
                const deleteBtn = document.getElementById('delete-selected-btn');

                table.on("rowSelectionChanged", function(data, rows) {
                    deleteBtn.disabled = data.length === 0;
                });

                deleteBtn.addEventListener('click', function() {
                    const selectedRows = table.getSelectedRows();

                    if (selectedRows.length === 0) {
                        alert("Please select rows to delete.");
                        return;
                    }

                    if (!confirm(`Are you sure you want to delete ${selectedRows.length} row(s)?`)) return;

                    const idsToDelete = selectedRows.map(row => row.getData().id);

                    fetch('/sourcing/delete', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                ids: idsToDelete
                            }),
                        })
                        .then(res => res.json())
                        .then(response => {
                            if (response.success) {
                                selectedRows.forEach(row => row.delete());
                                // alert('Deleted successfully');
                            } else {
                                alert('Deletion failed');
                            }
                        })
                        .catch(() => alert('Error deleting rows'));
                });
            }
            
            function updatePendingCount() {
                const allData = table.getData(); // poori data, not just current page
                let pending = allData.filter(row => row.status !== 'done' && row.status !== '').length;
                document.getElementById('total-pending').textContent = pending;
            }

            // Filter by Issue Date color
            const issueDateFilter = document.getElementById('issue-date-filter');
            issueDateFilter.addEventListener('change', function() {
                const value = this.value;

                table.setFilter(function(data, filterParams){
                    const date = new Date(data.created_at);
                    const today = new Date();
                    const diffDays = Math.floor((today - date) / (1000 * 60 * 60 * 24));

                    if(value === 'red') return diffDays >= 30;
                    if(value === 'yellow') return diffDays >= 15 && diffDays < 30;
                    if(value === 'green') return diffDays < 15;
                    return true;
                });

                setTimeout(updatePendingCount, 50);
            });

        });
    </script>
