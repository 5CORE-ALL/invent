@extends('layouts.vertical', ['title' => 'Refunds', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        .is-invalid {
            border: 2px solid red !important;
            background-color: #ffe6e6 !important;
        }
        .error-message {
            font-size: 13px;
            margin-top: 4px;
        }
        #inventoryTable thead th.sortable {
            cursor: pointer;
            user-select: none;
        }
        #inventoryTable thead tr.header-filters th {
            padding: 6px 8px;
            vertical-align: middle;
        }
        #inventoryTable thead tr.header-filters input {
            width: 100%;
            min-width: 90px;
        }
        .refund-30d-badge {
            cursor: pointer;
            font-size: 0.95rem;
            padding: 0.45em 0.75em;
        }
        .refund-30d-badge:hover {
            filter: brightness(1.05);
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Refunds',
        'sub_title' => 'Refunds',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <span class="badge bg-info text-dark refund-30d-badge" id="refund30dBadge" title="Click for daily chart (last 30 days, non-archived)">
                            <i class="fas fa-chart-line me-1"></i>
                            <span id="refund30dBadgeText">30-day refunds: …</span>
                        </span>
                        <span class="small text-muted">Non-archived records · ET dates</span>
                    </div>

                    <!-- Filters -->
                    <div class="row mb-3 p-3 bg-light rounded">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Reason</label>
                            <div class="d-flex align-items-center gap-1">
                                <select id="filterReason" class="form-select form-select-sm" style="min-width: 140px;">
                                    <option value="">All</option>
                                    @foreach($reasons ?? [] as $r)
                                        <option value="{{ $r }}">{{ $r }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="manageReasonsBtn" title="Add or manage reasons">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Person</label>
                            <select id="filterPerson" class="form-select form-select-sm" style="min-width: 120px;">
                                <option value="">All</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Start Date</label>
                            <input type="date" id="filterStartDate" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">End Date</label>
                            <input type="date" id="filterEndDate" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Order ID</label>
                            <input type="text" id="filterOrderId" class="form-control form-control-sm" placeholder="Contains..." style="min-width:100px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Channel</label>
                            <input type="text" id="filterChannel" class="form-control form-control-sm" placeholder="Search..." style="min-width:100px;">
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-primary me-1" id="applyFilter"><i class="fas fa-filter me-1"></i>Apply</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFilter">Clear</button>
                        </div>
                        <div class="col-auto ms-3 d-flex align-items-end">
                            <div class="mb-0">
                                <span class="small text-muted">Total Refund Amt (filtered):</span>
                                <strong id="totalValueFiltered" class="ms-1">$0</strong>
                            </div>
                        </div>
                        <div class="col-auto d-flex align-items-end">
                            <div class="mb-0">
                                <span class="small text-muted">Selected Refund Amt:</span>
                                <strong id="selectedRowsValue" class="ms-1">$0</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Search Box and Add Button-->
                    <div class="row mb-3">
                        <div class="col-md-6 d-flex align-items-center flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" id="openRefundModal" data-bs-toggle="modal" data-bs-target="#refundsModal">
                                <i class="fas fa-plus me-1"></i> Create Outgoing Refunds Records
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="archiveSelectedBtn" title="Archive selected rows">
                                <i class="fas fa-archive me-1"></i> Archive selected
                            </button>
                            <button type="button" class="btn btn-success" id="exportRefundsCsvBtn" title="Export visible rows to CSV">
                                <i class="fas fa-file-csv me-1"></i> Export CSV
                            </button>
                            <div class="dataTables_length"></div>
                        </div>

                        <div class="col-md-3 offset-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control" placeholder="Search refunds">
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

                    <!-- Refund Modal -->
                    <div class="modal fade" id="refundsModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <form id="refundForm">
                                @csrf
                                <div class="modal-content">

                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="refundModalLabel">Create Outgoing Refunds Records</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <div id="incoming-errors" class="mb-2 text-danger"></div>

                                        <div id="outgoing-rows-container">
                                            <div class="outgoing-row border rounded p-3 mb-3" data-row="0">
                                                <div class="row mb-2">
                                                    <div class="col-md-8">
                                                        <label for="sku_0" class="form-label fw-bold">SKU</label>
                                                        <select class="form-select row-sku" id="sku_0" name="sku[]" required>
                                                            <option selected disabled>Select SKU</option>
                                                            @foreach($skus as $item)
                                                                <option value="{{ $item->sku }}">{{ $item->sku }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="qty_0" class="form-label fw-bold">Qty</label>
                                                        <input type="number" id="qty_0" name="qty[]" class="form-control" required min="1">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="refund_amt" class="form-label fw-bold">Refund Amt</label>
                                                        <input type="number" id="refund_amt" name="refund_amt" class="form-control" required min="0" step="0.01" placeholder="0.00">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="reason" class="form-label fw-bold">Reason</label>
                                            <select class="form-select" id="reason" name="reason" required>
                                                <option selected disabled>Select Reason</option>
                                                @foreach($reasons ?? [] as $r)
                                                    <option value="{{ $r }}">{{ $r }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="comment" class="form-label fw-bold">Corrective Action Required</label>
                                            <input type="text" class="form-control" id="comment" name="comment" maxlength="80" placeholder="Optional (max 80 characters)">
                                            <small class="text-muted"><span id="comment-char-count">0</span>/80</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="person_responsible" class="form-label fw-bold">Person responsible</label>
                                            <select class="form-select" id="person_responsible" name="person_responsible" required>
                                                <option value="" selected disabled>Select person</option>
                                                @foreach($personResponsibleOptions ?? [] as $p)
                                                    <option value="{{ $p }}">{{ $p }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="supplier_id" class="form-label fw-bold">Supplier</label>
                                            <select class="form-select" id="supplier_id" name="supplier_id">
                                                <option value="">— Optional —</option>
                                                @foreach($suppliers ?? [] as $sup)
                                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Same supplier list as Forecast Analysis</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="order_id" class="form-label fw-bold">Order ID</label>
                                            <input type="text" class="form-control" id="order_id" name="order_id" maxlength="30" placeholder="Optional (max 30 characters)">
                                        </div>
                                        <div class="mb-3">
                                            <label for="channel_master_id" class="form-label fw-bold">Channel</label>
                                            <select class="form-select" id="channel_master_id" name="channel_master_id" style="width:100%">
                                                <option value="">— Optional —</option>
                                                @foreach($channels ?? [] as $ch)
                                                    <option value="{{ $ch->id }}">{{ $ch->channel }}@if(!empty($ch->type)) — {{ $ch->type }}@endif</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Active channels from Channel Master</small>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">Save refund</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


                    <!-- Edit Reason & Comment Modal -->
                    <div id="editReasonCommentModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit refund record</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" id="editInventoryId">
                                    <p class="mb-2"><strong>SKU:</strong> <span id="editSkuDisplay"></span></p>
                                    <div class="mb-3">
                                        <label for="editReason" class="form-label">Reason</label>
                                        <select class="form-select" id="editReason">
                                            @foreach($reasons ?? [] as $r)
                                                <option value="{{ $r }}">{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editComment" class="form-label">Corrective Action Required</label>
                                        <input type="text" class="form-control" id="editComment" maxlength="80" placeholder="Optional (max 80 characters)">
                                    </div>
                                    <div class="mb-3">
                                        <label for="editPersonResponsible" class="form-label">Person responsible</label>
                                        <select class="form-select" id="editPersonResponsible">
                                            @foreach($personResponsibleOptions ?? [] as $p)
                                                <option value="{{ $p }}">{{ $p }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editSupplierId" class="form-label">Supplier</label>
                                        <select class="form-select" id="editSupplierId">
                                            <option value="">— None —</option>
                                            @foreach($suppliers ?? [] as $sup)
                                                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editOrderId" class="form-label">Order ID</label>
                                        <input type="text" class="form-control" id="editOrderId" maxlength="30" placeholder="Optional (max 30)">
                                    </div>
                                    <div class="mb-3">
                                        <label for="editChannelMasterId" class="form-label">Channel</label>
                                        <select class="form-select" id="editChannelMasterId" style="width:100%">
                                            <option value="">— Optional —</option>
                                            @foreach($channels ?? [] as $ch)
                                                <option value="{{ $ch->id }}">{{ $ch->channel }}@if(!empty($ch->type)) — {{ $ch->type }}@endif</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div id="editReasonCommentError" class="text-danger small" style="display:none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="saveEditReasonCommentBtn">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 30-day refund chart modal -->
                    <div id="refund30dChartModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-info text-dark">
                                    <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Refund amount — last 30 days</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="mb-2"><strong>Total (30 days):</strong> <span id="refund30dModalTotal">$0</span></p>
                                    <div style="height:320px;position:relative;">
                                        <canvas id="refund30dChartCanvas"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History Modal -->
                    <div id="historyModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">History — SKU: <span id="historySkuDisplay"></span></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small">First row shows original; below are updates (reason / corrective action).</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Field</th>
                                                    <th>Previous</th>
                                                    <th>Updated to</th>
                                                    <th>By</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody id="historyTableBody">
                                            </tbody>
                                        </table>
                                    </div>
                                    <div id="historyEmpty" class="text-muted" style="display:none;">No edit history for this record.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manage Reasons Modal -->
                    <div id="manageReasonsModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Manage refund reasons</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Add new reason</label>
                                        <div class="d-flex gap-2">
                                            <input type="text" id="newReasonName" class="form-control" placeholder="e.g. Issue(Returns)" maxlength="255">
                                            <button type="button" id="addReasonBtn" class="btn btn-primary">Add</button>
                                        </div>
                                        <div id="newReasonError" class="text-danger small mt-1" style="display:none;"></div>
                                    </div>
                                    <hr>
                                    <label class="form-label">Current reasons</label>
                                    <ul id="reasonsList" class="list-group list-group-flush mb-0">
                                        @foreach($reasons ?? [] as $r)
                                            <li class="list-group-item d-flex justify-content-between align-items-center">{{ $r }}</li>
                                        @endforeach
                                    </ul>
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
                                    <th rowspan="2"><input type="checkbox" id="selectAllRows" title="Select all"></th>
                                    <th rowspan="2" class="sortable" data-col="sku">SKU <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="verified_stock">QUANTITY <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="reason">REASON <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="remarks">CORRECTIVE ACTION REQUIRED <i class="fas fa-sort ms-1"></i></th>
                                    <th class="sortable" data-col="person_responsible">PERSON RESPONSIBLE <i class="fas fa-sort ms-1"></i></th>
                                    <th class="sortable" data-col="supplier_name">SUPPLIER <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="order_id">ORDER ID <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="channel_name">CHANNEL <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="approved_by">CREATED BY <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="approved_at">DATE <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="refund_amt">REFUND AMT <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2" class="sortable" data-col="is_archived">ARCHIVE <i class="fas fa-sort ms-1"></i></th>
                                    <th rowspan="2">ACTIONS</th>
                                </tr>
                                <tr class="header-filters">
                                    <th>
                                        <input type="text" id="headerFilterPR" class="form-control form-control-sm" placeholder="Search person..." autocomplete="off">
                                    </th>
                                    <th>
                                        <input type="text" id="headerFilterSupplier" class="form-control form-control-sm" placeholder="Search supplier..." autocomplete="off">
                                    </th>
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
                        <div class="loading-text">Loading refunds...</div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <script>


        document.addEventListener('DOMContentLoaded', function() {
            // Set zoom level
            document.body.style.zoom = "75%";

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Store the loaded data globally
            let tableData = [];
            let currentDisplayData = [];
            let sortCol = null;
            let sortDir = 1; // 1 = asc, -1 = desc

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

            var refund30dChartInstance = null;

            function formatRefundMoney(n) {
                var x = parseFloat(n) || 0;
                return '$' + x.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function loadRefund30dBadge() {
                $.getJSON('{{ route("refunds.stats-30d") }}')
                    .done(function (res) {
                        $('#refund30dBadgeText').text('30-day refunds: ' + formatRefundMoney(res.total_30d));
                    })
                    .fail(function () {
                        $('#refund30dBadgeText').text('30-day refunds: —');
                    });
            }

            function initializeTable() {
                loadData();
                loadRefund30dBadge();
                setupSearch();
                setupSorting();
                setupAddWarehouseModal();
                setupProgressModal();
                setupEditDeleteButtons();
            }

            $(document).on('click', '#refund30dBadge', function () {
                $.getJSON('{{ route("refunds.stats-30d") }}')
                    .done(function (res) {
                        $('#refund30dModalTotal').text(formatRefundMoney(res.total_30d));
                        var daily = res.daily || [];
                        var modalEl = document.getElementById('refund30dChartModal');
                        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                        modalEl.addEventListener('shown.bs.modal', function drawChart() {
                            modalEl.removeEventListener('shown.bs.modal', drawChart);
                            if (refund30dChartInstance) {
                                refund30dChartInstance.destroy();
                                refund30dChartInstance = null;
                            }
                            var canvas = document.getElementById('refund30dChartCanvas');
                            if (!canvas || typeof Chart === 'undefined') return;
                            var ctx = canvas.getContext('2d');
                            refund30dChartInstance = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: daily.map(function (d) { return d.label; }),
                                    datasets: [{
                                        label: 'Refund amount ($)',
                                        data: daily.map(function (d) { return d.total; }),
                                        borderColor: 'rgb(13, 110, 253)',
                                        backgroundColor: 'rgba(13, 110, 253, 0.15)',
                                        fill: true,
                                        tension: 0.2,
                                        pointRadius: 2,
                                        pointHoverRadius: 5
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    plugins: {
                                        legend: { display: true },
                                        tooltip: {
                                            callbacks: {
                                                label: function (c) {
                                                    return ' $' + Number(c.parsed.y).toFixed(2);
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                                        y: { beginAtZero: true, ticks: { callback: function (v) { return '$' + v; } } }
                                    }
                                }
                            });
                        });
                    });
            });
            

            $(document).ready(function () {

                $('#refundForm').on('submit', function (e) {
                    e.preventDefault();

                    $('.error-message').remove();
                    $('input, select').removeClass('is-invalid');

                    const formData = $(this).serialize();
                    let hasError = false;
                    $('#outgoing-rows-container .outgoing-row').each(function(idx) {
                        const $row = $(this);
                        const skuVal = $row.find('.row-sku').val();
                        const qtyVal = $row.find('input[name="qty[]"]').val();
                        if (!skuVal || skuVal === 'Select SKU' || !qtyVal || parseInt(qtyVal, 10) < 1) {
                            hasError = true;
                            $row.find('.row-sku, input[name="qty[]"]').addClass('is-invalid');
                            $row.append(`<div class="text-danger error-message small">Row ${idx + 1}: SKU and Qty required.</div>`);
                        }
                    });
                    const reasonVal = $('#reason').val();
                    if (!reasonVal || reasonVal === 'Select Reason') {
                        hasError = true;
                        $('#reason').addClass('is-invalid').after('<div class="text-danger error-message">Reason is required.</div>');
                    }
                    const prVal = $('#person_responsible').val();
                    if (!prVal) {
                        hasError = true;
                        $('#person_responsible').addClass('is-invalid').after('<div class="text-danger error-message">Person responsible is required.</div>');
                    }
                    const refundAmtVal = parseFloat($('#refund_amt').val());
                    if ($('#refund_amt').val() === '' || isNaN(refundAmtVal) || refundAmtVal < 0) {
                        hasError = true;
                        $('#refund_amt').addClass('is-invalid').after('<div class="text-danger error-message">Refund Amt is required (0 or more).</div>');
                    }
                    if (hasError) return;


                    // Create overlay loader dynamically
                    const overlay = document.createElement("div");
                    overlay.id = "processing-overlay";
                    overlay.innerHTML = `
                        <div style="
                            position:fixed;
                            top:0; left:0;
                            width:100%; height:100%;
                            background:rgba(0,0,0,0.6);
                            color:white;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            flex-direction:column;
                            z-index:9999;
                            font-size:20px;
                        ">
                            <div style="font-size:28px;">Saving refund record...</div>
                            <small style="margin-top:10px;font-size:16px;">
                                Log only — inventory and Shopify are not changed.
                            </small>
                        </div>`;
                    document.body.appendChild(overlay);

                    $.ajax({
                        url: '{{ route("refunds.store") }}',
                        method: 'POST',
                        data: formData,
                        success: function (response) {
                            document.getElementById("processing-overlay")?.remove();
                            alert(response.message || 'Refund record saved.');
                            $('#refundsModal').modal('hide');
                            $('#refundForm')[0].reset();
                            if ($('#sku_0').data('select2')) {
                                $('#sku_0').val(null).trigger('change');
                            }
                            location.reload();
                        },
                        error: function (xhr) {
                            document.getElementById("processing-overlay")?.remove();
                            console.log(xhr.responseJSON);
                            var err = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Error saving refund.';
                            alert(err);
                        }
                    });
                });

                var $modal = $('#refundsModal');
                $('#sku_0').select2({
                    dropdownParent: $modal,
                    placeholder: "Select SKU",
                    allowClear: true
                });
                $('#channel_master_id').select2({
                    dropdownParent: $modal,
                    placeholder: 'Search channel...',
                    allowClear: true,
                    width: '100%'
                });
                var $editChModal = $('#editReasonCommentModal');
                $('#editChannelMasterId').select2({
                    dropdownParent: $editChModal,
                    placeholder: 'Search channel...',
                    allowClear: true,
                    width: '100%'
                });
                var skuSupplierTimer = null;
                $('#sku_0').on('change', function () {
                    var sku = $(this).val();
                    clearTimeout(skuSupplierTimer);
                    if (!sku) {
                        $('#supplier_id').val('');
                        return;
                    }
                    skuSupplierTimer = setTimeout(function () {
                        $.getJSON('{{ route("refunds.supplier-for-sku") }}', { sku: sku })
                            .done(function (res) {
                                if (res && res.supplier_id) {
                                    $('#supplier_id').val(String(res.supplier_id));
                                } else {
                                    $('#supplier_id').val('');
                                }
                            })
                            .fail(function () {
                                $('#supplier_id').val('');
                            });
                    }, 200);
                });
                $(document).on('click', '#openRefundModal', function () {
                    $('#refundForm')[0].reset();
                    $('#comment-char-count').text('0');
                    $('#refundModalLabel').text('Create Outgoing Refunds Records');
                    if ($('#sku_0').data('select2')) {
                        $('#sku_0').val(null).trigger('change');
                    }
                    $('#order_id').val('');
                    if ($('#channel_master_id').data('select2')) {
                        $('#channel_master_id').val(null).trigger('change');
                    } else {
                        $('#channel_master_id').val('');
                    }
                    $('#refundsModal').modal('show');
                });
                $(document).on('input', '#comment', function () {
                    $('#comment-char-count').text(this.value.length);
                });
            });


            var headerFilterTimer = null;
            function loadData() {
                var params = {
                    reason: $('#filterReason').val() || undefined,
                    person: $('#filterPerson').val() || undefined,
                    start_date: $('#filterStartDate').val() || undefined,
                    end_date: $('#filterEndDate').val() || undefined,
                    filter_pr: ($('#headerFilterPR').val() || '').trim() || undefined,
                    filter_supplier: ($('#headerFilterSupplier').val() || '').trim() || undefined,
                    filter_order_id: ($('#filterOrderId').val() || '').trim() || undefined,
                    filter_channel: ($('#filterChannel').val() || '').trim() || undefined
                };
                $.ajax({
                    url: '/refunds-data-list',
                    method: 'GET',
                    data: params,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    beforeSend: function () {
                        $('#rainbow-loader').show(); 
                    },
                    success: function (response) {
                        tableData = response.data || [];
                        if (response.reasons && response.reasons.length) {
                            var $sel = $('#filterReason');
                            $sel.find('option:not([value=""])').remove();
                            response.reasons.forEach(function(r) {
                                $sel.append($('<option></option>').val(r).text(r));
                            });
                        }
                        if (response.persons && $('#filterPerson option').length <= 1) {
                            response.persons.forEach(function(p) {
                                $('#filterPerson').append($('<option></option>').val(p).text(p || '(blank)'));
                            });
                        }
                        renderTable(tableData);
                        setupSearch();
                        $('#selectedRowsValue').text('$0');
                        $('#rainbow-loader').hide();
                    },
                    error: function(xhr) {
                        console.error("Load error:", xhr.responseText);
                        $('#rainbow-loader').hide();
                    }
                });
            }

            $(document).on('click', '#applyFilter', function() { loadData(); });
            $(document).on('click', '#clearFilter', function() {
                $('#filterReason').val('');
                $('#filterPerson').val('');
                $('#filterStartDate').val('');
                $('#filterEndDate').val('');
                $('#headerFilterPR').val('');
                $('#headerFilterSupplier').val('');
                $('#filterOrderId').val('');
                $('#filterChannel').val('');
                loadData();
            });

            $(document).on('input', '#headerFilterPR, #headerFilterSupplier', function() {
                clearTimeout(headerFilterTimer);
                headerFilterTimer = setTimeout(function() { loadData(); }, 450);
            });

            function refreshReasonsDropdowns(reasons) {
                if (!reasons) return;
                var $filter = $('#filterReason');
                $filter.find('option:not([value=""])').remove();
                reasons.forEach(function(r) { $filter.append($('<option></option>').val(r).text(r)); });
                var $reason = $('#reason');
                $reason.find('option:not(:first)').remove();
                reasons.forEach(function(r) { $reason.append($('<option></option>').val(r).text(r)); });
                var $editReason = $('#editReason');
                $editReason.find('option').remove();
                reasons.forEach(function(r) { $editReason.append($('<option></option>').val(r).text(r)); });
                var $list = $('#reasonsList');
                $list.empty();
                reasons.forEach(function(r) {
                    $list.append($('<li class="list-group-item d-flex justify-content-between align-items-center"></li>').text(r));
                });
            }

            $(document).on('click', '#manageReasonsBtn', function() {
                $.get('/refunds-reasons', function(res) {
                    if (res.reasons && res.reasons.length) {
                        $('#reasonsList').empty();
                        res.reasons.forEach(function(r) {
                            $('#reasonsList').append($('<li class="list-group-item d-flex justify-content-between align-items-center"></li>').text(r));
                        });
                    }
                });
                $('#newReasonName').val('');
                $('#newReasonError').hide().text('');
                new bootstrap.Modal(document.getElementById('manageReasonsModal')).show();
            });

            $(document).on('click', '#addReasonBtn', function() {
                var name = ($('#newReasonName').val() || '').trim();
                var $err = $('#newReasonError');
                $err.hide().text('');
                if (!name) {
                    $err.text('Please enter a reason name.').show();
                    return;
                }
                $.ajax({
                    url: '/refunds-reasons',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ name: name }),
                    success: function(res) {
                        if (res.success && res.reasons) {
                            refreshReasonsDropdowns(res.reasons);
                            $('#newReasonName').val('');
                        }
                    },
                    error: function(xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not add reason.';
                        $err.text(msg).show();
                    }
                });
            });

                $(document).on('click', '.edit-reason-btn', function() {
                    var id = $(this).data('id');
                    var sku = $(this).data('sku') || '-';
                    var reason = $(this).data('reason') || '';
                    var remarks = $(this).data('remarks') || '';
                    var pr = $(this).attr('data-person-responsible') || '';
                    var supId = $(this).attr('data-supplier-id') || '';
                    var idNum = parseInt(id, 10);
                    var rec = tableData.find(function(r) { return parseInt(r.id, 10) === idNum; });
                    if (!rec && currentDisplayData && currentDisplayData.length) {
                        rec = currentDisplayData.find(function(r) { return parseInt(r.id, 10) === idNum; });
                    }
                    $('#editInventoryId').val(id);
                    $('#editSkuDisplay').text(sku);
                    $('#editReason').val(reason);
                    $('#editComment').val(remarks);
                    var $epr = $('#editPersonResponsible');
                    $epr.val(pr);
                    if (!$epr.val()) { $epr.prop('selectedIndex', 0); }
                    $('#editSupplierId').val(supId || '');
                    $('#editOrderId').val(rec && rec.order_id ? rec.order_id : '');
                    var chVal = rec && rec.channel_master_id ? String(rec.channel_master_id) : '';
                    $('#editChannelMasterId').val(chVal).trigger('change');
                    $('#editReasonCommentError').hide().text('');
                    new bootstrap.Modal(document.getElementById('editReasonCommentModal')).show();
                });

                $(document).on('click', '.copy-order-id-btn', function(e) {
                    e.preventDefault();
                    var enc = $(this).attr('data-copy-enc') || '';
                    var text = enc ? decodeURIComponent(enc) : '';
                    if (!text) return;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            alert('Order ID copied.');
                        }).catch(function() {
                            alert(text);
                        });
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); alert('Order ID copied.'); } catch (x) { alert(text); }
                        document.body.removeChild(ta);
                    }
                });

                $(document).on('click', '#saveEditReasonCommentBtn', function() {
                    var id = $('#editInventoryId').val();
                    var reason = $('#editReason').val();
                    var comment = $('#editComment').val();
                    var personResponsible = $('#editPersonResponsible').val();
                    var supplierId = $('#editSupplierId').val();
                    var $err = $('#editReasonCommentError');
                    $err.hide().text('');
                    if (!reason) {
                        $err.text('Reason is required.').show();
                        return;
                    }
                    if (!personResponsible) {
                        $err.text('Person responsible is required.').show();
                        return;
                    }
                    $.ajax({
                        url: '/refunds-update-reason-comment',
                        method: 'PUT',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        contentType: 'application/json',
                        data: JSON.stringify({
                            id: id,
                            reason: reason,
                            comment: comment,
                            person_responsible: personResponsible,
                            supplier_id: supplierId ? parseInt(supplierId, 10) : null,
                            order_id: ($('#editOrderId').val() || '').trim().substring(0, 30) || null,
                            channel_master_id: $('#editChannelMasterId').val() ? parseInt($('#editChannelMasterId').val(), 10) : null
                        }),
                        success: function(res) {
                            if (res.success && res.record) {
                                var idNum = parseInt(id, 10);
                                function patchRow(r) {
                                    if (!r) return;
                                    r.reason = res.record.reason;
                                    r.remarks = res.record.remarks;
                                    r.person_responsible = res.record.person_responsible;
                                    r.supplier_id = res.record.supplier_id;
                                    r.supplier_name = res.record.supplier_name;
                                    r.order_id = res.record.order_id;
                                    r.channel_master_id = res.record.channel_master_id;
                                    r.channel_name = res.record.channel_name;
                                }
                                var idx = tableData.findIndex(function(r) { return parseInt(r.id, 10) === idNum; });
                                patchRow(tableData[idx]);
                                if (currentDisplayData.length) {
                                    var dx = currentDisplayData.findIndex(function(r) { return parseInt(r.id, 10) === idNum; });
                                    patchRow(currentDisplayData[dx]);
                                }
                                renderTable(currentDisplayData.length ? currentDisplayData : tableData);
                                bootstrap.Modal.getInstance(document.getElementById('editReasonCommentModal')).hide();
                            }
                        },
                        error: function(xhr) {
                            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Update failed.';
                            $err.text(msg).show();
                        }
                    });
                });

                $(document).on('click', '.history-btn', function() {
                    var id = $(this).data('id');
                    var sku = $(this).data('sku') || '-';
                    $('#historySkuDisplay').text(sku);
                    $('#historyTableBody').empty();
                    $('#historyEmpty').hide();
                    $.get('/refunds-history/' + id, function(res) {
                        if (res.success) {
                            if (res.history && res.history.length) {
                                res.history.forEach(function(h) {
                                    $('#historyTableBody').append(
                                        '<tr><td>' + (h.field_label || h.field) + '</td><td>' + escapeHtml(h.old_value || '') + '</td><td>' + escapeHtml(h.new_value || '') + '</td><td>' + escapeHtml(h.updated_by || '') + '</td><td>' + escapeHtml(h.updated_at || '') + '</td></tr>'
                                    );
                                });
                            } else {
                                $('#historyEmpty').show();
                            }
                        }
                    }).fail(function() {
                        $('#historyEmpty').text('Could not load history.').show();
                    });
                    new bootstrap.Modal(document.getElementById('historyModal')).show();
                });

                function escapeHtml(str) {
                    if (str == null) return '';
                    var div = document.createElement('div');
                    div.textContent = str;
                    return div.innerHTML;
                }

                $(document).on('click', '#archiveSelectedBtn', function() {
                const ids = [];
                document.querySelectorAll('.row-select:checked').forEach(function(cb) {
                    if (cb.getAttribute('data-archived') === '1') return;
                    const row = cb.closest('tr');
                    if (row && row.getAttribute('data-id')) ids.push(parseInt(row.getAttribute('data-id'), 10));
                });
                if (ids.length === 0) {
                    alert('Please select one or more rows to archive (archived rows are excluded).');
                    return;
                }
                $.ajax({
                    url: '/refunds-archive',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    contentType: 'application/json',
                    data: JSON.stringify({ ids: ids }),
                    success: function(res) {
                        if (res.success) { loadData(); loadRefund30dBadge(); alert(res.message || 'Archived.'); }
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to archive.');
                    }
                });
            });

            
            function renderTable(data) {
                const tbody = document.getElementById('inventory-table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="14" class="text-center">No records found</td></tr>';
                    updateTotalValueFiltered(0);
                    $('#selectedRowsValue').text('$0');
                    return;
                }

                currentDisplayData = data;
                let totalVal = 0;
                data.forEach((item, index) => {
                    const row = document.createElement('tr');
                    const refundAmt = parseFloat(item.refund_amt) || 0;
                    const archived = !!item.is_archived;
                    if (!archived) totalVal += refundAmt;
                    const amtFormatted = '$' + refundAmt.toFixed(2).replace(/\.00$/, '');
                    const archiveChecked = archived ? 'checked' : '';
                    const archiveDisabled = archived ? ' disabled' : ' disabled';
                    row.setAttribute('data-id', item.id);
                    row.setAttribute('data-archived', archived ? '1' : '0');
                    row.setAttribute('data-value', refundAmt);
                    const prEsc = escapeHtml(item.person_responsible || '-');
                    const supEsc = escapeHtml(item.supplier_name || '-');
                    const supId = item.supplier_id || '';
                    const prVal = (item.person_responsible || '').replace(/"/g, '&quot;');
                    const oid = (item.order_id || '').trim();
                    const oidEnc = oid ? encodeURIComponent(oid) : '';
                    const chEsc = escapeHtml(item.channel_name || '-');
                    const orderIdCell = oid
                        ? `<span class="order-id-cell">${escapeHtml(oid)}</span> <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 ms-1 copy-order-id-btn" data-copy-enc="${oidEnc}" title="Copy Order ID"><i class="far fa-copy"></i></button>`
                        : '<span class="text-muted">—</span>';
                    row.innerHTML = `
                        <td><input type="checkbox" class="row-select" data-index="${index}" data-value="${refundAmt}" data-archived="${archived ? '1' : '0'}"></td>
                        <td>${item.sku || '-'}</td>
                        <td>${item.verified_stock || '-'}</td>
                        <td>${item.reason || '-'}</td>
                        <td>${item.remarks || '-'}</td>
                        <td>${prEsc}</td>
                        <td>${supEsc}</td>
                        <td class="text-nowrap">${orderIdCell}</td>
                        <td>${chEsc}</td>
                        <td>${item.approved_by || '-'}</td>
                        <td>${item.approved_at || '-'}</td>
                        <td>${amtFormatted}</td>
                        <td class="text-center"><input type="checkbox" class="archive-display" ${archiveChecked}${archiveDisabled} title="${archived ? 'Archived' : 'Not archived'}"></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary edit-reason-btn" data-id="${item.id}" data-sku="${(item.sku || '').replace(/"/g, '&quot;')}" data-reason="${(item.reason || '').replace(/"/g, '&quot;')}" data-remarks="${(item.remarks || '').replace(/"/g, '&quot;')}" data-person-responsible="${prVal}" data-supplier-id="${supId}" title="Edit"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary history-btn" data-id="${item.id}" data-sku="${(item.sku || '').replace(/"/g, '&quot;')}" title="View history"><i class="fas fa-history"></i></button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                updateTotalValueFiltered(totalVal);
                bindRowCheckboxes();
                bindSelectAll();
            }

            function updateTotalValueFiltered(sum) {
                var s = parseFloat(sum) || 0;
                $('#totalValueFiltered').text('$' + (Math.round(s * 100) / 100).toFixed(2).replace(/\.00$/, ''));
            }

            function updateSelectedRowsValue() {
                let sum = 0;
                document.querySelectorAll('.row-select:checked').forEach(function(cb) {
                    if (cb.getAttribute('data-archived') === '1') return;
                    sum += parseFloat(cb.getAttribute('data-value')) || 0;
                });
                var t = (Math.round(sum * 100) / 100).toFixed(2).replace(/\.00$/, '');
                $('#selectedRowsValue').text('$' + t);
            }

            function bindRowCheckboxes() {
                $(document).off('change', '.row-select').on('change', '.row-select', function() {
                    updateSelectedRowsValue();
                });
            }

            function bindSelectAll() {
                $('#selectAllRows').off('change').on('change', function() {
                    const checked = this.checked;
                    document.querySelectorAll('.row-select').forEach(function(cb) { cb.checked = checked; });
                    updateSelectedRowsValue();
                });
            }

            // $('#sku').on('change', function () {
            //     const selectedSku = $(this).val();

            //     if (!selectedSku) {
            //         $('#available_quantity').val('');
            //         return;
            //     }

            //     // Make AJAX call to your backend to get available qty from Shopify
            //     $.ajax({
            //         url: '/shopify/get-available-quantity',
            //         method: 'GET',
            //         data: { sku: selectedSku },
            //         success: function (response) {
                        
            //             $('#available_quantity').val(response.available_quantity || 0);
            //         },
            //         error: function () {
            //             alert('Failed to fetch available quantity.');
            //             $('#available_quantity').val('');
            //         }
            //     });
            // });


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

            function setupSorting() {
                $(document).off('click', '#inventoryTable thead th.sortable').on('click', '#inventoryTable thead th.sortable', function() {
                    const col = $(this).data('col');
                    if (!col) return;
                    sortDir = (sortCol === col) ? -sortDir : 1;
                    sortCol = col;
                    const sorted = [...currentDisplayData].sort(function(a, b) {
                        let va = a[col];
                        let vb = b[col];
                        if (col === 'verified_stock' || col === 'refund_amt') {
                            va = parseFloat(va) || 0;
                            vb = parseFloat(vb) || 0;
                            return sortDir * (va - vb);
                        }
                        if (col === 'is_archived') {
                            va = va ? 1 : 0;
                            vb = vb ? 1 : 0;
                            return sortDir * (va - vb);
                        }
                        va = String(va || '').toLowerCase();
                        vb = String(vb || '').toLowerCase();
                        if (va < vb) return -sortDir;
                        if (va > vb) return sortDir;
                        return 0;
                    });
                    renderTable(sorted);
                    $('#inventoryTable thead th.sortable i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
                    $(this).find('i').removeClass('fa-sort').addClass(sortDir === 1 ? 'fa-sort-up' : 'fa-sort-down');
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
                    /* Refunds page has no warehouse edit */
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
                document.getElementById('refundForm').reset();

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

            $(document).on('click', '#exportRefundsCsvBtn', function () {
                var data = currentDisplayData && currentDisplayData.length ? currentDisplayData : tableData;
                if (!data || !data.length) {
                    alert('No data to export.');
                    return;
                }

                function csvEsc(val) {
                    var s = String(val == null ? '' : val).replace(/"/g, '""');
                    return /[",\n\r]/.test(s) ? '"' + s + '"' : s;
                }

                var headers = ['SKU', 'QTY', 'REASON', 'CORRECTIVE ACTION REQUIRED',
                    'PERSON RESPONSIBLE', 'SUPPLIER', 'ORDER ID', 'CHANNEL',
                    'CREATED BY', 'DATE', 'REFUND AMT', 'ARCHIVED'];

                var lines = [headers.map(csvEsc).join(',')];
                data.forEach(function (r) {
                    lines.push([
                        r.sku, r.verified_stock, r.reason, r.remarks,
                        r.person_responsible, r.supplier_name, r.order_id || '',
                        r.channel_name || '', r.approved_by, r.approved_at,
                        r.refund_amt, r.is_archived ? 'Yes' : 'No'
                    ].map(csvEsc).join(','));
                });

                var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'refunds_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });

            initializeTable();
        });
    </script>

@endsection
