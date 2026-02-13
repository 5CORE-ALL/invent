@extends('layouts.vertical', ['title' => 'Stock Adjustment', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        'page_title' => 'Stock Adjustment Inventory',
        'sub_title' => 'Stock Adjustment',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">


                    <!-- Search Box and Add Button-->
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <button type="button" class="btn btn-primary" id="openAddWarehouseModal" data-bs-toggle="modal" data-bs-target="#addWarehouseModal">
                                <i class="fas fa-plus me-1"></i> CREATE STOCK ADJUSTMENT
                            </button>
                            <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#bulkCSVModal">
                                <i class="fas fa-file-csv me-1"></i> CREATE STOCK ADJUSTMENT BULK
                            </button>
                            <div class="dataTables_length ms-3"></div>
                        </div>

                        <div class="col-md-3 offset-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control" placeholder="Search Stock Adjustment">
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

                    <!-- Stock Adjustment Modal -->
                    <div class="modal fade" id="addWarehouseModal" tabindex="-1" aria-labelledby="incomingModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <form id="stockAdjustmentForm">
                                @csrf
                                <div class="modal-content">

                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="incomingModalLabel">Add Stock Adjustment</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <div id="incoming-errors" class="mb-2 text-danger"></div>

                                        <!-- SKU Dropdown -->
                                        <div class="mb-3">
                                            <label for="sku" class="form-label fw-bold">SKU</label>
                                            <select class="form-select" id="sku" name="sku" required>
                                                <option selected disabled>Select SKU</option>
                                                @foreach($skus as $item)
                                                    <option value="{{ $item->sku }}" data-parent="{{ $item->parent }}">{{ $item->sku }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Auto-filled Parent -->
                                        <div class="mb-3">
                                            <label for="parent" class="form-label fw-bold">Parent</label>
                                            <input type="text" class="form-control" id="parent" name="parent" readonly>
                                        </div>

                                        <!-- Qty -->
                                        <div class="mb-3">
                                            <label for="qty" class="form-label fw-bold">Quantity</label>
                                            <input type="number" class="form-control" id="qty" name="qty" required>
                                        </div>

                                        <!-- Warehouse Dropdown -->
                                        <div class="mb-3">
                                            <label for="warehouse_id" class="form-label fw-bold">Warehouse</label>
                                            <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                                                <option selected disabled>Select Warehouse</option>
                                                @foreach($warehouses as $warehouse)
                                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Adjsutment -->
                                        <div class="mb-3">
                                            <label for="adjustment" class="form-label fw-bold">Adjustment</label>
                                            <select class="form-select" id="adjustment" name="adjustment" required>
                                                <option selected disabled>Select Adjustment</option>
                                                <option value="Add">Add</option>
                                                <option value="Reduce">Reduce</option>
                                            </select>
                                        </div>

                                        <!-- Reason Dropdown -->
                                        <div class="mb-3">
                                            <label for="reason" class="form-label fw-bold">Reason</label>
                                            <select class="form-select" id="reason" name="reason" required>
                                                <option selected disabled>Select Reason</option>
                                                <option value="Container Shortfall">Container Shortfall</option>
                                                <option value="Container Excess">Container Excess</option>
                                                <option value="Container Damaged">Container Damaged</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>

                                        <!-- Auto Date -->
                                        <div class="mb-3">
                                            <label for="date" class="form-label fw-bold">Date</label>
                                            <input type="text" class="form-control" id="date" name="date" readonly>
                                        </div>
                                    </div>

                                    <!-- type -->
                                    <input type="hidden" name="type" value="adjustment"> 

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success" id="saveStockAdjustmentBtn">Save Stock Adjustment</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


                    <!-- Bulk CSV Upload Modal -->
                    <div class="modal fade" id="bulkCSVModal" tabindex="-1" aria-labelledby="bulkCSVModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="bulkCSVModalLabel">
                                        <i class="fas fa-file-csv me-2"></i>Create Stock Adjustment - Bulk CSV Upload
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <!-- Step 1: Upload CSV -->
                                    <div class="mb-4">
                                        <h6><i class="fas fa-cloud-upload-alt me-1"></i> Step 1: Upload CSV File</h6>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="mb-3">
                                                <input type="file" class="form-control" id="bulkCSVFile" accept=".csv,.txt">
                                                <div id="csvFileInfo" class="mt-2" style="display: none;">
                                                    <span class="badge bg-info"><i class="fas fa-file-csv"></i> <span id="csvFileName"></span></span>
                                                    <button type="button" class="btn btn-sm btn-link text-danger" onclick="clearBulkCSVFile()">
                                                        <i class="fas fa-times"></i> Remove
                                                    </button>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-primary" id="processBulkCSVBtn" disabled>
                                                <i class="fas fa-upload me-1"></i> Process CSV
                                            </button>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <strong>CSV Format:</strong> Required columns: <code>SKU</code> and <code>QUANTITY</code>
                                                    <br>Optional columns: <code>WAREHOUSE</code>, <code>ADJUSTMENT</code>, <code>REASON</code>
                                                    <br><em>CREATED BY and DATE will be automatically set by the system</em>
                                                    <br><strong>Example:</strong>
                                                    <pre class="mt-1 p-2 bg-white border" style="font-size: 10px;">SKU,QUANTITY,WAREHOUSE,ADJUSTMENT,REASON
CAPO GLD,1,Main Godawn,Reduce,Container Damaged
MS RBP5 2PCS,100,Main Godawn,Add,Other</pre>
                                                    <a href="{{ asset('templates/BULK_ADJUSTMENT_TEMPLATE.csv') }}" class="btn btn-sm btn-outline-primary mt-1" download>
                                                        <i class="fas fa-download me-1"></i> Download Template
                                                    </a>
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2: Preview & Configure -->
                                    <div id="bulkPreviewSection" style="display: none;">
                                        <hr>
                                        <h6><i class="fas fa-cog me-1"></i> Step 2: Configure Adjustment Details</h6>
                                        
                                        <div id="bulkDefaultFieldsSection" class="row mb-3">
                                            <div class="col-md-6" id="bulk_warehouse_container">
                                                <label for="bulk_warehouse_id" class="form-label fw-bold">
                                                    Default Warehouse <span class="text-danger">*</span>
                                                    <small class="text-muted">(for rows without warehouse in CSV)</small>
                                                </label>
                                                <select class="form-select" id="bulk_warehouse_id">
                                                    <option value="" selected>Select Warehouse</option>
                                                    @foreach($warehouses as $warehouse)
                                                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6" id="bulk_reason_container">
                                                <label for="bulk_reason" class="form-label fw-bold">
                                                    Default Reason <span class="text-danger">*</span>
                                                    <small class="text-muted">(for rows without reason in CSV)</small>
                                                </label>
                                                <input type="text" class="form-control" id="bulk_reason" 
                                                       placeholder="e.g., Bulk adjustment from CSV import">
                                            </div>
                                        </div>

                                        <h6><i class="fas fa-list me-1"></i> Preview (First 20 rows)</h6>
                                        <div id="bulkPreviewContent"></div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-success" id="submitBulkBtn" disabled>
                                        <i class="fas fa-save me-1"></i> Create Adjustments
                                    </button>
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
                                    <th>SKU</th>
                                    <th>QUANTITY</th>
                                    <th>WAREHOUSE</th>
                                    <th>ADJUSTMENT</th>
                                    <th>REASON</th>
                                    <th>CREATED BY</th>
                                    <th>DATE</th>
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

                // Helper function for retrying AJAX calls with exponential backoff
                function ajaxWithRetry(options, maxRetries = 2) {
                    return new Promise((resolve, reject) => {
                        let attempt = 0;
                        
                        function makeRequest() {
                            attempt++;
                            
                            $.ajax({
                                ...options,
                                timeout: 15000, // 15 second timeout per request
                                success: function(response) {
                                    resolve(response);
                                },
                                error: function(xhr) {
                                    // Timeout error
                                    if (xhr.statusText === 'timeout') {
                                        if (attempt < maxRetries) {
                                            const waitTime = attempt * 2; // 2, 4 seconds
                                            console.log(`Request timeout. Retrying in ${waitTime}s (attempt ${attempt}/${maxRetries})`);
                                            showRateLimitMessage(waitTime, attempt, maxRetries, 'Timeout - Retrying');
                                            
                                            setTimeout(() => {
                                                makeRequest();
                                            }, waitTime * 1000);
                                        } else {
                                            reject(xhr);
                                        }
                                        return;
                                    }
                                    
                                    // Only retry on 429 (rate limit) errors
                                    if (xhr.status === 429 && attempt < maxRetries) {
                                        const waitTime = attempt * 2; // 2, 4 seconds
                                        console.log(`Rate limited (429). Retrying in ${waitTime}s (attempt ${attempt}/${maxRetries})`);
                                        
                                        // Show a progress message to user
                                        showRateLimitMessage(waitTime, attempt, maxRetries);
                                        
                                        setTimeout(() => {
                                            makeRequest();
                                        }, waitTime * 1000);
                                    } else {
                                        reject(xhr);
                                    }
                                }
                            });
                        }
                        
                        makeRequest();
                    });
                }
                
                // Show rate limit message to user
                function showRateLimitMessage(waitSeconds, attempt, maxRetries, messageType = 'Rate Limit') {
                    const progressContainer = document.getElementById('progress-container');
                    if (!progressContainer) return;
                    
                    let message = document.getElementById('rate-limit-message');
                    if (!message) {
                        message = document.createElement('div');
                        message.id = 'rate-limit-message';
                        message.className = 'alert alert-warning';
                        progressContainer.appendChild(message);
                    }
                    
                    message.innerHTML = `
                        <i class="fas fa-clock me-2"></i>
                        <strong>Shopify ${messageType}:</strong> Retrying in ${waitSeconds}s... 
                        (Attempt ${attempt}/${maxRetries})
                    `;
                }

                $('#stockAdjustmentForm').on('submit', function (e) {
                    e.preventDefault();

                    const formData = $(this).serialize();
                    const $submitBtn = $('#saveStockAdjustmentBtn');
                    const originalText = $submitBtn.text();
                    const sku = $('#sku option:selected').text();
                    
                    // Show loading state
                    $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

                    // Use retry logic for the AJAX call with timeout handling
                    ajaxWithRetry({
                        url: '{{ route("stock.adjustment.store") }}',
                        method: 'POST',
                        data: formData
                    }, 2).then(function (response) {
                        // Success
                        $submitBtn.prop('disabled', false).text(originalText);
                        $('#addWarehouseModal').modal('hide');
                        loadData(); // Reload after store
                        $('#stockAdjustmentForm')[0].reset();
                        
                        // Show success message with more details
                        showSuccessAlert(
                            'Stock adjustment completed successfully for ' + sku + '!<br>' +
                            'New quantity: ' + (response.new_stock_level || 'Updated')
                        );
                    }).catch(function (xhr) {
                        // Error
                        $submitBtn.prop('disabled', false).text(originalText);
                        console.log('Full Error Response:', xhr);
                        
                        let errorMessage = 'Error storing stock adjustment.';
                        let errorDetails = '';
                        
                        if (xhr.statusText === 'timeout' || xhr.status === 504) {
                            errorMessage = 'Request Timeout';
                            errorDetails = 'The request took too long. Please try again. Your SKU data may have already been updated.';
                        } else if (xhr.responseJSON && xhr.responseJSON.error) {
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
                            errorMessage = 'Shopify API Rate Limit';
                            errorDetails = 'Too many requests to Shopify. Please wait a moment and try again.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server Error';
                            errorDetails = xhr.responseJSON?.details || xhr.responseJSON?.message || 'Please try again or contact support.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Connection Failed';
                            errorDetails = 'Unable to connect to the server. Please check your internet connection and try again.';
                        }
                        
                        // Add SKU info to error details
                        if (sku) {
                            errorDetails += (errorDetails ? '<br><br>' : '') + '<strong>SKU Attempted:</strong> ' + sku;
                        }
                        
                        showLargeErrorAlert(errorMessage, errorDetails);
                    });
                });

                $('#sku').select2({
                    dropdownParent: $('#addWarehouseModal'),
                    placeholder: "Select SKU",
                    allowClear: true
                });
                
                // Auto-fill Parent when select sku
                $('#sku').on('change', function () {
                    const parent = $(this).find('option:selected').data('parent');
                    $('#parent').val(parent || '');
                });

               

               
                $(document).on('click', '#openAddWarehouseModal', function () {
                    $('#stockAdjustmentForm')[0].reset(); // Only resets for add
                    $('#warehouseId').val('');
                    $('#warehouseModalLabel').text('Create Stock Adjustment');
                    // $('#warehouseGroup').val('').trigger('change');

                    // Auto-fill PO number and date when modal is shown
                    // const today = new Date();
                    // const formattedDate = `${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}-${today.getFullYear()}`;
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
                    url: '/stock-adjustment-data-list',
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
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center">No records found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');

                    row.innerHTML = `
                        <td>${item.sku || '-'}</td>
                        <td>${item.verified_stock || '-'}</td>
                        <td>${item.warehouse_name  || '-'}</td>
                        <td>${item.adjustment || '-'}</td>
                        <td>${item.reason || '-'}</td>
                        <td>${item.approved_by || '-'}</td>
                        <td>${item.approved_at || '-'}</td>
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
                document.getElementById('stockAdjustmentForm').reset();

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

            // Large Error Alert Display
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

            // Success Alert Display
            function showSuccessAlert(message) {
                // Remove any existing alerts
                $('.error-overlay, .error-alert-large, .success-alert-large').remove();
                
                const successAlert = $(`
                    <div class="error-alert-large" style="border-color: #28a745;">
                        <div class="error-title" style="color: #28a745;">
                            <i class="fas fa-check-circle"></i> SUCCESS
                        </div>
                        <div class="error-message" style="color: #28a745;">
                            ${message}
                        </div>
                        <button class="btn-close-error" style="background: #28a745;">
                            <i class="fas fa-check"></i> OK
                        </button>
                    </div>
                `);
                
                const overlay = $('<div class="error-overlay" style="background: rgba(0, 0, 0, 0.5);"></div>');
                
                $('body').append(overlay).append(successAlert);
                
                successAlert.find('.btn-close-error').on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    successAlert.fadeOut(300, function() { $(this).remove(); });
                });
                
                overlay.on('click', function() {
                    overlay.fadeOut(300, function() { $(this).remove(); });
                    successAlert.fadeOut(300, function() { $(this).remove(); });
                });
            }

            initializeTable();
        });

        // ========== BULK CSV UPLOAD FUNCTIONALITY ==========
        
        let bulkCSVData = [];
        let bulkCSVErrors = [];

        $('#bulkCSVFile').on('change', function() {
            const file = this.files[0];
            if (file) {
                $('#csvFileName').text(file.name);
                $('#csvFileInfo').show();
                $('#processBulkCSVBtn').prop('disabled', false);
            }
        });

        function clearBulkCSVFile() {
            $('#bulkCSVFile').val('');
            $('#csvFileInfo').hide();
            $('#processBulkCSVBtn').prop('disabled', true);
            $('#bulkPreviewSection').hide();
            bulkCSVData = [];
            bulkCSVErrors = [];
        }

        $('#processBulkCSVBtn').on('click', function() {
            const formData = new FormData();
            const file = $('#bulkCSVFile')[0].files[0];
            
            if (!file) {
                alert('Please select a CSV file');
                return;
            }

            formData.append('csv_file', file);

            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Processing...');

            $.ajax({
                url: '{{ route("stock.adjustment.bulk-csv") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        bulkCSVData = response.data;
                        bulkCSVErrors = response.errors || [];
                        
                        displayBulkPreview(response);
                        $('#processBulkCSVBtn').prop('disabled', false).html('<i class="fas fa-check me-1"></i> Process CSV');
                    } else {
                        alert('Error: ' + response.message);
                        $('#processBulkCSVBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Process CSV');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Error processing CSV';
                    alert('Error: ' + message);
                    $('#processBulkCSVBtn').prop('disabled', false).html('<i class="fas fa-upload me-1"></i> Process CSV');
                }
            });
        });

        function displayBulkPreview(response) {
            // Check if CSV has warehouse and reason columns
            const hasWarehouse = bulkCSVData.some(item => item.warehouse_id);
            const hasReason = bulkCSVData.some(item => item.reason);

            // Show/hide default fields based on CSV content
            if (hasWarehouse) {
                $('#bulk_warehouse_container').hide();
            } else {
                $('#bulk_warehouse_container').show();
            }

            if (hasReason) {
                $('#bulk_reason_container').hide();
            } else {
                $('#bulk_reason_container').show();
            }

            // Show appropriate message
            let alertMessage = '';
            if (hasWarehouse && hasReason) {
                alertMessage = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i><strong>All details provided in CSV!</strong> Warehouse and Reason from CSV will be used. CREATED BY and DATE will be set automatically.</div>';
            } else if (hasWarehouse) {
                alertMessage = '<div class="alert alert-warning"><i class="fas fa-info-circle me-1"></i><strong>Note:</strong> Warehouse from CSV will be used. Please provide default Reason below.</div>';
            } else if (hasReason) {
                alertMessage = '<div class="alert alert-warning"><i class="fas fa-info-circle me-1"></i><strong>Note:</strong> Reason from CSV will be used. Please provide default Warehouse below.</div>';
            } else {
                alertMessage = '<div class="alert alert-warning"><i class="fas fa-info-circle me-1"></i><strong>Note:</strong> Please provide default Warehouse and Reason below (not found in CSV).</div>';
            }

            let html = `
                <div class="alert alert-info">
                    <strong>CSV Processed:</strong> ${response.valid_rows} valid rows, ${response.error_rows} errors
                </div>
                ${alertMessage}`;

            if (bulkCSVErrors.length > 0) {
                html += `<div class="alert alert-danger">
                    <strong>Errors Found:</strong><br>
                    ${bulkCSVErrors.slice(0, 5).join('<br>')}
                    ${bulkCSVErrors.length > 5 ? '<br><em>...and ' + (bulkCSVErrors.length - 5) + ' more</em>' : ''}
                </div>`;
            }

            html += `
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-primary" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th>SKU</th>
                                <th>QUANTITY</th>
                                <th>WAREHOUSE</th>
                                <th>ADJUSTMENT</th>
                                <th>REASON</th>
                            </tr>
                        </thead>
                        <tbody>`;

            bulkCSVData.slice(0, 20).forEach(function(item) {
                const warehouseDisplay = item.warehouse_name || '<span class="text-muted">Will use selected</span>';
                const reasonDisplay = item.reason || '<span class="text-muted">Will use selected</span>';
                
                html += `
                    <tr>
                        <td><strong>${item.sku}</strong><br><small class="text-muted">${item.title}</small></td>
                        <td><span class="badge bg-primary">${item.quantity}</span></td>
                        <td>${warehouseDisplay}</td>
                        <td><span class="badge bg-${item.adjustment_type === 'Add' ? 'success' : 'danger'}">${item.adjustment_type}</span></td>
                        <td>${reasonDisplay}</td>
                    </tr>`;
            });

            if (bulkCSVData.length > 20) {
                html += `<tr><td colspan="5" class="text-center text-muted">...and ${bulkCSVData.length - 20} more rows</td></tr>`;
            }

            html += `</tbody></table></div>`;

            $('#bulkPreviewContent').html(html);
            $('#bulkPreviewSection').show();
            $('#submitBulkBtn').prop('disabled', false);
        }

        $('#submitBulkBtn').on('click', function() {
            if (bulkCSVData.length === 0) {
                alert('No data to process');
                return;
            }

            if (!confirm(`Are you sure you want to create ${bulkCSVData.length} stock adjustments?`)) {
                return;
            }

            const defaultWarehouse = $('#bulk_warehouse_id').val();
            const defaultReason = $('#bulk_reason').val();

            // Check if we need defaults
            const needsWarehouse = bulkCSVData.some(item => !item.warehouse_id);
            const needsReason = bulkCSVData.some(item => !item.reason);
            
            if (needsWarehouse && !defaultWarehouse) {
                alert('Please select default warehouse (some rows don\'t have warehouse in CSV)');
                return;
            }

            if (needsReason && !defaultReason) {
                alert('Please enter default reason (some rows don\'t have reason in CSV)');
                return;
            }

            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Creating...');

            // Show progress indicator
            const totalRows = bulkCSVData.length;
            $('#bulkPreviewContent').prepend(`
                <div id="bulkProgressBar" class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Processing: <span id="bulkProgressText">0 / ${totalRows}</span></span>
                        <span><span id="bulkProgressPercent">0</span>%</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             id="bulkProgressBarInner" 
                             role="progressbar" 
                             style="width: 0%">0%</div>
                    </div>
                </div>
            `);

            // Process each SKU
            let processed = 0;
            let failed = 0;
            let failedSkus = [];

            function updateProgress() {
                const current = processed + failed;
                const percent = Math.round((current / totalRows) * 100);
                $('#bulkProgressText').text(`${current} / ${totalRows}`);
                $('#bulkProgressPercent').text(percent);
                $('#bulkProgressBarInner').css('width', percent + '%').text(percent + '%');
                
                if (failed > 0) {
                    $('#bulkProgressBarInner').removeClass('bg-success').addClass('bg-warning');
                } else {
                    $('#bulkProgressBarInner').removeClass('bg-warning').addClass('bg-success');
                }
            }

            function processNextSKU(index) {
                if (index >= bulkCSVData.length) {
                    // All done - remove progress bar
                    $('#bulkProgressBar').remove();
                    
                    // Close modal
                    $('#bulkCSVModal').modal('hide');
                    clearBulkCSVFile();
                    
                    // Reload table data instantly without page refresh
                    if (typeof loadInventoryData === 'function') {
                        loadInventoryData();
                    } else if (window.inventoryTable) {
                        window.inventoryTable.ajax.reload(null, false);
                    }
                    
                    // Build detailed message
                    let messageHtml = `
                        <div class="text-center">
                            <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                            <h4 class="mt-3">Bulk Stock Adjustment Complete!</h4>
                            <p class="mb-2"><strong>Created:</strong> <span class="badge bg-success">${processed}</span></p>
                            <p class="mb-3"><strong>Failed:</strong> <span class="badge bg-danger">${failed}</span></p>`;
                    
                    if (failedSkus.length > 0) {
                        messageHtml += `
                            <div class="alert alert-warning text-start">
                                <strong>Failed SKUs:</strong><br>
                                ${failedSkus.slice(0, 10).join('<br>')}
                                ${failedSkus.length > 10 ? '<br><em>...and ' + (failedSkus.length - 10) + ' more</em>' : ''}
                            </div>`;
                    }
                    
                    messageHtml += `</div>`;
                    
                    // Show in existing success alert function or create modal
                    if (typeof showLargeSuccessAlert === 'function') {
                        showLargeSuccessAlert('Bulk Processing Complete', messageHtml);
                    } else {
                        // Fallback to standard alert
                        alert(`Bulk stock adjustment complete!\n\nCreated: ${processed}\nFailed: ${failed}${failedSkus.length > 0 ? '\n\nFailed SKUs:\n' + failedSkus.slice(0, 5).join('\n') : ''}`);
                    }
                    
                    $('#submitBulkBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i> Create Adjustments');
                    return;
                }

                const item = bulkCSVData[index];
                
                // Use CSV values if provided, otherwise use defaults
                const warehouseId = item.warehouse_id || defaultWarehouse;
                const reason = item.reason || defaultReason;
                const adjustmentType = item.adjustment_type || 'Add';
                const parent = item.parent || '';
                const today = new Date().toISOString().split('T')[0];

                $.ajax({
                    url: '{{ route("stock.adjustment.store") }}',
                    method: 'POST',
                    data: {
                        sku: item.sku,
                        parent: parent,
                        qty: Math.abs(item.quantity),
                        warehouse_id: warehouseId,
                        adjustment: adjustmentType,
                        reason: reason,
                        date: today,
                        type: 'adjustment',
                        is_approved: false
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function() {
                        processed++;
                        updateProgress();
                        processNextSKU(index + 1);
                    },
                    error: function(xhr) {
                        failed++;
                        const errorMsg = xhr.responseJSON?.error || xhr.responseJSON?.details || 'Error';
                        failedSkus.push(item.sku + ': ' + errorMsg);
                        updateProgress();
                        processNextSKU(index + 1);
                    }
                });
            }

            processNextSKU(0);
        });
    </script>

@endsection
