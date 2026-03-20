@extends('layouts.vertical', ['title' => 'Kw Amazon', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        /* Your existing CSS styles remain unchanged */
        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 15px 18px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.2s ease;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 12px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead input::placeholder {
            color: #8e9ab4;
            font-style: italic;
        }

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
            transition: all 0.2s ease;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .edit-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        /* Additional styles remain unchanged */
        .form-control {
            border: 2px solid #E2E8F0;
            border-radius: 6px;
            padding: 0.75rem;
        }

        #status {
            display: block !important;
            position: static !important;
            width: 100% !important;
            height: auto !important;
            margin: auto !important;
        }

        .dt-buttons .btn {
            margin-left: 10px;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            padding: 1.5rem;
        }

        .modal-footer-gradient {
            background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%);
            border-top: 4px solid #E2E8F0;
            padding: 1.5rem;
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        /* Add these new styles for the plus button and multi-select functionality */
        .selection-controls {
            position: relative;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card-body:hover .selection-controls {
            opacity: 1;
        }

        .select-toggle-btn {
            background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(34, 197, 94, 0.3);
            transition: all 0.2s;
        }

        .select-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);
        }

        .select-toggle-text {
            margin-right: 10px;
            font-weight: 600;
            color: #333;
        }

        .checkbox-column {
            width: 40px;
            text-align: center;
            display: none;
        }

        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .row-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .selection-actions {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .selection-actions .btn {
            margin: 0 5px;
            border-radius: 20px;
            font-weight: 600;
            padding: 5px 15px;
        }

        .selection-count {
            color: white;
            font-weight: bold;
            margin-right: 15px;
            display: inline-block;
        }

        /* Add these styles to your CSS section */
        .field-operation {
            padding: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
            transition: all 0.2s;
        }

        .field-operation:hover {
            background-color: #e9ecef;
        }

        #addFieldBtn {
            border-radius: 20px;
            padding: 6px 15px;
        }

        #applyChangesBtn {
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            border: none;
        }

        .remove-field {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .custom-toast {
            z-index: 2000;
        }

        /* Add to your CSS section */
        .sku-tooltip {
            position: absolute;
            z-index: 9999;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            display: none;
            max-width: 180px;
            max-height: 180px;
        }

        .sku-tooltip img {
            max-width: 160px;
            max-height: 160px;
            border-radius: 6px;
            display: block;
        }

        .dil-label {
        display: inline-block;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: bold;
        color: white;
        border-radius: 4px;
        text-align: center;
        min-width: 40px;
    }
    .dil-label.red {
        background-color: #ff4d4d;
    }
    .dil-label.yellow {
        background-color: #ffcc00;
        color: black;
    }
    .dil-label.green {
        background-color: #00cc66;
    }
    .dil-label.pink {
        background-color: #ff66cc;
    }

    .time-navigation-group {
            display: flex;
            gap: 15px;
            padding: 10px;
            background-color: #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            width: fit-content;
            margin: 10px 0;
        }


         /* Play button */
        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        /* Pause button */
        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Navigation buttons */
        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        /* Button state colors - must come after hover styles */
        #play-auto.btn-success,
        #play-pause.btn-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning,
        #play-pause.btn-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger,
        #play-pause.btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #play-auto.btn-light,
        #play-pause.btn-light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }

        /* Ensure hover doesn't override state colors */
        #play-auto.btn-success:hover,
        #play-pause.btn-success:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning:hover,
        #play-pause.btn-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger:hover,
        #play-pause.btn-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Active state styling */
        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .time-navigation-group button {
                width: 36px;
                height: 36px;
            }

            .time-navigation-group button i {
                font-size: 1rem;
            }
        }



    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Kw Amazon',
        'sub_title' => 'Amazon master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                            <!-- Play Navigation Buttons -->
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                                <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle" title="Show all products" style="display: none;">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-light rounded-circle" title="Show all products">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="col-md-6 text-end">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control" placeholder="Search products...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
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

                    <!-- Process Selected Modal -->
                    <div class="modal fade" id="processSelectedModal" tabindex="-1"
                        aria-labelledby="processSelectedModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="processSelectedModalLabel">Process Selected Items</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Selected <span id="selectedItemCount" class="fw-bold">0</span> items. Choose fields
                                        to update:</p>

                                    <div id="fieldOperations">
                                        <div class="field-operation mb-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-3">
                                                    <select class="form-select field-selector">
                                                        <option value="">Select Field</option>
                                                        <option value="lp">LP</option>
                                                        <option value="cp">CP</option>
                                                        <option value="frght">FRGHT</option>
                                                        <option value="ship">SHIP</option>
                                                        <option value="label_qty">Label QTY</option>
                                                        <option value="wt_act">WT ACT</option>
                                                        <option value="wt_decl">WT DECL</option>
                                                        <option value="l">L</option>
                                                        <option value="w">W</option>
                                                        <option value="h">H</option>
                                                        <option value="status">Status</option>
                                                    </select>
                                                </div>
                                                <div class="col-3">
                                                    <select class="form-select operation-selector">
                                                        <option value="set">=</option>
                                                        <option value="add">+</option>
                                                        <option value="subtract">-</option>
                                                        <option value="multiply">×</option>
                                                        <option value="divide">÷</option>
                                                    </select>
                                                </div>
                                                <div class="col-4">
                                                    <input type="text" class="form-control field-value"
                                                        placeholder="Enter value">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-outline-danger remove-field">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button id="addFieldBtn" class="btn btn-outline-primary mt-2">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Changes will be applied to all selected items
                                    </div>

                                    <div id="batchUpdateResult" class="mt-3"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="applyChangesBtn">Apply
                                        Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selection actions bar -->
                    <div class="selection-actions" id="selectionActions">
                        <span class="selection-count">0 items selected</span>
                        <button class="btn btn-sm btn-light" id="cancelSelection">Cancel</button>
                        <button class="btn btn-sm btn-success" id="processSelected">Process Selected</button>
                    </div>

                    <div class="table-responsive">
                        <table id="row-callback-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" class="select-all-checkbox" id="selectAll">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search SKU">
                                    </th>
                                    <th>INV</th>
                                    <th>OV L30</th>
                                    <th>OV DIL</th>
                                    <th>RA</th>
                                    <th>NRA</th>
                                    <th>Running</th>
                                    <th>To Pause</th>
                                    <th>Paused</th>
                                    <!-- <th>IMP L30</th> -->
                                    <th>CLICKS L30</th>
                                    <!-- <th>CTR L30</th> -->
                                    <th>SPEND L30</th>
                                    <th>AD SALES L30</th>
                                    <th>CPC L2</th>
                                    <th>AD  SOLD L30</th>
                                    <th>ACOS L30</th>
                                    <th>CVR L30</th>
                                    <th>BGT CRNT</th>
                                    <th>UBL7</th>
                                    <th>UBL1</th>
                                    <!-- <th>L(2)</th> -->
                                    <!-- <th>Action</th> -->
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Product Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="skuImageTooltip" class="sku-tooltip"></div>
@endsection

