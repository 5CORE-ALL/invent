@extends('layouts.vertical', ['title' => 'Stock Balance', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <!-- Add DataTables Buttons CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />


    <style>
        /* Your existing styles */
        .dt-buttons .btn {
            margin-left: 10px;
        }

        .dataTables_wrapper .dataTables_filter input {
            border-radius: 4px;
            border: 1px solid #ddd;
            padding: 5px;
        }
    </style>
    <style>
        /* Add this to your existing styles */
        .table-responsive {
            position: relative;
            border: 1px solid #dee2e6;
            max-height: 600px;
            /* or whatever height you prefer */
            overflow-y: auto;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background-color: #2c6ed5;
            /* Grid blue color */
            color: white;
            /* White text for better contrast */
            z-index: 10;
            padding: 12px 15px;
            /* Adjust padding as needed */
            font-weight: 600;
            /* Make header text slightly bold */
            border-bottom: 2px solid #1a56b7;
            /* Darker blue border bottom */
        }

        /* Optional: Add some shadow to the sticky header */
        .table-responsive thead th {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Hover effect for header cells */
        .table-responsive thead th:hover {
            background-color: #1a56b7;
            /* Slightly darker blue on hover */
        }

        /* Style for table cells to match the design */
        .table-responsive tbody td {
            padding: 10px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e0e0e0;
        }

        /* Alternate row coloring for better readability */
        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        /* Hover effect for rows */
        .table-responsive tbody tr:hover {
            background-color: #ebf2fb;
        }
    </style>
    <style>
        /* Override DataTables styles if needed */
        #inventoryTable thead th {
            background-color: #2c6ed5 !important;
            color: white !important;
        }

        /* Ensure DataTables sorting icons are visible */
        #inventoryTable thead th.sorting:after,
        #inventoryTable thead th.sorting_asc:after,
        #inventoryTable thead th.sorting_desc:after {
            color: white !important;
            opacity: 0.8 !important;
        }

        /* Large Error Display */
        .error-alert-large {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
            min-width: 500px;
            max-width: 800px;
            background: #fff;
            border: 5px solid #dc3545;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 30px;
            animation: shake 0.5s;
        }

        .error-alert-large .error-title {
            color: #dc3545;
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
        }

        .error-alert-large .error-message {
            color: #dc3545;
            font-size: 24px;
            font-weight: 600;
            line-height: 1.5;
            text-align: center;
            margin-bottom: 25px;
            word-wrap: break-word;
        }

        .error-alert-large .error-details {
            background: #f8d7da;
            border: 2px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #721c24;
            font-size: 16px;
        }

        .error-alert-large .btn-close-error {
            width: 100%;
            padding: 15px;
            font-size: 20px;
            font-weight: bold;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .error-alert-large .btn-close-error:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .error-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9998;
        }

        @keyframes shake {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            10%, 30%, 50%, 70%, 90% { transform: translate(-50%, -50%) rotate(-2deg); }
            20%, 40%, 60%, 80% { transform: translate(-50%, -50%) rotate(2deg); }
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Stock Balance Inventory',
        'sub_title' => 'Stock Balance',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">


                    <!-- Search Box and Add Button-->
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <button type="button" class="btn btn-primary" id="openAddWarehouseModal" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                                <i class="fas fa-plus me-1"></i> CREATE STOCK BALANCE
                            </button>
                            <div class="dataTables_length ms-3"></div>
                        </div>

                        <div class="col-md-3 offset-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control" placeholder="Search">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                    </div>


                    <!-- <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addWarehouseModal">
                            <i class="fas fa-plus me-1"></i> ADD WAREHOUSE
                        </button>
                        <button type="button" class="btn btn-success ms-2" id="downloadExcel">
                            <i class="fas fa-file-excel me-1"></i> Download Excel
                        </button>
                    </div> -->

                    <!-- Transfer Modal -->
                    <!-- Transfer Stock Modal -->
                    <div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="transferStockModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">

                        <div class="modal-header">
                            <h5 class="modal-title"> Stock Balance</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <form id="stockBalanceForm">
                                @csrf

                            <div class="row">
                                <!-- From Warehouse Section -->
                                <div class="col-md-6 p-3">
                                <h5><strong> TO </strong></h5>

                                <div class="mb-3">
                                    <label for="from_sku" class="form-label fw-bold">SKU</label>
                                    <select class="form-select" id="from_sku" name="from_sku" required>
                                        <option selected disabled>Select SKU</option>
                                        @foreach($skus as $item)
                                            <option value="{{ $item->sku }}" data-parent="{{ $item->parent }}"  data-available_qty="{{ $item->available_quantity }}" data-dil="{{ $item->dil }}">{{ $item->sku }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="from_parent_name" class="form-label fw-bold">Parent</label>
                                    <input type="text" class="form-control" id="from_parent_name" name="from_parent_name" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="from_available_qty" class="form-label fw-bold">Available Qty</label>
                                    <input type="number" id="from_available_qty" name="from_available_qty" class="form-control" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="from_dil_percent" class="form-label fw-bold">Dil%</label>
                                    <input type="number" id="from_dil_percent" name="from_dil_percent" class="form-control" min="0" max="100" step="0.01">
                                </div>

                                <div class="mb-3">
                                    <label for="from_adjust_qty" class="form-label fw-bold">Qty Adj</label>
                                    <input type="number" name="from_adjust_qty" class="form-control">
                                </div>

                                <!-- <div class="mb-3 text-center">
                                    <label><strong>To</strong></label>
                                </div> -->
                                </div>

                                <!-- To Warehouse Section -->
                                <div class="col-md-6 p-3">
                                <h5><strong>From</strong></h5>

                                
                                <!-- <div class="mb-3">
                                    <label>SKU</label>
                                    <select name="to_sku" class="form-control">
                                    <option value="">Select SKU</option>
                                    </select>
                                </div> -->

                                <div class="mb-3">
                                    <label for="to_sku" class="form-label fw-bold">SKU</label>
                                    <select class="form-select" id="to_sku" name="to_sku" required>
                                        <option selected disabled>Select SKU</option>
                                        @foreach($skus as $item)
                                            <option value="{{ $item->sku }}" data-parent="{{ $item->parent }}" data-to_available_qty="{{ $item->available_quantity }}" data-to_dil="{{ $item->dil }}">{{ $item->sku }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="to_parent_name" class="form-label fw-bold">Parent</label>
                                    <input type="text" class="form-control" id="to_parent_name" name="to_parent_name" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="to_available_qty" class="form-label fw-bold">Available Qty</label>
                                    <input type="number" id="to_available_qty" name="to_available_qty" class="form-control" readonly>
                                </div>

                                <div class="mb-3">
                                    <label for="to_dil_percent" class="form-label fw-bold">Dil%</label>
                                    <input type="number" id="to_dil_percent" name="to_dil_percent" class="form-control" min="0" max="100" step="0.01">
                                </div>

                                <div class="mb-3">
                                    <label for="to_adjust_qty" class="form-label fw-bold">Qty Adj</label>
                                    <input type="number" name="to_adjust_qty" class="form-control">
                                </div>

                                <div class="mt-4 text-end">
                                    <button type="submit" class="btn btn-success">Submit</button>
                                </div>
                                </div>
                            </div>

                            </form>
                        </div>

                        </div>
                    </div>
                    </div>



                    <!-- Progress Modal -->
                    <div id="progressModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Processing Data</h5>
                                </div>
                                <div class="modal-body">
                                    <div id="progress-container" class="mb-3"></div>
                                    <div id="error-container"></div>
                                    <div id="success-alert" class="alert alert-success" style="display:none">
                                        All sheets updated successfully!
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button id="cancelUploadBtn" class="btn btn-secondary">Cancel</button>
                                    <button id="doneBtn" class="btn btn-primary" style="display:none">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="inventoryTable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>From Parent</th>
                                    <th>From SKU</th>
                                    <th>From DIL %</th>
                                    <th>From Available</th>
                                    <th>From Adjust Qty</th>
                                    <th>To Parent</th>
                                    <th>To SKU</th>
                                    <th>To DIL %</th>
                                    <th>To Available</th>
                                    <th>To Adjust Qty</th>
                                    <th>Transferred By</th>
                                    <th>Transferred At</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-table-body">
                                <!-- Rows will be dynamically inserted here -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Rainbow Wave Loader -->
                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Outgoing Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- Load jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


    <script>


        document.addEventListener('DOMContentLoaded', function() {
            // Set zoom level
            document.body.style.zoom = "75%";

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Store the loaded data globally
            let tableData = [];

            function setupProgressModal() {
                const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
                const cancelUploadBtn = document.getElementById('cancelUploadBtn');
                const doneBtn = document.getElementById('doneBtn');
                let uploadInProgress = false;
                let currentUpload = null;

                cancelUploadBtn.addEventListener('click', function() {
                    if (uploadInProgress && currentUpload) {
                        currentUpload.abort();
                    }
                    progressModal.hide();
                });

                doneBtn.addEventListener('click', function() {
                    progressModal.hide();
                });

                window.showUploadProgress = function(sheets) {
                    const progressContainer = document.getElementById('progress-container');
                    const errorContainer = document.getElementById('error-container');

                    progressContainer.innerHTML = '';
                    errorContainer.innerHTML = '';
                    document.getElementById('success-alert').style.display = 'none';
                    doneBtn.style.display = 'none';
                    cancelUploadBtn.disabled = false;
                    uploadInProgress = true;

                    sheets.forEach(sheet => {
                        progressContainer.innerHTML += `
                            <div class="progress-item mb-3" id="${sheet.id}-container">
                                <h6 class="d-flex align-items-center">
                                    <i class="fas fa-file-excel text-primary me-2"></i>
                                    ${sheet.displayName}
                                    <span id="${sheet.id}-icon" class="ms-auto">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                    </span>
                                </h6>
                                <div class="progress">
                                    <div id="${sheet.id}-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                        role="progressbar" style="width: 0%"></div>
                                </div>
                                <div id="${sheet.id}-status" class="small text-muted mt-1">Initializing...</div>
                                <div id="${sheet.id}-error" class="small text-danger mt-1"></div>
                            </div>
                        `;
                    });

                    progressModal.show();
                };

                window.updateUploadProgress = function(sheetId, progress, status, isSuccess, errorMessage) {
                    const progressEl = document.getElementById(`${sheetId}-progress`);
                    const statusEl = document.getElementById(`${sheetId}-status`);
                    const iconEl = document.getElementById(`${sheetId}-icon`);
                    const errorEl = document.getElementById(`${sheetId}-error`);

                    if (progressEl && statusEl && iconEl) {
                        progressEl.style.width = `${progress}%`;

                        if (isSuccess) {
                            progressEl.classList.remove('progress-bar-animated');
                            progressEl.classList.add('bg-success');
                            statusEl.textContent = status || 'Completed successfully';
                            statusEl.classList.add('text-success');
                            iconEl.innerHTML = '<i class="fas fa-check-circle text-success"></i>';
                        } else if (progress === 100) {
                            progressEl.classList.remove('progress-bar-animated');
                            progressEl.classList.add('bg-danger');
                            statusEl.textContent = status || 'Failed';
                            statusEl.classList.add('text-danger');
                            iconEl.innerHTML = '<i class="fas fa-times-circle text-danger"></i>';

                            if (errorMessage) {
                                errorEl.textContent = errorMessage;
                                document.getElementById('error-container').innerHTML += `
                                    <div class="alert alert-danger py-2 mb-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>${sheetId} Error:</strong> ${errorMessage}
                                    </div>
                                `;
                            }
                        } else {
                            statusEl.textContent = status || 'Processing...';
                        }
                    }
                };

                window.completeUpload = function(successCount, totalCount) {
                    uploadInProgress = false;
                    cancelUploadBtn.disabled = true;

                    if (successCount === totalCount) {
                        document.getElementById('success-alert').style.display = 'block';
                        doneBtn.style.display = 'block';
                    } else {
                        document.getElementById('error-container').innerHTML += `
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                ${successCount}/${totalCount} sheets updated successfully
                            </div>
                        `;
                        doneBtn.style.display = 'block';
                    }
                };
            }

            function initializeTable() {
                loadData();
                setupSearch();
                setupAddWarehouseModal();
                setupProgressModal();
                setupEditDeleteButtons();
                // setupEditButtons();
            }
            

            $(document).ready(function () {

                $('#stockBalanceForm').on('submit', function (e) {
                    e.preventDefault();

                    const formData = $(this).serialize();
                    const $submitBtn = $(this).find('button[type="submit"]');
                    const originalText = $submitBtn.text();
                    $submitBtn.prop('disabled', true).text('Processing...');

                    $.ajax({
                        url: '{{ route("stock.balance.store") }}',
                        method: 'POST',
                        data: formData,
                        success: function (response) {
                            $submitBtn.prop('disabled', false).text(originalText);
                            $('#addWarehouseModal').modal('hide');
                            loadData(); // Reload after store
                            $('#stockBalanceForm')[0].reset();
                            showSuccessAlert(response.message || 'Stock transferred successfully!');
                        },
                        error: function (xhr) {
                            $submitBtn.prop('disabled', false).text(originalText);
                            console.log('Full Error Response:', xhr);
                            
                            let errorMessage = 'Error storing stock balance';
                            let errorDetails = '';
                            
                            if (xhr.responseJSON && xhr.responseJSON.error) {
                                errorMessage = xhr.responseJSON.error;
                                // Check if there are additional details
                                if (xhr.responseJSON.details) {
                                    errorDetails = xhr.responseJSON.details;
                                }
                            } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                                // Handle validation errors
                                const errors = xhr.responseJSON.errors;
                                errorMessage = Object.values(errors).flat().join('<br>');
                            } else if (xhr.status === 429) {
                                errorMessage = 'Shopify API rate limit exceeded!';
                                errorDetails = 'Too many requests. Please wait 5-10 seconds and try again.';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server Error';
                                errorDetails = xhr.responseJSON?.message || 'Please check the logs or contact support.';
                            } else if (xhr.status === 0) {
                                errorMessage = 'Connection Failed';
                                errorDetails = 'Unable to connect to the server. Please check your internet connection.';
                            }
                            
                            // Add SKU info to error details
                            const fromSku = $('#from_sku option:selected').text();
                            const toSku = $('#to_sku option:selected').text();
                            if (fromSku || toSku) {
                                errorDetails += (errorDetails ? '<br><br>' : '');
                                if (fromSku) errorDetails += '<strong>From SKU:</strong> ' + fromSku + '<br>';
                                if (toSku) errorDetails += '<strong>To SKU:</strong> ' + toSku;
                            }
                            
                            showLargeErrorAlert(errorMessage, errorDetails);
                        }
                    });
                });

                $('#from_sku').select2({
                    dropdownParent: $('#addWarehouseModal'),
                    placeholder: "Select SKU",
                    allowClear: true
                });
                
                // Auto-fill Parent when select sku
                // $('#sku').on('change', function () {
                //     const parent = $(this).find('option:selected').data('parent');
                //     $('#parent').val(parent || '');
                // });

                $('#to_sku').select2({
                    dropdownParent: $('#addWarehouseModal'),
                    placeholder: "Select SKU",
                    allowClear: true
                });
                
                // Auto-fill Parent when select sku_to
                // $('#sku_to').on('change', function () {
                //     const parent = $(this).find('option:selected').data('parent');
                //     $('#to_parent_name').val(parent || '');
                // });

                // Auto-fill Avl Qty when select sku_to
                $('#from_sku').on('change', function () {
                    const selected = $(this).find('option:selected');
                    
                    //for parent
                    const parent = selected.data('parent');
                    $('#from_parent_name').val(parent || '');

                    // for from Available Qty
                    const availableQty = selected.data('available_qty');
                    $('#from_available_qty').val(availableQty || 0);

                    //for from DIL
                    let fromDil = selected.data('dil') || 0;
                    // Cap at 100% to prevent validation errors
                    if (fromDil > 100) {
                        fromDil = 100;
                    }
                    console.log('dilll',fromDil);
                    
                    $('#from_dil_percent').val(fromDil);

                });


                // Auto-fill Avl Qty when select sku_to
                $('#to_sku').on('change', function () {
                    const selected = $(this).find('option:selected');
                    
                    // for to parent
                    const parent = selected.data('parent');
                    $('#to_parent_name').val(parent || '');

                    //  for to Available Qty
                    const availableQty = selected.data('to_available_qty');
                    $('#to_available_qty').val(availableQty || 0);

                     //for to DIL
                    let toDil = selected.data('to_dil') || 0;
                    // Cap at 100% to prevent validation errors
                    if (toDil > 100) {
                        toDil = 100;
                    }
                    console.log('dilll',toDil);
                    
                    $('#to_dil_percent').val(toDil);
                });

               
                $(document).on('click', '#openAddWarehouseModal', function () {
                    $('#stockBalanceForm')[0].reset(); // Only resets for add
                    $('#warehouseId').val('');
                    $('#warehouseModalLabel').text('Create Stock Transfer');
                    // $('#warehouseGroup').val('').trigger('change');

                    // Auto-fill PO number and date when modal is shown
                   
                    const ohioTime = new Date(
                        new Intl.DateTimeFormat('en-US', {
                            timeZone: 'America/New_York',
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                        }).format(new Date())
                    );

                    // Format to YYYY-MM-DD for input field
                    const yyyy = ohioTime.getFullYear();
                    const mm = String(ohioTime.getMonth() + 1).padStart(2, '0');
                    const dd = String(ohioTime.getDate()).padStart(2, '0');
                    const formattedDate = `${yyyy}-${mm}-${dd}`;

                    $('#date').val(formattedDate);

                    $('#addWarehouseModal').modal('show');
                });

            });


            function loadData() {
                $.ajax({
                    url: '/stock-balance-data-list',
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    beforeSend: function () {
                        $('#rainbow-loader').show(); 
                    },
                    success: function (response) {
                        tableData = response.data || [];
                        renderTable(tableData);
                        setupSearch();
                        $('#rainbow-loader').hide();
                    },
                    error: function(xhr) {
                        console.error("Load error:", xhr.responseText);
                    }
                });
            }

            
            function renderTable(data) {
                const tbody = document.getElementById('inventory-table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="12" class="text-center">No records found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');

                    row.innerHTML = `
                        <td>${item.from_parent_name || '-'}</td>
                        <td>${item.from_sku || '-'}</td>
                        <td>${item.from_dil_percent != null ? `${parseFloat(item.from_dil_percent).toFixed(0)}%` : '-'}</td>
                        <td>${item.from_available_qty || '-'}</td>
                        <td>${item.from_adjust_qty || '-'}</td>

                        <td>${item.to_parent_name || '-'}</td>
                        <td>${item.to_sku || '-'}</td>
                         <td>${item.to_dil_percent != null ? `${parseFloat(item.to_dil_percent).toFixed(0)}%` : '-'}</td>
                        <td>${item.to_available_qty || '-'}</td>
                        <td>${item.to_adjust_qty || '-'}</td>

                        <td>${item.transferred_by || '-'}</td>
                        <td>${item.transferred_at || '-'}</td>
                    `;

                    tbody.appendChild(row);
                });
            }



            function setupSearch() {
                const searchInput = document.getElementById('customSearch');
                const clearButton = document.getElementById('clearSearch');

                searchInput.addEventListener('input', debounce(function() {
                    const searchTerm = this.value.toLowerCase().trim();

                    if (!searchTerm) {
                        renderTable(tableData);
                        return;
                    }

                    const filteredData = tableData.filter(item =>
                        Object.values(item).some(value =>
                            String(value).toLowerCase().includes(searchTerm)
                        )
                    );

                    renderTable(filteredData);
                }, 300));

                clearButton.addEventListener('click', function() {
                    searchInput.value = '';
                    renderTable(tableData);
                });
            }


            function setupAddWarehouseModal() {
                const modal = document.getElementById('addProductModal');
                const saveBtn = document.getElementById('saveProductBtn');
                const refreshParentsBtn = document.getElementById('refreshParents');

                $(saveBtn).off('click');

            }

            function setupEditDeleteButtons() {
                // EDIT BUTTON
                $(document).on('click', '.edit-btn', function () {
                    const id = $(this).data('id');
                    const warehouse = tableData.find(w => w.id == id);

                    if (warehouse) {
                        $('#warehouseModalLabel').text('Edit Warehouse');
                        $('#warehouseId').val(warehouse.id);
                        $('#warehouseName').val(warehouse.name);
                        $('#warehouseGroup').val(warehouse.group).trigger('change');
                        $('#warehouseLocation').val(warehouse.location);
                        $('#addWarehouseModal').modal('show');
                    }
                });

                // DELETE BUTTON
                $(document).on('click', '.delete-btn', function () {
                    const id = $(this).data('id');

                    if (confirm('Are you sure you want to delete this warehouse?')) {
                        $.ajax({
                            url: `/warehouses/${id}`,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function () {
                                loadData(); // Refresh table
                            },
                            error: function (xhr) {
                                alert('Failed to delete warehouse.');
                                console.error(xhr.responseText);
                            }
                        });
                    }
                });
            }


            function deleteWarehouse(id) {
                $.ajax({
                    url: `/warehouses/${id}`,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        loadData(); // Refresh table
                    },
                    error: function () {
                        alert("Failed to delete warehouse.");
                    }
                });
            }


            function validateProductForm() {
                let isValid = true;
                const requiredFields = ['labelQty', 'cps', 'ship', 'wtAct', 'wtDecl', 'w', 'l', 'h'];

                requiredFields.forEach(id => {
                    const field = document.getElementById(id);
                    if (!field.value.trim()) {
                        showFieldError(field, 'This field is required');
                        isValid = false;
                    } else if (isNaN(field.value)) {
                        showFieldError(field, 'Must be a number');
                        isValid = false;
                    } else {
                        clearFieldError(field);
                    }
                });

                return isValid;
            }

            function getFormData() {
                return {
                    SKU: document.getElementById('sku').value,
                    Parent: document.getElementById('parent').value || '',
                    Label_QTY: document.getElementById('labelQty').value,
                    CP: document.getElementById('cps').value,
                    SHIP: document.getElementById('ship').value,
                    WT_ACT: document.getElementById('wtAct').value,
                    WT_DECL: document.getElementById('wtDecl').value,
                    W: document.getElementById('w').value,
                    L: document.getElementById('l').value,
                    H: document.getElementById('h').value,
                    '5C': document.getElementById('l2Url').value || '',
                    pcbox: document.getElementById('pcbox').value || '',
                    l1: document.getElementById('l1').value || '',
                    b: document.getElementById('b').value || '',
                    h1: document.getElementById('h1').value || '',
                    UPC: document.getElementById('upc').value || ''
                };
            }

            async function saveProduct(formData) {
                try {
                    const sheets = [{
                            name: 'ProductMaster',
                            displayName: 'Product Master',
                            id: 'product-master'
                        },
                        {
                            name: 'Amazon',
                            displayName: 'Amazon',
                            id: 'amazon'
                        },
                        {
                            name: 'Ebay',
                            displayName: 'Ebay',
                            id: 'ebay'
                        },
                        {
                            name: 'ShopifyB2C',
                            displayName: 'Shopify B2C',
                            id: 'shopifyb2c'
                        },
                        {
                            name: 'Mecy',
                            displayName: 'Mecy',
                            id: 'mecy'
                        },
                        {
                            name: 'NeweggB2C',
                            displayName: 'Newegg B2C',
                            id: 'neweggb2c'
                        }
                    ];

                    showUploadProgress(sheets);
                    const saveBtn = document.getElementById('saveProductBtn');
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = formData.operation === 'update' ?
                        '<i class="fas fa-spinner fa-spin me-2"></i> Updating...' :
                        '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

                    currentUpload = new AbortController();
                    const response = await fetch('/api/sync-sheets', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(formData),
                        signal: currentUpload.signal
                    });

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const textResponse = await response.text();
                        throw new Error('Server returned an HTML error page. Please check the server logs.');
                    }

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || `Server returned status ${response.status}`);
                    }

                    let successCount = 0;
                    sheets.forEach(sheet => {
                        const result = data.results[sheet.name];
                        if (result?.success) {
                            updateUploadProgress(sheet.id, 100, 'Completed successfully', true);
                            successCount++;
                        } else {
                            updateUploadProgress(sheet.id, 100, 'Failed', false, result?.message);
                        }
                    });

                    completeUpload(successCount, sheets.length);

                    if (successCount === sheets.length) {
                        showAlert('success', 'All sheets updated successfully!');
                        return true;
                    } else {
                        showAlert('warning', `${successCount}/${sheets.length} sheets updated successfully`);
                        return false;
                    }
                } catch (error) {
                    let errorMessage = error.message;
                    if (error.name === 'AbortError') {
                        errorMessage = 'Request was cancelled';
                    } else if (error.message.includes('HTML error page')) {
                        errorMessage = 'Server error occurred. Please try again or contact support.';
                    }

                    showAlert('danger', errorMessage);
                    updateUploadProgress('product-master', 100, 'Failed', false, errorMessage);
                    completeUpload(0, 1);
                    return false;
                } finally {
                    currentUpload = null;
                    const saveBtn = document.getElementById('saveProductBtn');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = formData.operation === 'update' ?
                        '<i class="fas fa-save me-2"></i> Update Product' :
                        '<i class="fas fa-save me-2"></i> Save Product';
                }
            }

            function resetProductForm() {
                document.getElementById('stockBalanceForm').reset();

                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                    const feedback = el.closest('.form-group')?.querySelector('.invalid-feedback');
                    if (feedback) feedback.textContent = '';
                });
                document.getElementById('form-errors').innerHTML = '';

                const saveBtn = document.getElementById('saveProductBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

                newSaveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Save Product';
                newSaveBtn.onclick = async function() {
                    if (!validateProductForm()) return;

                    const formData = getFormData();
                    formData.operation = 'create';

                    // Display the data being sent to the server
                    console.log('Data being sent to server:\n' + JSON.stringify(formData, null, 2));

                    const success = await saveProduct(formData);
                    if (success) {
                        bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                        loadData();
                    }
                };

                newSaveBtn.removeAttribute('data-original-sku');
                newSaveBtn.removeAttribute('data-original-parent');
            }


            function editProduct(product) {
                const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
                const saveBtn = document.getElementById('saveProductBtn');

                $(saveBtn).off('click');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);

                newSaveBtn.setAttribute('data-original-sku', product.SKU || '');
                newSaveBtn.setAttribute('data-original-parent', product.Parent || '');

                newSaveBtn.innerHTML = '<i class="fas fa-save me-2"></i> Update Product';
                newSaveBtn.addEventListener('click', async function handleUpdate() {
                    if (!validateProductForm()) return;

                    const formData = getFormData();
                    formData.operation = 'update';
                    formData.original_sku = newSaveBtn.getAttribute('data-original-sku');
                    formData.original_parent = newSaveBtn.getAttribute('data-original-parent');

                    // Display the data being sent to the server
                    console.log('Data being sent to server:\n' + JSON.stringify(formData, null, 2));

                    const success = await saveProduct(formData);
                    if (success) {
                        bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                        loadData();
                        resetProductForm();
                    }
                });

                const fields = {
                    sku: product.SKU || '',
                    parent: product.Parent || '',
                    labelQty: product['Label QTY'] || '1',
                    cps: product.CP || '',
                    ship: product.SHIP || '',
                    wtAct: product['WT ACT'] || product.weight_actual || '',
                    wtDecl: product['WT DECL'] || product.WT_DECL || product.wt_decl || product
                        .weight_declared || '',
                    w: product.W || product.width || product.Width || product.product_width || '',
                    l: product.L || product.length || item.Length || product.product_length || '',
                    h: product.H || product.height || product.product_height || '',
                    l2Url: product['5C'] || '',
                    pcbox: product.pcbox || '',
                    l1: product.l1 || '',
                    b: product.b || '',
                    h1: product.h1 || '',
                    upc: product.upc || ''
                };

                Object.entries(fields).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.value = value;
                });

                calculateCBM();
                calculateLP();
                modal.show();
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function formatNumber(num, decimals) {
                if (num === undefined || num === null) return '-';
                const n = parseFloat(num);
                return isNaN(n) ? '-' : n.toFixed(decimals);
            }

            function debounce(func, wait) {
                let timeout;
                return function() {
                    const context = this,
                        args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), wait);
                };
            }

            function showError(message) {
                document.getElementById('rainbow-loader').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                `;
            }

            function showAlert(type, message) {
                const alert = document.createElement('div');
                alert.className = `alert alert-${type} alert-dismissible fade show`;
                alert.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;

                const container = document.getElementById('form-errors');
                container.innerHTML = '';
                container.appendChild(alert);
            }

            function showFieldError(field, message) {
                const formGroup = field.closest('.form-group');
                if (!formGroup) return;

                let errorElement = formGroup.querySelector('.invalid-feedback');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'invalid-feedback';
                    formGroup.appendChild(errorElement);
                }

                field.classList.add('is-invalid');
                errorElement.textContent = message;
            }

            function clearFieldError(field) {
                const formGroup = field.closest('.form-group');
                if (!formGroup) return;

                const errorElement = formGroup.querySelector('.invalid-feedback');
                if (errorElement) {
                    field.classList.remove('is-invalid');
                    errorElement.textContent = '';
                }
            }

            // Large Error Alert Display (matching adjustment page)
            function showLargeErrorAlert(message, details = '') {
                // Remove any existing error alerts
                $('.error-overlay, .error-alert-large').remove();
                
                // Create overlay
                const overlay = $('<div class="error-overlay"></div>');
                
                // Create error alert
                const errorAlert = $(`
                    <div class="error-alert-large">
                        <div class="error-title">
                            <i class="fas fa-exclamation-circle"></i> ERROR
                        </div>
                        <div class="error-message">
                            ${message}
                        </div>
                        ${details ? `<div class="error-details">${details}</div>` : ''}
                        <button class="btn-close-error">
                            <i class="fas fa-times-circle"></i> CLOSE
                        </button>
                    </div>
                `);
                
                // Add to body
                $('body').append(overlay).append(errorAlert);
                
                // Close on button click
                errorAlert.find('.btn-close-error').on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    errorAlert.fadeOut(300, function() { $(this).remove(); });
                });
                
                // Close on overlay click
                overlay.on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    errorAlert.fadeOut(300, function() { $(this).remove(); });
                });
                
                // Auto-play error sound if available
                try {
                    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSmH0fPTgjMGHm7A7+OZURI7k9nzwoQtBCF+0PDZjjwIGGS57OmjVxEIQ5zg8bhjHAU8kdXzyoIvBCN70vDekj8JGWi67Oqe'); 
                    audio.volume = 0.3;
                    audio.play().catch(() => {});
                } catch(e) {}
            }

            // Success Alert Display (matching adjustment page)
            function showSuccessAlert(message) {
                // Remove any existing alerts
                $('.error-overlay, .error-alert-large, .success-alert-large').remove();
                
                // Create success alert (reusing error-alert-large style with green color)
                const successAlert = $(`
                    <div class="error-alert-large" style="border-color: #28a745;">
                        <div class="error-title" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i> SUCCESS
                        </div>
                        <div class="error-message" style="color: #155724;">
                            ${message}
                        </div>
                        <button class="btn-close-error" style="background: #28a745;">
                            <i class="fas fa-check-circle"></i> CLOSE
                        </button>
                    </div>
                `);
                
                const overlay = $('<div class="error-overlay" style="background: rgba(0, 0, 0, 0.5);"></div>');
                
                // Add to body
                $('body').append(overlay).append(successAlert);
                
                // Close on button click
                successAlert.find('.btn-close-error').on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    successAlert.fadeOut(300, function() { $(this).remove(); });
                });
                
                // Close on overlay click
                overlay.on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    successAlert.fadeOut(300, function() { $(this).remove(); });
                });
                
                // Auto close after 3 seconds
                setTimeout(function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    successAlert.fadeOut(300, function() { $(this).remove(); });
                }, 3000);
            }

            initializeTable();
        });
    </script>

@endsection
