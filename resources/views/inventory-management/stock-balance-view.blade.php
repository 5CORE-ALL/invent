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
            font-weight: 700;
            /* Make header text bolder */
            font-size: 20px;
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
            font-weight: 700 !important;
            font-size: 20px !important;
        }

        /* Ensure DataTables sorting icons are visible */
        #inventoryTable thead th.sorting:after,
        #inventoryTable thead th.sorting_asc:after,
        #inventoryTable thead th.sorting_desc:after {
            color: white !important;
            opacity: 0.8 !important;
        }

        /* Inventory table header styling */
        #inventoryDataTable thead th {
            background-color: #2c6ed5 !important;
            color: white !important;
            font-weight: 700 !important;
            font-size: 20px !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 10 !important;
            padding: 12px 15px !important;
            border-bottom: 2px solid #1a56b7 !important;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1) !important;
        }

        #inventoryDataTable thead th:hover {
            background-color: #1a56b7 !important;
        }

        #inventoryDataTable tbody td {
            padding: 10px 15px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #e0e0e0 !important;
        }

        #inventoryDataTable tbody tr:nth-child(even) {
            background-color: #f8fafc !important;
        }

        #inventoryDataTable tbody tr:hover {
            background-color: #ebf2fb !important;
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

        /* Increase overall page font size for better readability */
        .card-body {
            font-size: 20px;
        }

        .card-body table tbody td {
            font-size: 20px;
        }

        .card-body .form-label {
            font-size: 20px;
        }

        .card-body .form-control,
        .card-body .form-select {
            font-size: 20px;
        }

        /* Make form section headers bolder */
        .modal-body h5 {
            font-weight: 700;
            font-size: 22px;
        }

        /* DIL% Color Rules - matching verification-adjustment view */
        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            background-color: #dc3545;
            color: white;
        }

        .dil-percent-value.yellow {
            background-color: #ffc107;
            color: #212529;
        }

        .dil-percent-value.green {
            background-color: #28a745;
            color: white;
        }

        .dil-percent-value.pink {
            background-color: #e83e8c;
            color: white;
        }


        /* Two column layout adjustments */
        .col-md-6 {
            padding-right: 10px;
            padding-left: 10px;
        }

        #inventoryTableContainer {
            height: calc(100vh - 250px);
            max-height: calc(100vh - 250px);
        }

        /* Stock Balance Form Container Styling */
        .stock-balance-form-container {
            height: calc(100vh - 250px);
            max-height: calc(100vh - 250px);
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .stock-balance-form-header {
            background-color: #2c6ed5;
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #1a56b7;
            font-weight: 700;
            font-size: 20px;
        }

        .stock-balance-form-header .btn-close {
            background-color: transparent;
            border: none;
            opacity: 1;
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .stock-balance-form-header .btn-close:hover {
            opacity: 0.8;
        }

        .stock-balance-form-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        /* Sortable column styles */
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .sortable:hover {
            background-color: #1a56b7 !important;
        }

        .sort-icon {
            font-size: 12px;
            margin-left: 5px;
            opacity: 0.7;
        }

        .sort-asc .sort-icon::after {
            content: ' ↑';
            opacity: 1;
            font-weight: bold;
        }

        .sort-desc .sort-icon::after {
            content: ' ↓';
            opacity: 1;
            font-weight: bold;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Stock Balance/TRF',
        'sub_title' => 'Stock Balance',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">


                    <!-- Search Box and Add Button-->
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center">
                            <button type="button" class="btn btn-primary" id="toggleStockBalanceForm">
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

                    <!-- History and Inventory Buttons -->
                    <div class="row mb-3">
                        <div class="col-12 d-flex align-items-center">
                            <button type="button" class="btn btn-secondary me-2" id="toggleHistoryBtn">
                                <i class="fas fa-history me-1"></i> Show History
                            </button>
                            <button type="button" class="btn btn-info me-2" id="toggleInventoryBtn">
                                <i class="fas fa-boxes me-1"></i> Hide Inventory
                            </button>
                            
                            <!-- Parent Navigation Controls -->
                            <div class="btn-group time-navigation-group ms-2" role="group" aria-label="Parent navigation">
                                <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle" title="Pause" style="display: none;">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-light rounded-circle" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Parent and SKU Filters (Outside Table) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="filterParent" class="form-label fw-bold">Filter by Parent</label>
                                    <input type="text" id="filterParent" class="form-control" placeholder="Enter Parent to filter">
                                </div>
                                <div class="col-md-6">
                                    <label for="filterSKU" class="form-label fw-bold">Filter by SKU</label>
                                    <input type="text" id="filterSKU" class="form-control" placeholder="Enter SKU to filter">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Two Column Layout -->
                    <div class="row">
                        <!-- Left Column: Inventory Table (50%) -->
                        <div class="col-md-6">
                            <div id="inventoryTableContainer" class="table-responsive mb-3">
                                <table id="inventoryDataTable" class="table dt-responsive nowrap w-100">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-column="img">IMG</th>
                                            <th class="sortable" data-column="parent">PARENT <span class="sort-icon">↕</span></th>
                                            <th class="sortable" data-column="sku">SKU <span class="sort-icon">↕</span></th>
                                            <th class="sortable" data-column="inv">INV <span class="sort-icon">↕</span></th>
                                            <th class="sortable" data-column="sold">SOLD <span class="sort-icon">↕</span></th>
                                            <th class="sortable" data-column="dil">DIL% <span class="sort-icon">↕</span></th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventory-data-table-body">
                                        <!-- Rows will be dynamically inserted here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Right Column: Stock Balance Form Container (50%) -->
                        <div class="col-md-6">
                            <div id="stockBalanceFormContainer" class="stock-balance-form-container" style="display: none;">
                                <div class="stock-balance-form-header">
                                    <h5 class="mb-0">Stock Balance</h5>
                                    <button type="button" class="btn-close" id="closeStockBalanceForm" aria-label="Close"></button>
                                </div>
                                <div class="stock-balance-form-body">
                                    <form id="stockBalanceForm">
                                        @csrf

                                        <div class="row">
                                            <!-- From Warehouse Section -->
                                            <div class="col-md-6 p-3">
                                                <h5><strong>From</strong></h5>

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
                                                    <label for="to_adjust_qty" class="form-label fw-bold">Qty Adj (From)</label>
                                                    <input type="number" id="to_adjust_qty" name="to_adjust_qty" class="form-control" min="1" required>
                                                </div>
                                            </div>

                                            <!-- To Warehouse Section -->
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
                                                    <label for="from_adjust_qty" class="form-label fw-bold">Qty Adj (To)</label>
                                                    <input type="number" id="from_adjust_qty" name="from_adjust_qty" class="form-control" min="1" required>
                                                    <small class="text-muted" id="from_qty_hint"></small>
                                                    <div class="text-danger" id="ratio_calculation_error" style="display: none; font-size: 14px; margin-top: 5px;"></div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12 p-3">
                                                <div class="mb-3">
                                                    <label for="ratio" class="form-label fw-bold">Ratio</label>
                                                    <select class="form-select" id="ratio" name="ratio">
                                                        <option value="1:4">1:4 ratio</option>
                                                        <option value="1:2">1:2 ratio</option>
                                                        <option value="1:1" selected>1:1 ratio</option>
                                                        <option value="2:1">2:1 ratio</option>
                                                        <option value="4:1">4:1 ratio</option>
                                                    </select>
                                                </div>
                                                <div class="mt-3 text-end">
                                                    <button type="submit" class="btn btn-success">Submit</button>
                                                </div>
                                            </div>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History DataTable (Hidden by default) -->
                    <div id="historyTableContainer" class="table-responsive" style="display: none;">
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

                // History button toggle functionality
                $('#toggleHistoryBtn').on('click', function() {
                    const tableContainer = $('#historyTableContainer');
                    const isVisible = tableContainer.is(':visible');
                    
                    if (isVisible) {
                        tableContainer.slideUp(300);
                        $(this).html('<i class="fas fa-history me-1"></i> Show History');
                    } else {
                        tableContainer.slideDown(300);
                        $(this).html('<i class="fas fa-history me-1"></i> History');
                    }
                });

                // Inventory button toggle functionality
                let inventoryData = [];
                let inventoryDataLoaded = false;

                // Parent Navigation System
                let currentParentIndex = -1; // -1 means showing all products
                let uniqueParents = [];
                let isNavigationActive = false;
                let filteredInventoryData = [];

                // Sorting System
                let currentSortColumn = null;
                let currentSortDirection = null; // 'asc' or 'desc'

                // Function to get DIL color based on value
                function getDilColor(value) {
                    const percent = parseFloat(value) * 100;
                    if (percent < 16.66) return 'red';
                    if (percent >= 16.66 && percent < 25) return 'yellow';
                    if (percent >= 25 && percent < 50) return 'green';
                    return 'pink';
                }

                // Load inventory data from API
                function loadInventoryData() {
                    $.ajax({
                        url: '/stock-balance-inventory-data',
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        beforeSend: function () {
                            $('#inventory-data-table-body').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
                        },
                        success: function (response) {
                            if (response && response.data) {
                                inventoryData = response.data;
                                inventoryDataLoaded = true;
                                // Initialize parent navigation
                                initPlaybackControls();
                                // Set up sorting handlers (in case DOM wasn't ready earlier)
                                setupSorting();
                                renderInventoryTable(inventoryData);
                            }
                        },
                        error: function(xhr) {
                            console.error("Load inventory error:", xhr.responseText);
                            $('#inventory-data-table-body').html('<tr><td colspan="6" class="text-center text-danger">Error loading inventory data</td></tr>');
                        }
                    });
                }

                // Sort data function
                function sortData(data, column, direction) {
                    if (!column) return data;
                    
                    const sorted = [...data].sort((a, b) => {
                        let aValue, bValue;
                        
                        switch(column) {
                            case 'img':
                                // Sort by SKU since images don't have sortable values
                                aValue = (a.SKU || '').toLowerCase();
                                bValue = (b.SKU || '').toLowerCase();
                                return direction === 'asc' 
                                    ? aValue.localeCompare(bValue)
                                    : bValue.localeCompare(aValue);
                            
                            case 'parent':
                                aValue = (a.Parent || '').toLowerCase();
                                bValue = (b.Parent || '').toLowerCase();
                                return direction === 'asc' 
                                    ? aValue.localeCompare(bValue)
                                    : bValue.localeCompare(aValue);
                            
                            case 'sku':
                                aValue = (a.SKU || '').toLowerCase();
                                bValue = (b.SKU || '').toLowerCase();
                                return direction === 'asc' 
                                    ? aValue.localeCompare(bValue)
                                    : bValue.localeCompare(aValue);
                            
                            case 'inv':
                                aValue = parseFloat(a.INV) || 0;
                                bValue = parseFloat(b.INV) || 0;
                                return direction === 'asc' ? aValue - bValue : bValue - aValue;
                            
                            case 'sold':
                                aValue = parseFloat(a.SOLD) || 0;
                                bValue = parseFloat(b.SOLD) || 0;
                                return direction === 'asc' ? aValue - bValue : bValue - aValue;
                            
                            case 'dil':
                                aValue = parseFloat(a.DIL) || 0;
                                bValue = parseFloat(b.DIL) || 0;
                                return direction === 'asc' ? aValue - bValue : bValue - aValue;
                            
                            default:
                                return 0;
                        }
                    });
                    
                    return sorted;
                }

                // Render inventory table
                function renderInventoryTable(data) {
                    const tbody = document.getElementById('inventory-data-table-body');
                    tbody.innerHTML = '';

                    // Get filter values
                    const filterParent = ($('#filterParent').val() || '').toLowerCase().trim();
                    const filterSKU = ($('#filterSKU').val() || '').toLowerCase().trim();

                    // Filter out rows with "parent" in SKU string (case-insensitive)
                    let filteredData = data.filter(item => {
                        const sku = (item.SKU || '').toLowerCase();
                        return !sku.includes('parent');
                    });

                    // Apply Parent and SKU filters
                    if (filterParent || filterSKU) {
                        filteredData = filteredData.filter(item => {
                            const itemParent = (item.Parent || '').toLowerCase();
                            const itemSKU = (item.SKU || '').toLowerCase();
                            const matchesParent = !filterParent || itemParent.includes(filterParent);
                            const matchesSKU = !filterSKU || itemSKU.includes(filterSKU);
                            return matchesParent && matchesSKU;
                        });
                    }

                    // Use filtered data if navigation is active, otherwise use all data
                    let dataToRender = isNavigationActive && filteredInventoryData.length > 0 
                        ? filteredInventoryData.filter(item => {
                            const sku = (item.SKU || '').toLowerCase();
                            const itemParent = (item.Parent || '').toLowerCase();
                            const itemSKU = (item.SKU || '').toLowerCase();
                            const matchesParent = !filterParent || itemParent.includes(filterParent);
                            const matchesSKU = !filterSKU || itemSKU.includes(filterSKU);
                            return !sku.includes('parent') && matchesParent && matchesSKU;
                        })
                        : filteredData;

                    // Apply sorting if a column is selected
                    if (currentSortColumn && currentSortDirection) {
                        dataToRender = sortData(dataToRender, currentSortColumn, currentSortDirection);
                    }

                    if (dataToRender.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No records found</td></tr>';
                        return;
                    }

                    dataToRender.forEach(item => {
                        const row = document.createElement('tr');
                        
                        // IMG
                        const imgCell = document.createElement('td');
                        imgCell.innerHTML = item.IMAGE_URL 
                            ? `<img src="${item.IMAGE_URL}" style="width:40px;height:auto;" alt="${item.SKU}">` 
                            : '';
                        row.appendChild(imgCell);

                        // PARENT
                        const parentCell = document.createElement('td');
                        parentCell.textContent = item.Parent || '-';
                        row.appendChild(parentCell);

                        // SKU with select button
                        const skuCell = document.createElement('td');
                        const skuContainer = document.createElement('div');
                        skuContainer.style.display = 'flex';
                        skuContainer.style.alignItems = 'center';
                        skuContainer.style.gap = '8px';
                        
                        const skuText = document.createElement('span');
                        skuText.textContent = item.SKU || '-';
                        skuContainer.appendChild(skuText);
                        
                        // Small round button to select SKU
                        const selectBtn = document.createElement('button');
                        selectBtn.type = 'button';
                        selectBtn.className = 'btn btn-sm btn-primary rounded-circle p-0';
                        selectBtn.style.width = '20px';
                        selectBtn.style.height = '20px';
                        selectBtn.style.fontSize = '10px';
                        selectBtn.style.lineHeight = '1';
                        selectBtn.innerHTML = '<i class="fas fa-arrow-right"></i>';
                        selectBtn.title = 'Select as From SKU';
                        selectBtn.setAttribute('data-sku', item.SKU || '');
                        selectBtn.setAttribute('data-parent', item.Parent || '');
                        selectBtn.setAttribute('data-inv', item.INV || 0);
                        selectBtn.setAttribute('data-dil', item.DIL || 0);
                        
                        // Click handler to auto-select SKU in form
                        selectBtn.addEventListener('click', function() {
                            const sku = this.getAttribute('data-sku');
                            const parent = this.getAttribute('data-parent');
                            const inv = this.getAttribute('data-inv');
                            const dil = parseFloat(this.getAttribute('data-dil')) || 0;
                            
                            // Show stock balance form if hidden
                            const formContainer = $('#stockBalanceFormContainer');
                            if (!formContainer.is(':visible')) {
                                formContainer.slideDown(300);
                            }
                            
                            // Set the to_sku dropdown value (in FROM section)
                            $('#to_sku').val(sku).trigger('change');
                            
                            // Also set parent, available qty, and dil if not auto-filled
                            setTimeout(function() {
                                if ($('#to_parent_name').val() === '') {
                                    $('#to_parent_name').val(parent || '');
                                }
                                if ($('#to_available_qty').val() === '' || $('#to_available_qty').val() === '0') {
                                    $('#to_available_qty').val(inv || 0);
                                }
                                if ($('#to_dil_percent').val() === '' || $('#to_dil_percent').val() === '0') {
                                    const dilPercent = dil > 0 ? (dil * 100) : 0;
                                    $('#to_dil_percent').val(dilPercent > 100 ? 100 : dilPercent);
                                }
                            }, 100);
                        });
                        
                        skuContainer.appendChild(selectBtn);
                        skuCell.appendChild(skuContainer);
                        row.appendChild(skuCell);

                        // INV
                        const invCell = document.createElement('td');
                        invCell.textContent = item.INV || 0;
                        row.appendChild(invCell);

                        // SOLD
                        const soldCell = document.createElement('td');
                        soldCell.textContent = item.SOLD || 0;
                        row.appendChild(soldCell);

                        // DIL%
                        const dilCell = document.createElement('td');
                        const dilValue = parseFloat(item.DIL) || 0;
                        if (dilValue <= 0) {
                            dilCell.innerHTML = '<span>-</span>';
                        } else {
                            const dilPercent = Math.round(dilValue * 100);
                            const dilClass = getDilColor(dilValue);
                            dilCell.innerHTML = `<span class="dil-percent-value ${dilClass}">${dilPercent}%</span>`;
                        }
                        row.appendChild(dilCell);

                        tbody.appendChild(row);
                    });

                    // Update sort indicators in header
                    updateSortIndicators();
                }

                // Update sort indicators in table header
                function updateSortIndicators() {
                    $('.sortable').removeClass('sort-asc sort-desc');
                    if (currentSortColumn) {
                        const sortableHeader = $(`.sortable[data-column="${currentSortColumn}"]`);
                        sortableHeader.addClass(`sort-${currentSortDirection}`);
                    }
                }

                // Set up sort click handlers
                function setupSorting() {
                    $('.sortable').off('click').on('click', function() {
                        const column = $(this).data('column');
                        
                        // Toggle sort direction if clicking the same column, otherwise start with ascending
                        if (currentSortColumn === column) {
                            currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            currentSortColumn = column;
                            currentSortDirection = 'asc';
                        }
                        
                        // Re-render table with new sort
                        if (inventoryDataLoaded) {
                            renderInventoryTable(inventoryData);
                        }
                    });
                }

                // Parent Navigation Functions
                function initPlaybackControls() {
                    // Get all unique parent ASINs
                    uniqueParents = [...new Set(inventoryData.map(item => item.Parent))].filter(p => p && p !== '(No Parent)');
                    
                    // Set up event handlers
                    $('#play-forward').off('click').on('click', nextParent);
                    $('#play-backward').off('click').on('click', previousParent);
                    $('#play-pause').off('click').on('click', stopNavigation);
                    $('#play-auto').off('click').on('click', startNavigation);
                    
                    // Initialize button states
                    updateButtonStates();
                }

                function startNavigation() {
                    if (uniqueParents.length === 0) return;
                    
                    isNavigationActive = true;
                    currentParentIndex = 0;
                    showCurrentParent();
                    
                    // Update button visibility
                    $('#play-auto').hide();
                    $('#play-pause').show().removeClass('btn-light');
                }

                function stopNavigation() {
                    isNavigationActive = false;
                    currentParentIndex = -1;
                    
                    // Update button visibility and reset color
                    $('#play-pause').hide();
                    $('#play-auto').show()
                        .removeClass('btn-success btn-warning btn-danger')
                        .addClass('btn-light');
                    
                    // Show all products
                    filteredInventoryData = [];
                    renderInventoryTable(inventoryData);
                    updateButtonStates();
                }

                function nextParent() {
                    if (!isNavigationActive) return;
                    if (currentParentIndex >= uniqueParents.length - 1) return;
                    
                    currentParentIndex++;
                    showCurrentParent();
                }

                function previousParent() {
                    if (!isNavigationActive) return;
                    if (currentParentIndex <= 0) return;
                    
                    currentParentIndex--;
                    showCurrentParent();
                }

                function showCurrentParent() {
                    if (!isNavigationActive || currentParentIndex === -1) return;
                    
                    // Filter data to show only current parent's products, excluding rows with "parent" in SKU
                    filteredInventoryData = inventoryData.filter(item => {
                        const sku = (item.SKU || '').toLowerCase();
                        return item.Parent === uniqueParents[currentParentIndex] && !sku.includes('parent');
                    });
                    
                    // Update UI
                    renderInventoryTable(inventoryData);
                    updateButtonStates();
                }

                function updateButtonStates() {
                    // Enable/disable navigation buttons based on position
                    $('#play-backward').prop('disabled', !isNavigationActive || currentParentIndex <= 0);
                    $('#play-forward').prop('disabled', !isNavigationActive || currentParentIndex >= uniqueParents.length - 1);
                    
                    // Update button tooltips
                    $('#play-auto').attr('title', isNavigationActive ? 'Show all products' : 'Start parent navigation');
                    $('#play-pause').attr('title', 'Stop navigation and show all');
                    $('#play-forward').attr('title', isNavigationActive ? 'Next parent' : 'Start navigation first');
                    $('#play-backward').attr('title', isNavigationActive ? 'Previous parent' : 'Start navigation first');
                    
                    // Update button colors based on state
                    if (isNavigationActive) {
                        $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                    } else {
                        $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                    }
                }

                // Load inventory data on page load (since table is visible by default)
                function loadInventoryDataOnInit() {
                    if (!inventoryDataLoaded) {
                        loadInventoryData();
                    }
                }

                // Load inventory data immediately on page load since table is visible by default
                loadInventoryDataOnInit();

                // Set up sorting handlers
                setupSorting();

                // Parent and SKU filter handlers
                $('#filterParent, #filterSKU').on('input', debounce(function() {
                    if (inventoryDataLoaded) {
                        renderInventoryTable(inventoryData);
                    }
                }, 300));

                $('#toggleInventoryBtn').on('click', function() {
                    const tableContainer = $('#inventoryTableContainer');
                    const isVisible = tableContainer.is(':visible');
                    
                    if (isVisible) {
                        tableContainer.slideUp(300);
                        $(this).html('<i class="fas fa-boxes me-1"></i> Show Inventory');
                    } else {
                        tableContainer.slideDown(300);
                        $(this).html('<i class="fas fa-boxes me-1"></i> Hide Inventory');
                        
                        // Load inventory data if not already loaded
                        if (!inventoryDataLoaded) {
                            loadInventoryData();
                        } else {
                            renderInventoryTable(inventoryData);
                        }
                    }
                });

                // Function to get DIL color based on value
                function getDilColor(value) {
                    const percent = parseFloat(value) * 100;
                    if (percent < 16.66) return 'red';
                    if (percent >= 16.66 && percent < 25) return 'yellow';
                    if (percent >= 25 && percent < 50) return 'green';
                    return 'pink';
                }

                // Load inventory data from API
                function loadInventoryData() {
                    $.ajax({
                        url: '/stock-balance-inventory-data',
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        beforeSend: function () {
                            $('#inventory-data-table-body').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
                        },
                        success: function (response) {
                            if (response && response.data) {
                                inventoryData = response.data;
                                inventoryDataLoaded = true;
                                // Initialize parent navigation
                                initPlaybackControls();
                                // Set up sorting handlers (in case DOM wasn't ready earlier)
                                setupSorting();
                                renderInventoryTable(inventoryData);
                            }
                        },
                        error: function(xhr) {
                            console.error("Load inventory error:", xhr.responseText);
                            $('#inventory-data-table-body').html('<tr><td colspan="6" class="text-center text-danger">Error loading inventory data</td></tr>');
                        }
                    });
                }

                $('#stockBalanceForm').on('submit', function (e) {
                    e.preventDefault();
                    
                    // Validate ratio calculation first - ensure calculated value is a whole number
                    const fromQtyAdj = parseFloat($('#to_adjust_qty').val()) || 0;
                    const ratio = $('#ratio').val() || '1:1';
                    
                    if (fromQtyAdj > 0 && ratio) {
                        const ratioParts = ratio.split(':');
                        if (ratioParts.length === 2) {
                            const firstRatio = parseFloat(ratioParts[0]);
                            const secondRatio = parseFloat(ratioParts[1]);
                            
                            if (firstRatio > 0) {
                                const calculatedQty = fromQtyAdj * (secondRatio / firstRatio);
                                
                                // Check if the result is a whole number
                                if (calculatedQty % 1 !== 0) {
                                    showLargeErrorAlert(
                                        'Invalid Quantity Calculation',
                                        `The calculated "Qty Adj (To)" value (<strong>${calculatedQty.toFixed(2)}</strong>) is not a whole number.<br><br>` +
                                        `Please adjust the "Qty Adj (From)" value or select a different ratio to get a whole number result.`
                                    );
                                    return false;
                                }
                            }
                        }
                    }
                    
                    // Validate inventory before submitting - check FROM SKU (Qty Adj (From))
                    const fromAvailableQty = parseInt($('#to_available_qty').val()) || 0;
                    const fromSku = $('#to_sku option:selected').text();
                    
                    if (fromQtyAdj > fromAvailableQty) {
                        showLargeErrorAlert(
                            'Insufficient Inventory',
                            `Cannot transfer <strong>${fromQtyAdj} units</strong> from SKU: <strong>${fromSku}</strong><br><br>` +
                            `<strong>Available Quantity:</strong> ${fromAvailableQty} units<br>` +
                            `<strong>Requested Transfer:</strong> ${fromQtyAdj} units<br><br>` +
                            `You need <strong>${fromQtyAdj - fromAvailableQty} more units</strong> to complete this transfer.<br><br>` +
                            `<em>Please adjust the quantity or select a different SKU.</em>`
                        );
                        return false;
                    }
                    
                    if (fromQtyAdj <= 0) {
                        showLargeErrorAlert(
                            'Invalid Quantity',
                            'Qty Adj (From) must be greater than 0.'
                        );
                        return false;
                    }
                    
                    // Also validate TO SKU quantity
                    const toQtyAdj = parseInt($('#from_adjust_qty').val()) || 0;
                    if (toQtyAdj <= 0) {
                        showLargeErrorAlert(
                            'Invalid Quantity',
                            'Qty Adj (To) must be greater than 0.'
                        );
                        return false;
                    }

                    const formData = $(this).serialize();
                    const $submitBtn = $(this).find('button[type="submit"]');
                    const originalText = $submitBtn.text();
                    $submitBtn.prop('disabled', true).text('Processing (This may take 30-60 seconds)...');

                    $.ajax({
                        url: '{{ route("stock.balance.store") }}',
                        method: 'POST',
                        data: formData,
                        timeout: 120000, // 120 second timeout (2 minutes)
                        success: function (response) {
                            $submitBtn.prop('disabled', false).text(originalText);
                            $('#stockBalanceFormContainer').slideUp(300);
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
                            } else if (xhr.status === 400) {
                                // Insufficient inventory or bad request
                                errorMessage = xhr.responseJSON?.error || 'Invalid Request';
                                errorDetails = xhr.responseJSON?.details || 'The request could not be processed. Please check your input and try again.';
                            } else if (xhr.status === 429) {
                                errorMessage = 'Shopify API Rate Limit Exceeded!';
                                errorDetails = 'Too many requests to Shopify. Please wait 10-15 seconds and try again.';
                            } else if (xhr.status === 504 || xhr.status === 524) {
                                errorMessage = 'Request Timeout';
                                errorDetails = 'The operation took too long to complete. This could be due to:<br>' +
                                              '• Shopify API being slow<br>' +
                                              '• Network connectivity issues<br><br>' +
                                              'Please wait a moment and try again. If the problem persists, contact support.';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server Error';
                                errorDetails = xhr.responseJSON?.message || xhr.responseJSON?.details || 'An unexpected server error occurred. Please check the logs or contact support.';
                            } else if (xhr.status === 0) {
                                errorMessage = 'Connection Failed or Request Timeout';
                                errorDetails = 'Unable to complete the request. This could be because:<br>' +
                                              '• The operation is taking longer than expected (try again)<br>' +
                                              '• Network connection was lost<br>' +
                                              '• Server is not responding<br><br>' +
                                              'Please refresh the page and try again.';
                            } else if (xhr.statusText === 'timeout') {
                                errorMessage = 'Request Timeout';
                                errorDetails = 'The stock transfer operation took too long (>2 minutes). Please try again or contact support if this persists.';
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
                    dropdownParent: $('#stockBalanceFormContainer'),
                    placeholder: "Select SKU",
                    allowClear: true,
                    minimumInputLength: 0
                });
                
                // Make the dropdown field act as search box - focus search input immediately when opened
                $('#from_sku').on('select2:open', function() {
                    setTimeout(function() {
                        const searchField = document.querySelector('#stockBalanceFormContainer .select2-search__field');
                        if (searchField) {
                            searchField.focus();
                        }
                    }, 10);
                });

                // Auto-fill Parent when select sku
                // $('#sku').on('change', function () {
                //     const parent = $(this).find('option:selected').data('parent');
                //     $('#parent').val(parent || '');
                // });

                $('#to_sku').select2({
                    dropdownParent: $('#stockBalanceFormContainer'),
                    placeholder: "Select SKU",
                    allowClear: true,
                    minimumInputLength: 0
                });
                
                // Make the dropdown field act as search box - focus search input immediately when opened
                $('#to_sku').on('select2:open', function() {
                    setTimeout(function() {
                        const searchField = document.querySelector('#stockBalanceFormContainer .select2-search__field');
                        if (searchField) {
                            searchField.focus();
                        }
                    }, 10);
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
                    
                    // Show available quantity hint
                    $('#from_qty_hint').text(`Available: ${availableQty || 0} units`).css('color', '#6c757d');

                });

                // Validate quantity when user enters it
                $('#from_adjust_qty').on('input', function() {
                    const requestedQty = parseInt($(this).val()) || 0;
                    const availableQty = parseInt($('#from_available_qty').val()) || 0;
                    const hint = $('#from_qty_hint');
                    
                    if (requestedQty > availableQty) {
                        hint.text(`⚠️ Not enough inventory! Available: ${availableQty} units, Requested: ${requestedQty} units`)
                            .css('color', '#dc3545');
                        $(this).addClass('is-invalid');
                    } else if (requestedQty > 0) {
                        hint.text(`✓ Valid quantity (${availableQty - requestedQty} units will remain)`)
                            .css('color', '#28a745');
                        $(this).removeClass('is-invalid');
                    } else {
                        hint.text(`Available: ${availableQty} units`).css('color', '#6c757d');
                        $(this).removeClass('is-invalid');
                    }
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

                // Calculate Qty Adj (To) based on Qty Adj (From) and ratio
                function calculateToQtyAdj() {
                    const fromQty = parseFloat($('#to_adjust_qty').val()) || 0;
                    const ratio = $('#ratio').val() || '1:1';
                    const errorDiv = $('#ratio_calculation_error');
                    const toQtyInput = $('#from_adjust_qty');
                    
                    // Clear previous errors
                    errorDiv.hide().text('');
                    toQtyInput.removeClass('is-invalid');
                    
                    if (fromQty > 0 && ratio) {
                        // Parse ratio (e.g., "2:1" -> [2, 1])
                        const ratioParts = ratio.split(':');
                        if (ratioParts.length === 2) {
                            const firstRatio = parseFloat(ratioParts[0]);
                            const secondRatio = parseFloat(ratioParts[1]);
                            
                            if (firstRatio > 0) {
                                // Calculate: to_qty = from_qty * (second_ratio / first_ratio)
                                const calculatedQty = fromQty * (secondRatio / firstRatio);
                                
                                // Check if the result is a whole number
                                if (calculatedQty % 1 !== 0) {
                                    // Not a whole number - show error
                                    const roundedQty = Math.round(calculatedQty);
                                    errorDiv.text(`⚠️ Calculated quantity (${calculatedQty.toFixed(2)}) is not a whole number. Expected: ${roundedQty} (rounded). Please adjust the "Qty Adj (From)" value.`).show();
                                    toQtyInput.addClass('is-invalid').val('');
                                    return false;
                                } else {
                                    // Valid whole number
                                    toQtyInput.val(calculatedQty);
                                    return true;
                                }
                            }
                        }
                    }
                    return true;
                }

                // Trigger calculation when Qty Adj (From) changes
                $('#to_adjust_qty').on('input', function() {
                    calculateToQtyAdj();
                });

                // Trigger calculation when ratio changes
                $('#ratio').on('change', function() {
                    calculateToQtyAdj();
                });

               
                // Toggle Stock Balance Form
                $('#toggleStockBalanceForm').on('click', function () {
                    const formContainer = $('#stockBalanceFormContainer');
                    const isVisible = formContainer.is(':visible');
                    
                    if (isVisible) {
                        formContainer.slideUp(300);
                    } else {
                        // Reset form when showing
                        $('#stockBalanceForm')[0].reset();
                        $('#warehouseId').val('');
                        formContainer.slideDown(300);
                    }
                });

                // Close button handler
                $('#closeStockBalanceForm').on('click', function () {
                    $('#stockBalanceFormContainer').slideUp(300);
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
                        $('#rainbow-loader').hide();
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
