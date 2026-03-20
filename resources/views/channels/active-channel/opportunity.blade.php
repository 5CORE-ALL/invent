@extends('layouts.vertical', ['title' => 'Opportunities', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            color: #22223b;
            vertical-align: middle;
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

        .custom-select-wrapper {
            width: 100%;
            cursor: pointer;
            position: relative;
        }

        .custom-select-display {
            background-color: #fff;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
        }

        .custom-select-options {
            position: absolute;
            z-index: 999;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-top: none;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .custom-select-search {
            width: 100%;
            padding: 0.5rem;
            border: none;
            border-bottom: 1px solid #eee;
            outline: none;
        }

        .custom-select-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
        }

        .custom-select-option:hover {
            background-color: #f1f1f1;
        }

    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Opportunities',
        'sub_title' => 'Opportunities',
    ])
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert"
            style="background-color: #d1e7dd; color: #0f5132;">
            <style>
                .alert-success .btn-close {
                    filter: invert(41%) sepia(94%) saturate(362%) hue-rotate(89deg) brightness(90%) contrast(92%);
                }
            </style>
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <h4 class="mb-0 fw-bold text-primary">Opportunities</h4>
                        <div class="d-flex flex-wrap gap-3 align-items-center">

                            <button class="btn btn-danger d-none" id="delete-selected-btn">
                                <i class="fas fa-trash-alt me-1"></i> Delete Selected
                            </button>

                            <button class="btn btn-danger d-flex align-items-center gap-2 position-relative" id="approval-pending-btn">
                                <i class="fas fa-hourglass-half fs-5"></i>
                                <span>Not Applied</span>
                                <span class="badge bg-white text-danger fs-5 rounded-pill" id="pending-count-badge">0</span>
                            </button>

                            <button class="btn btn-warning d-flex align-items-center gap-2 position-relative text-black" id="approval-pending-btn">
                                <i class="fas fa-hourglass-half fs-5"></i>
                                <span>Not Applicable</span>
                                <span class="badge bg-black text-white fs-5 rounded-pill" id="not-applicable-badge">0</span>
                            </button>

                            <!-- Export Button -->
                            <a href="{{ route('opportunities.export') }}" class="btn btn-success">
                                <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                            </a>
                            <!-- Import Button -->
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#importModal">
                                <i class="fas fa-file-import me-1"></i> Import Excel/CSV
                            </button>
                        </div>
                    </div>
                    <div id="oppertunity-master"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('import.opportunities') }}" method="POST" enctype="multipart/form-data"
                class="modal-content" id="importForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Reviews</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Drag & Drop Area -->
                    <div id="drop-area" class="border border-2 border-primary rounded p-4 text-center mb-3"
                        style="cursor:pointer; position:relative;">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                        <p class="mb-1">Drag & drop your file here, or click to select</p>
                        <input type="file" name="excel_file" id="importFileInput"
                            style="opacity:0;position:absolute;top:0;left:0;width:100%;height:100%;cursor:pointer;"
                            required>
                        <div id="fileName" class="small text-muted"></div>
                    </div>
                    <div id="selectedFilePreview" class="mt-2"></div>
                    <a href="{{ asset('sample_excel/opportunities_import_sample.xlsx') }}" class="btn btn-link">
                        <i class="fas fa-download me-1"></i> Download Sample File
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-import me-1"></i> Import
                    </button>
                </div>
        </div>
        </form>
    </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const table = new Tabulator("#oppertunity-master", {
                ajaxURL: "/opportunities/data",
                ajaxConfig: "GET",
                layout: "fitDataFill",
                pagination: "local",
                paginationSize: 25,
                height: "700px",
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Type",
                        field: "type",
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const value = cell.getValue() ?? '';
                            const opportunityID = row.getData().id;

                            return `
                                <select class="form-select form-select-sm editable-select"
                                    data-id="${opportunityID}"
                                    data-field="type">
                                    <option value="">Select</option>
                                    <option value="Dropship" ${value === 'Dropship' ? 'selected' : ''}>Dropship</option>
                                    <option value="B2B" ${value === 'B2B' ? 'selected' : ''}>B2B</option>
                                    <option value="B2C" ${value === 'B2C' ? 'selected' : ''}>B2C</option>
                                    <option value="C2C" ${value === 'C2C' ? 'selected' : ''}>C2C</option>
                                    <option value="Promotional" ${value === 'Promotional' ? 'selected' : ''}>Promotional</option>
                                    <option value="Coupons" ${value === 'Coupons' ? 'selected' : ''}>Coupons</option>
                                </select>
                            `;
                        }
                    },
                    {
                        title: "Channel",
                        field: "channel_name"
                    },
                    {
                        title: "Regn Link",
                        field: "regn_link",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            return `
                                <a href="${value}" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            `;
                        },
                        headerSort: false
                    },
                    {
                        title: "Status",
                        field: "status",
                        editor: "input"
                    },
                    {
                        title: "A&A Stage",
                        field: "aa_stage",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const value = cell.getValue() ?? '';
                            const opportunityID = row.getData().id;

                            // Red background agar not_applicable
                            const bgStyle = value === 'not_applicable' ? 'background-color: #D03E3F;color:#fff;' : '';

                            return `
                                <select class="form-select form-select-sm editable-select"
                                    data-id="${opportunityID}"
                                    data-field="aa_stage"
                                    style="width: 120px; ${bgStyle}">
                                    <option value="">Select</option>
                                    <option value="under_review" ${value === 'under_review' ? 'selected' : ''}>Under Review</option>
                                    <option value="submit" ${value === 'submit' ? 'selected' : ''}>Submit Documentation</option>
                                    <option value="approved" ${value === 'approved' ? 'selected' : ''}>Approved</option>
                                    <option value="not_applicable" ${value === 'not_applicable' ? 'selected' : ''}>Not Applicable</option>
                                </select>
                            `;
                        }
                    },
                    {
                        title: "Priority",
                        field: "priority",
                        editor: "input"
                    },
                    {
                        title: "Item Sold",
                        field: "item_sold",
                        editor: "input"
                    },
                    {
                        title: "Link as Customer",
                        field: "link_as_customer",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            return `
                                <a href="${value}" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            `;
                        },
                        headerSort: false
                    },
                    {
                        title: "May, 24 Traffic",
                        field: "last_year_traffic",
                        editor: "input"
                    },
                    {
                        title: "May, 25 Traffic",
                        field: "current_traffic",
                        editor: "input"
                    },
                    {
                        title: "US Presence",
                        field: "us_presence",
                        editor: "input"
                    },
                    {
                        title: "US Visitor Count",
                        field: "us_visitor_count",
                        editor: "input"
                    },
                    {
                        title: "Comm Chgs",
                        field: "comm_chgs",
                        editor: "input"
                    },
                    {
                        title: "Current Status",
                        field: "current_status",
                        editor: "input"
                    },
                    {
                        title: "Final",
                        field: "final",
                        editor: "input"
                    },
                    {
                        title: "Date",
                        field: "date",
                        editor: "date",
                        sorter: "date"
                    },
                    {
                        title: "Email",
                        field: "email",
                        editor: "input"
                    },
                    {
                        title: "Remarks",
                        field: "remarks",
                        editor: "input"
                    },
                    {
                        title: "Sign Up Page Link",
                        field: "sign_up_page_link",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '';
                            return `
                                <a href="${value}" target="_blank" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            `;
                        },
                        headerSort: false
                    },
                    {
                        title: "Followup Dt-3rd June",
                        field: "followup_dt",
                        editor: "input"
                    },
                    {
                        title: "Masum Comment",
                        field: "masum_comment",
                        editor: "input"
                    },

                ],
            });
            table.on("cellEdited", function(cell) {
                const rowData = cell.getRow().getData();
                const field = cell.getField();
                const value = cell.getValue();

                $.ajax({
                    url: '/opportunities/save',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        channel_id: rowData.channel_id,
                        // opportunity_id: rowData.id,
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        console.log(response);
                        updateApprovalPendingCount();
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            table.on("dataProcessed", function() {
                updateApprovalPendingCount();
            });

            table.on("rowSelectionChanged", function(data, rows){
                if(data.length > 0){
                    $('#delete-selected-btn').removeClass('d-none');
                } else {
                    $('#delete-selected-btn').addClass('d-none');
                }
            });

            table.setFilter([
                [
                    { field: "aa_stage", type: "=", value: "" },
                    { field: "aa_stage", type: "=", value: "not_applicable" }
                ]
            ]);


            $(document).on('change', '.editable-select', function() {
                const $select = $(this);
                const id = $select.data('id');
                const field = $select.data('field');
                const value = $select.val();

                const row = table.getRows().find(r => r.getData().app === id);
                if (row) {
                    row.update({ [field]: value });
                }

                if (field === "aa_stage") {
                    if (value === "not_applicable") {
                        $select.css("background-color", "#D03E3F");
                        $select.css("color", "#fff");
                    } else {
                        $select.css("background-color", "");
                    }
                }

                $.ajax({
                    url: '/opportunities/save',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: id,
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        console.log(response);
                        updateApprovalPendingCount();

                        if (field === "aa_stage" && value.trim() !== "" && value.trim() !== "not_applicable") {
                            const row = table.getRows().find(r => r.getData().id === id);
                            if (row) {
                                table.deleteRow(row);
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
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
                    url: '/opportunities/delete',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ids: ids
                    },
                    success: function(response) {
                        table.deleteRow(ids);
                        updateApprovalPendingCount();
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                    }
                });
            });

            openImportModal();

            function openImportModal() {
                const dropArea = document.getElementById('drop-area');
                const fileInput = document.getElementById('importFileInput');
                const fileName = document.getElementById('fileName');
                const filePreview = document.getElementById('selectedFilePreview');

                dropArea.addEventListener('click', function(e) {
                    if (e.target !== fileInput) {
                        fileInput.click();
                    }
                });

                dropArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    dropArea.classList.add('bg-light');
                });

                dropArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                });

                dropArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    dropArea.classList.remove('bg-light');
                    if (e.dataTransfer.files.length) {
                        fileInput.files = e.dataTransfer.files;
                        showFileInfo(e.dataTransfer.files[0]);
                    }
                });

                fileInput.addEventListener('change', () => {
                    if (fileInput.files.length) {
                        showFileInfo(fileInput.files[0]);
                    } else {
                        fileName.textContent = '';
                        filePreview.innerHTML = '';
                    }
                });

                function showFileInfo(file) {
                    fileName.textContent = file.name;
                    filePreview.innerHTML = `
                <div class="alert alert-info py-2 px-3 mb-0 text-start">
                    <strong>File:</strong> ${file.name} <br>
                    <strong>Size:</strong> ${(file.size/1024).toFixed(2)} KB <br>
                    <strong>Type:</strong> ${file.type || 'Unknown'}
                </div>
            `;
                }

                if (fileInput.files.length) {
                    showFileInfo(fileInput.files[0]);
                }
            }
            function updateApprovalPendingCount() {
                const visibleData = table.getData("active");

                let pendingCount = 0;
                let underReviewCount = 0;

                visibleData.forEach(row => {
                    if (!row.aa_stage || row.aa_stage.trim() === '') {
                        pendingCount++;
                    }
                    if (row.aa_stage && row.aa_stage.trim() === 'not_applicable') {
                        underReviewCount++;
                    }
                });

                $('#pending-count-badge').text(pendingCount);
                $('#not-applicable-badge').text(underReviewCount);
            }


            document.body.style.zoom = '85%';
        });
    </script>
@endsection