@section('script')
    <script>
        window.userPermissions = @json($permissions ?? []);
        const productPermissions = window.userPermissions['product_lists'] || [];
    </script>
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
   
    <script>
        const campaignSheetData = @json($sheetData['amazon_kw_l30']);
        const amazonL1 = @json($sheetData['amazon_kw_l1']);
        console.log('l30 all data',campaignSheetData);
        

        function findSheetBySKU(sku) {
            if (!sku || !Array.isArray(campaignSheetData)) return null;

            const normalizedSKU = sku.trim().toUpperCase();

            return campaignSheetData.find(item => {
                const campaignName = (item['Campaigns'] || '').trim().toUpperCase();
                return campaignName === normalizedSKU;
            });
        }

        function findSheetL2BySKU(sku) {
            if (!sku || !Array.isArray(amazonL1)) return null;

            const normalizedSKU = sku.trim().toUpperCase();

            return amazonL1.find(item => {
                const campaignName = (item['Campaigns'] || '').trim().toUpperCase();
                return campaignName === normalizedSKU;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize with 75% zoom
            document.body.style.zoom = "75%";

            // Store the loaded data globally
            let tableData = [];
            let parentGroups = [];
            let currentParentIndex = -1;
            let fullData = [];
            const dbFlags = @json($skuFlags); 

            // Track selected items with both SKU and ID
            let selectedItems = {}; // Format: { sku: { id: 123, checked: true } }

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Initialize all components
            initializeTable();

            // Centralized AJAX request function with CSRF protection
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                // Include CSRF token in request body for POST/PUT/PATCH/DELETE
                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Load product data from server
            function loadData() {
                makeRequest('/product-master-data-view', 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            fullData = [...tableData];
                            parentGroups = [...new Set(tableData.map(item => item.Parent))];
                            currentParentIndex = -1;
                            renderTable(fullData);
                            renderTable(tableData);
                            updateParentOptions();
                            setupParentNavigation();
                            // renderTable(tableData);
                            // updateParentOptions();

                            // Add this block to update counts
                            const parentSet = new Set();
                            let skuCount = 0;
                            tableData.forEach(item => {
                                if (item.Parent) parentSet.add(item.Parent);
                                // Only count SKUs that do NOT contain 'PARENT'
                                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                                    skuCount++;
                            });
                            document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                            document.getElementById('skuCount').textContent = `(${skuCount})`;

                        } else {
                            showError('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        showError('Failed to load product data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            function getDilLabelHTML(dilValue) {
                const val = parseFloat(dilValue);
                if (isNaN(val)) return '-';

                let className = '';
                if (val < 16.66) className = 'dil-label red';
                else if (val < 25) className = 'dil-label yellow';
                else if (val < 50) className = 'dil-label green';
                else className = 'dil-label pink';

                return `<div class="${className}">${Math.round(val)}%</div>`;
            }


            // Render the table with data
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="17" class="text-center">No products found</td></tr>';
                    return;
                }

                const hasEditPermission = productPermissions.includes('edit');
                const hasDeletePermission = productPermissions.includes('delete');
                const selectionMode = document.querySelector('.checkbox-column').style.display === 'table-cell';

                let displayItems = [...data];

                if (selectionMode && Object.keys(selectedItems).length > 0) {
                    const selectedItemsList = tableData.filter(item => selectedItems[item.SKU]);
                    const combinedItems = [...data];

                    selectedItemsList.forEach(item => {
                        if (!data.some(d => d.SKU === item.SKU)) {
                            combinedItems.push(item);
                        }
                    });

                    displayItems = combinedItems;
                }

                if (selectionMode) {
                    displayItems.sort((a, b) => {
                        const aSelected = selectedItems[a.SKU];
                        const bSelected = selectedItems[b.SKU];
                        if (aSelected && !bSelected) return -1;
                        if (!aSelected && bSelected) return 1;
                        return 0;
                    });
                }

                const parentTotals = {};
                data.forEach(item => {
                    if (item.Parent && !String(item.SKU).toUpperCase().includes('PARENT')) {
                        if (!parentTotals[item.Parent]) {
                            parentTotals[item.Parent] = { inv: 0, ovl30: 0 };
                        }
                        parentTotals[item.Parent].inv += Number(item.shopify_inv) || 0;
                        parentTotals[item.Parent].ovl30 += Number(item.shopify_quantity) || 0;
                    }
                });

                const parentSkuMap = {};
                displayItems.forEach(item => {
                    if (item.SKU && item.SKU.toUpperCase().includes('PARENT') && item.Parent) {
                        parentSkuMap[item.Parent.trim()] = item.SKU.trim();
                    }
                });

                displayItems.forEach(item => {
                    const row = document.createElement('tr');

                    const isParentRow = item.SKU && item.SKU.toUpperCase().includes('PARENT');

                    if (isParentRow) {
                        row.style.backgroundColor = 'rgba(13, 110, 253, 0.2)';
                        row.style.fontWeight = '500';

                        const totals = parentTotals[item.Parent] || { inv: 0, ovl30: 0 };
                        let dil = '-';
                        if (totals.inv && totals.inv !== 0 && !isNaN(totals.ovl30)) {
                            dil = Math.round((totals.ovl30 / totals.inv) * 100) + '%';
                        }

                        const sku = item.SKU?.trim().toUpperCase() || '';
                        // const campaignSheetSku = sku.replace(/\s+PT$/, '');
                        const sheet = findSheetBySKU(sku);
                        const sheetL1 = findSheetL2BySKU(sku);

                        const rawSku = item.SKU || item.sku || '';
                        const skus = rawSku.trim().toLowerCase(); 
                        const flags = dbFlags[skus] || {};
                        const raChecked = flags.ra == 1 ? 'checked' : '';
                        const nraChecked = flags.nra == 1 ? 'checked' : '';
                        const runningChecked = flags.running == 1 ? 'checked' : '';
                        const toPauseChecked = flags.to_pause == 1 ? 'checked' : '';
                        const pausedChecked = flags.paused == 1 ? 'checked' : '';

                        let clicksl30 = '-', spendl30 = '-', adSalesl30 = '-', adSoldl30 = '-', bgtCrnt = '-', cpcl2 = '-', acosl30 = '-', cvrl30 = '-';

                        if (sheet) {
                            // impl30 = sheet.Impressions ?? '-';
                            clicksl30 = sheet.Clicks ?? '-';
                            spendl30 = sheet.Spend ?? '-';
                            adSalesl30 = sheet.Sales ?? '-';
                            adSoldl30 = sheet.Orders ?? '-';
                            // acosl30 = sheet.ACOS ?? '-';
                            bgtCrnt = sheet.Budget ?? '-';

                            if (clicksl30 > 0) {
                                cvrl30 = Math.round((adSoldl30 / clicksl30) * 100) + '%';
                            }

                            if (!isNaN(sheet.ACOS)) {
                                acosl30 = Math.round(sheet.ACOS) + '%';
                            }
                        }

                        if (sheetL1) {
                            cpcl2 = sheetL1.CPC ?? '-';
                        }

                        row.innerHTML = `
                            <td>${escapeHtml(item.Parent) || '-'}</td>
                            <td>${escapeHtml(item.SKU) || '-'}</td>
                            <td><b>${totals.inv}</b></td>
                            <td><b>${totals.ovl30}</b></td>
                            <td>${getDilLabelHTML(dil)}</td>
                            <td><input type="checkbox" class="flag-checkbox" data-sku="${sku}" data-field="ra" ${raChecked}></td>
                            <td><input type="checkbox" class="flag-checkbox" data-sku="${sku}" data-field="nra" ${nraChecked}></td>
                            <td><input type="checkbox" class="flag-checkbox" data-sku="${sku}" data-field="running" ${runningChecked}></td>
                            <td><input type="checkbox" class="flag-checkbox" data-sku="${sku}" data-field="to_pause" ${toPauseChecked}></td>
                            <td><input type="checkbox" class="flag-checkbox" data-sku="${sku}" data-field="paused" ${pausedChecked}></td>
                            <td class="text-center">${clicksl30}</td>
                            <td class="text-center">${spendl30}</td>
                            <td class="text-center">${adSalesl30}</td>
                            <td class="text-center">${cpcl2}</td>
                            <td class="text-center">${adSoldl30}</td>
                            <td class="text-center">${acosl30}</td>
                            <td class="text-center">${cvrl30}</td>
                            <td class="text-center">${bgtCrnt}</td>
                            <td> - </td>
                            <td> - </td>
                        `;
                        tbody.appendChild(row);
                        return;
                    }

                    // Child row
                    if (selectionMode) {
                        const isChecked = selectedItems[item.SKU] ? 'checked' : '';
                        row.innerHTML = `
                            <td class="checkbox-cell" style="text-align: center;">
                                <input type="checkbox" class="row-checkbox" 
                                    data-sku="${escapeHtml(item.SKU || '')}" 
                                    data-id="${escapeHtml(item.id || '')}" ${isChecked}>
                            </td>
                        `;
                    }

                    const l = parseFloat(item.l);
                    const w = parseFloat(item.w);
                    const h = parseFloat(item.h);
                    let cbm = '', frght = '';
                    if (!isNaN(l) && !isNaN(w) && !isNaN(h)) {
                        cbm = (((l * 2.54) * (w * 2.54) * (h * 2.54)) / 1000000).toFixed(4);
                        frght = (cbm * 200).toFixed(2);
                    }

                    let inv = parseFloat(item.shopify_inv);
                    let ovl30 = parseFloat(item.shopify_quantity);
                    let dil = '-';
                    if (!isNaN(inv) && inv !== 0 && !isNaN(ovl30)) {
                        dil = Math.round((ovl30 / inv) * 100) + '%';
                    }

                    // Child rows: show only dash for campaign columns
                    row.innerHTML += `
                        <td>${escapeHtml(item.Parent) || '-'}</td>
                        <td>
                            <span class="sku-hover" 
                                data-sku="${escapeHtml(item.SKU) || ''}"
                                data-image="${item.image_path ? item.image_path : ''}">
                                ${escapeHtml(item.SKU) || '-'}
                            </span>
                        </td>
                        <td>${escapeHtml(item.shopify_inv) || '-'}</td>
                        <td>${escapeHtml(item.shopify_quantity) || '-'}</td>
                        <td>${getDilLabelHTML(dil)}</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                        <td class="text-center">-</td>
                    `;
                    tbody.appendChild(row);
                });
                setupParentNavigation(); 

                if (hasEditPermission) {
                    setupEditButtons();
                    setupDeleteButtons();
                }
            }


            // Initialize table components
            function initializeTable() {
                loadData();
                setupSearch();
                setupHeaderColumnSearch();
                setupProgressModal();
                setupBatchProcessing();
            }

            $(document).off('change', '.flag-checkbox').on('change', '.flag-checkbox', function () {
                const checkbox = $(this);
                const field = checkbox.data('field');
                const sku = checkbox.data('sku');
                const value = checkbox.is(':checked') ? 1 : 0;

                if (!sku || !field) {
                    console.warn('Missing SKU or field:', { sku, field });
                    return;
                }

                $.ajax({
                    url: '/update-checkbox-flag', 
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        field: field,
                        value: value
                    },
                    success: function (response) {
                        console.log(`${field} updated for ${sku}: ${value}`);
                    },
                    error: function (xhr) {
                        console.error(`Failed to update ${field} for ${sku}`, xhr.responseText);
                    }
                });
            });
            

            function setupParentNavigation() {
                $('#play-auto').off().on('click', function () {
                    if (!parentGroups || parentGroups.length === 0) {
                        console.error("🚫 No parent groups found.");
                        return;
                    }
                    currentParentIndex = 0;
                    showGroup(currentParentIndex);
                });

                $('#play-forward').off().on('click', function () {
                    if (currentParentIndex < parentGroups.length - 1) {
                        currentParentIndex++;
                        showGroup(currentParentIndex);
                    }
                });

                $('#play-backward').off().on('click', function () {
                    if (currentParentIndex > 0) {
                        currentParentIndex--;
                        showGroup(currentParentIndex);
                    }
                });

                $('#play-pause').off().on('click', function () {
                    currentParentIndex = -1;
                    renderTable(fullData);
                });
            }

            function showGroup(index) {
                if (index >= 0 && index < parentGroups.length) {
                    const parent = parentGroups[index];
                    const groupItems = tableData.filter(item => item.Parent === parent);
                    console.log("📂 Showing Parent:", parent, "Index:", index);
                    renderTable(groupItems);
                } else {
                    console.warn("⛔ Invalid group index:", index);
                }
            }

            // Setup search functionality
            function setupSearch() {
                const searchInput = document.getElementById('customSearch');
                const clearButton = document.getElementById('clearSearch');

                searchInput.addEventListener('input', debounce(function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    const parentValue = document.getElementById('parentSearch').value.toLowerCase()
                        .trim();
                    const skuValue = document.getElementById('skuSearch').value.toLowerCase().trim();

                    let filteredData = [...tableData];

                    if (searchTerm) {
                        filteredData = filteredData.filter(item =>
                            Object.values(item).some(value =>
                                String(value).toLowerCase().includes(searchTerm)
                            )
                        );
                    }

                    if (parentValue) {
                        filteredData = filteredData.filter(item =>
                            (item.Parent || '').toLowerCase().includes(parentValue)
                        );
                    }

                    if (skuValue) {
                        filteredData = filteredData.filter(item =>
                            (item.SKU || '').toLowerCase().includes(skuValue)
                        );
                    }

                    renderTable(filteredData);
                }, 300));

                clearButton.addEventListener('click', function() {
                    searchInput.value = '';
                    document.getElementById('parentSearch').value = '';
                    document.getElementById('skuSearch').value = '';
                    renderTable(tableData);
                });
            }

            // Setup header column search
            function setupHeaderColumnSearch() {
                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');

                function applyFilters() {
                    const parentValue = parentSearch.value.toLowerCase().trim();
                    const skuValue = skuSearch.value.toLowerCase().trim();
                    const globalValue = document.getElementById('customSearch').value.toLowerCase().trim();

                    let filteredData = [...tableData];

                    if (parentValue) {
                        filteredData = filteredData.filter(item =>
                            (item.Parent || '').toLowerCase().includes(parentValue)
                        );
                    }

                    if (skuValue) {
                        filteredData = filteredData.filter(item =>
                            (item.SKU || '').toLowerCase().includes(skuValue)
                        );
                    }

                    if (globalValue) {
                        filteredData = filteredData.filter(item =>
                            Object.values(item).some(value =>
                                String(value).toLowerCase().includes(globalValue)
                            )
                        );
                    }

                    renderTable(filteredData);
                }

                parentSearch.addEventListener('input', debounce(applyFilters, 300));
                skuSearch.addEventListener('input', debounce(applyFilters, 300));
            }

           
           

            // Modify getFormData to use FormData for file upload
            function getFormData() {
                const formElement = document.getElementById('addProductForm');
                const formData = new FormData(formElement);

                // Add all fields as before
                formData.append('parent', document.getElementById('parent').value || '');
                formData.append('sku', document.getElementById('sku').value);

                // Build Values JSON
                const values = {
                    lp: document.getElementById('lp').value || null,
                    cp: document.getElementById('cp').value || null,
                    frght: document.getElementById('freght').value || null,
                    lps: document.getElementById('lps').value || null,
                    ship: document.getElementById('ship').value || null,
                    label_qty: document.getElementById('labelQty').value || null,
                    wt_act: document.getElementById('wtAct').value || null,
                    wt_decl: document.getElementById('wtDecl').value || null,
                    l: document.getElementById('l').value || null,
                    w: document.getElementById('w').value || null,
                    h: document.getElementById('h').value || null,
                    cbm: document.getElementById('cbm').value || null,
                    dc: document.getElementById('dc').value || null,
                    l2_url: document.getElementById('l2Url').value || null,
                    pcs_per_box: document.getElementById('pcbox').value || null,
                    l1: document.getElementById('l1').value || null,
                    b: document.getElementById('b').value || null,
                    h1: document.getElementById('h1').value || null,
                    weight: document.getElementById('weight').value || null,
                    msrp: document.getElementById('msrp').value || null,
                    map: document.getElementById('map').value || null,
                    status: document.getElementById('status').value || null,
                    unit: document.getElementById('unit').value || null,
                    upc: document.getElementById('upc').value || null,
                };

                formData.append('Values', JSON.stringify(values));
                // The image file is already included by <input name="image">

                return formData;
            }


            // Update parent options in datalist
            function updateParentOptions() {
                const parentOptions = document.getElementById('parentOptions');
                parentOptions.innerHTML = '';

                const parentSKUs = new Set();
                tableData.forEach(item => {
                    // Only add Parent values that do NOT contain 'PARENT'
                    if (item.Parent && !item.Parent.toUpperCase().includes('PARENT')) {
                        parentSKUs.add(item.Parent);
                    }
                });

                parentSKUs.forEach(sku => {
                    const option = document.createElement('option');
                    option.value = sku;
                    parentOptions.appendChild(option);
                });
            }

            // Setup edit buttons
            function setupEditButtons() {
                document.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const sku = this.getAttribute('data-sku');
                        const product = tableData.find(p => p.SKU === sku);
                        if (product) {
                            editProduct(product);
                        }
                    });
                });
            }


            // Reset the product form
            function resetProductForm() {
                document.getElementById('addProductForm').reset();
                document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                document.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
                document.getElementById('form-errors').innerHTML = '';
            }

            // Initialize progress modal
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

           

            // Add checkboxes to existing rows when entering selection mode
            function addCheckboxesToRows() {
                const rows = document.querySelectorAll('#table-body tr');
                rows.forEach(row => {
                    if (!row.querySelector('.row-checkbox')) {
                        const firstCell = row.cells[0];
                        const skuCell = row.cells[1] || row.cells[0];
                        const sku = skuCell.textContent.trim();

                        // Find the item in tableData to get the ID
                        const item = tableData.find(item => item.SKU === sku);
                        const id = item ? item.id : '';

                        const checkboxCell = document.createElement('td');
                        checkboxCell.className = 'checkbox-cell';
                        checkboxCell.style.textAlign = 'center';

                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'row-checkbox';
                        checkbox.dataset.sku = sku;
                        checkbox.dataset.id = id;

                        if (selectedItems[sku]) {
                            checkbox.checked = true;
                        }

                        checkboxCell.appendChild(checkbox);
                        row.insertBefore(checkboxCell, firstCell);
                    }
                });
            }

            // Get current filter values
            function getCurrentFilters() {
                return {
                    parent: document.getElementById('parentSearch').value.toLowerCase().trim(),
                    sku: document.getElementById('skuSearch').value.toLowerCase().trim(),
                    global: document.getElementById('customSearch').value.toLowerCase().trim()
                };
            }

            // Apply filters to data
            function applyFiltersToData(filters) {
                let filteredData = [...tableData];

                if (filters.parent) {
                    filteredData = filteredData.filter(item =>
                        (item.Parent || '').toLowerCase().includes(filters.parent)
                    );
                }

                if (filters.sku) {
                    filteredData = filteredData.filter(item =>
                        (item.SKU || '').toLowerCase().includes(filters.sku)
                    );
                }

                if (filters.global) {
                    filteredData = filteredData.filter(item =>
                        Object.values(item).some(value =>
                            String(value).toLowerCase().includes(filters.global)
                        )
                    );
                }

                return filteredData;
            }



            // Handle the batch processing of selected items
            function setupBatchProcessing() {
                const processSelectedModal = new bootstrap.Modal(document.getElementById('processSelectedModal'));
                const addFieldBtn = document.getElementById('addFieldBtn');
                const fieldOperations = document.getElementById('fieldOperations');
                const applyChangesBtn = document.getElementById('applyChangesBtn');
                const selectedItemCount = document.getElementById('selectedItemCount');
                const batchUpdateResult = document.getElementById('batchUpdateResult');

                // List of all available fields
                const allFields = [{
                        value: 'lp',
                        text: 'LP'
                    },
                    {
                        value: 'cp',
                        text: 'CP'
                    },
                    {
                        value: 'frght',
                        text: 'FRGHT'
                    },
                    {
                        value: 'ship',
                        text: 'SHIP'
                    },
                    {
                        value: 'label_qty',
                        text: 'Label QTY'
                    },
                    {
                        value: 'wt_act',
                        text: 'WT ACT'
                    },
                    {
                        value: 'wt_decl',
                        text: 'WT DECL'
                    },
                    {
                        value: 'l',
                        text: 'L'
                    },
                    {
                        value: 'w',
                        text: 'W'
                    },
                    {
                        value: 'h',
                        text: 'H'
                    },
                    {
                        value: 'status',
                        text: 'Status'
                    }
                ];

                // Open modal when Process Selected is clicked
                document.getElementById('processSelected').addEventListener('click', function() {
                    const selectedCount = Object.keys(selectedItems).length;
                    if (selectedCount === 0) {
                        alert('No items selected');
                        return;
                    }

                    // Reset the form
                    resetBatchForm();

                    // Update selected count
                    selectedItemCount.textContent = selectedCount;

                    // Show the modal
                    processSelectedModal.show();
                });

                // Add a new field operation row
                addFieldBtn.addEventListener('click', function() {
                    addFieldRow();
                    updateFieldOptions();
                });

                // Handle remove field button clicks using event delegation
                fieldOperations.addEventListener('change', function(e) {
                    if (e.target.classList.contains('field-selector')) {
                        updateFieldOptions();

                        // If status is selected, change input to dropdown and hide operation
                        const selectedField = e.target.value;
                        const row = e.target.closest('.field-operation');
                        const valueInput = row.querySelector('.field-value');
                        const operationSelector = row.querySelector('.operation-selector');

                        if (selectedField === 'status') {
                            // Replace text input with status dropdown
                            valueInput.outerHTML = `
                            <select class="form-select field-value">
                                <option value="">Select Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="DC">DC</option>
                                <option value="upcoming">Upcoming</option>
                                <option value="2BDC">2BDC</option>
                            </select>
                            `;

                            // Hide operation selector for status and set it to "set" (=)
                            operationSelector.style.display = 'none';
                            operationSelector.value = 'set';
                        } else {
                            // For non-status fields, show operation selector
                            operationSelector.style.display = '';

                            // Replace dropdown with text input if it's not status
                            if (valueInput.tagName === 'SELECT' && selectedField !== 'status') {
                                valueInput.outerHTML = `
                                <input type="text" class="form-control field-value" placeholder="Enter value">
                                `;
                            }
                        }
                    }
                });

                // Apply changes to selected items
                applyChangesBtn.addEventListener('click', async function() {
                    // Validate form

                    const operations = getFieldOperations();
                    if (operations.length === 0) {
                        showBatchResult('warning', 'Please select at least one field to update');
                        return;
                    }

                    // Prepare data for update with item IDs
                    const updateData = {
                        // Convert selectedItems to array of {sku, id} objects
                        items: Object.entries(selectedItems).map(([sku, item]) => {
                            // Find the full item data to get the ID
                            const fullItem = tableData.find(i => i.SKU === sku);
                            return {
                                sku: sku,
                                id: fullItem ? fullItem.id : null
                            };
                        }),
                        operations: operations
                    };

                    // Disable button and show loading
                    applyChangesBtn.disabled = true;
                    applyChangesBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                    try {
                        // Send to server
                        const response = await makeRequest('/product-master/batch-update', 'POST',
                            updateData);
                        const result = await response.json();

                        if (!response.ok) {
                            throw new Error(result.message || 'Failed to update products');
                        }



                        // Show success message
                        showBatchResult('success',
                            `Successfully updated ${Object.keys(selectedItems).length} products`);

                        // After successful update, refresh the table
                        setTimeout(() => {
                            processSelectedModal.hide();
                            loadData();
                        }, 1500);

                    } catch (error) {
                        console.error("Error during batch update:", error);
                        showBatchResult('danger', `Error: ${error.message}`);
                    } finally {
                        applyChangesBtn.disabled = false;
                        applyChangesBtn.innerHTML = 'Apply Changes';
                    }
                });

                // Helper function to add a new field row
                function addFieldRow() {
                    const row = document.createElement('div');
                    row.className = 'field-operation mb-3';
                    row.innerHTML = `
                        <div class="row g-2 align-items-center">
                            <div class="col-3">
                                <select class="form-select field-selector">
                                    <option value="">Select Field</option>
                                    ${generateFieldOptions([])}
                                </select>
                            </div>
                            <div class="col-3">
                                <select class="form-select operation-selector">
                                    <option value="set">=</option>
                                    <option value="add">+</option>
                                    <option value="subtract">-</option>
                                    <option value="multiply">×</option>
                                    <option value="divide">÷</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <input type="text" class="form-control field-value" placeholder="Enter value">
                            </div>
                            <div class="col-2">
                                <button type="button" class="btn btn-outline-danger remove-field">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    fieldOperations.appendChild(row);
                }

                // Helper function to reset the batch form
                function resetBatchForm() {
                    fieldOperations.innerHTML = '';
                    addFieldRow();
                    batchUpdateResult.innerHTML = '';
                }

                // Helper function to get field operations from the form
                function getFieldOperations() {
                    const operations = [];
                    const rows = fieldOperations.querySelectorAll('.field-operation');

                    rows.forEach(row => {
                        const field = row.querySelector('.field-selector').value;
                        const operation = row.querySelector('.operation-selector').value;
                        const value = row.querySelector('.field-value').value;

                        if (field && value) {
                            operations.push({
                                field: field,
                                operation: operation,
                                value: value
                            });
                        }
                    });

                    return operations;
                }

                // Helper function to show result messages
                function showBatchResult(type, message) {
                    batchUpdateResult.innerHTML = `
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                }

                // Helper function to generate field options HTML, excluding already selected fields
                function generateFieldOptions(excludeFields) {
                    return allFields
                        .filter(field => !excludeFields.includes(field.value))
                        .map(field => `<option value="${field.value}">${field.text}</option>`)
                        .join('');
                }

                // Helper function to update field options in all selectors
                function updateFieldOptions() {
                    const rows = fieldOperations.querySelectorAll('.field-operation');
                    const selectedFields = getSelectedFields();

                    rows.forEach(row => {
                        const selector = row.querySelector('.field-selector');
                        const currentValue = selector.value;

                        // Get fields to exclude (all selected fields except current one)
                        const fieldsToExclude = selectedFields.filter(field => field !== currentValue);

                        // Store current selection
                        const currentSelection = selector.value;

                        // Update options, excluding already selected fields
                        selector.innerHTML = `
                            <option value="">Select Field</option>
                            ${generateFieldOptions(fieldsToExclude)}
                        `;

                        // Restore current selection
                        selector.value = currentSelection;
                    });

                    // Disable/enable add field button based on available options
                    const unusedFields = allFields.filter(field => !selectedFields.includes(field.value));
                    addFieldBtn.disabled = unusedFields.length === 0;
                }

                // Helper function to get currently selected fields
                function getSelectedFields() {
                    const selectedFields = [];
                    const selectors = fieldOperations.querySelectorAll('.field-selector');

                    selectors.forEach(selector => {
                        if (selector.value) {
                            selectedFields.push(selector.value);
                        }
                    });

                    return selectedFields;
                }
            }


            // Utility functions
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

            function setupDeleteButtons() {
                document.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const sku = this.getAttribute('data-sku');
                        // Use Bootstrap modal for confirmation instead of window.confirm
                        const confirmModal = document.createElement('div');
                        confirmModal.innerHTML = `
                            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0" style="border-radius: 18px; overflow: hidden;">
                                <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff0000 100%); color: #fff;">
                                    <div class="d-flex align-items-center w-100">
                                    <div class="me-3" style="font-size: 2.5rem;">
                                        <i class="fas fa-exclamation-triangle fa-shake"></i>
                                    </div>
                                    <div>
                                        <h5 class="modal-title mb-0" id="deleteConfirmModalLabel" style="font-weight: 800; letter-spacing: 1px;">
                                        Delete Product?
                                        </h5>
                                        <small class="text-white-50">This action cannot be undone!</small>
                                    </div>
                                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                </div>
                                <div class="modal-body text-center py-4">
                                    <div class="mb-3" style="font-size: 1.2rem;">
                                    Are you sure you want to <span class="fw-bold text-danger">delete</span> product<br>
                                    <span class="badge bg-danger fs-6 px-3 py-2 mt-2" style="font-size:1.1rem;">SKU: ${escapeHtml(sku)}</span>?
                                    </div>
                                    <div class="mb-2 text-warning" style="font-size: 1rem;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    This will move the product to trash (soft delete).
                                    </div>
                                </div>
                                <div class="modal-footer justify-content-center" style="background: #fff;">
                                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancel
                                    </button>
                                    <button type="button" class="btn btn-danger px-4" id="confirmDeleteBtn">
                                    <i class="fas fa-trash me-1"></i>Yes, Delete
                                    </button>
                                </div>
                                </div>
                            </div>
                            </div>
                        `;
                        document.body.appendChild(confirmModal);

                        const modal = new bootstrap.Modal(confirmModal.querySelector(
                            '#deleteConfirmModal'));
                        modal.show();

                        confirmModal.querySelector('#confirmDeleteBtn').addEventListener('click',
                            () => {
                                makeRequest('/product_master/delete', 'DELETE', {
                                        ids: [id]
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            showToast('success', data.message ||
                                                'Product deleted successfully!');
                                            loadData();
                                        } else {
                                            showToast('danger', data.message ||
                                                'Delete failed');
                                        }
                                    })
                                    .catch(() => {
                                        showToast('danger', 'Delete failed');
                                    })
                                    .finally(() => {
                                        modal.hide();
                                        setTimeout(() => confirmModal.remove(), 500);
                                    });
                            });

                        // Remove modal from DOM after hiding
                        confirmModal.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
                            btn.addEventListener('click', () => setTimeout(() =>
                                confirmModal.remove(), 500));
                        });
                    });
                });
            }

            // Add this helper function for toast notifications (place it with your other utility functions)
            function showToast(type, message) {
                // Remove any existing toast
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                toast.className =
                    `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${escapeHtml(message)}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            document.addEventListener('mouseover', function(e) {
                const target = e.target.closest('.sku-hover');
                const tooltip = document.getElementById('skuImageTooltip');
                if (target && tooltip) {
                    const image = target.getAttribute('data-image');
                    if (image) {
                        tooltip.innerHTML = `<img src="${image}" alt="Product Image">`;
                        tooltip.style.display = 'block';
                    } else {
                        tooltip.style.display = 'none';
                    }
                }
            });
            document.addEventListener('mousemove', function(e) {
                const tooltip = document.getElementById('skuImageTooltip');
                if (tooltip && tooltip.style.display === 'block') {
                    tooltip.style.left = (e.pageX + 20) + 'px';
                    tooltip.style.top = (e.pageY + 10) + 'px';
                }
            });
            document.addEventListener('mouseout', function(e) {
                const target = e.target.closest('.sku-hover');
                const tooltip = document.getElementById('skuImageTooltip');
                if (target && tooltip) {
                    tooltip.style.display = 'none';
                }
            });
        });
    </script>
@endsection
