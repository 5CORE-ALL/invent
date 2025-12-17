@extends('layouts.vertical', ['title' => 'Competitors Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
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

        .delete-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .delete-btn:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(220, 53, 69, 0.2);
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

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Competitors Master',
        'sub_title' => 'Competitors Master Analysis',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control"
                                    placeholder="Search competitors...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <button type="button" class="btn btn-primary me-2" id="addCompetitorsBtn">
                                <i class="fas fa-plus me-1"></i> Add Competitors Data
                            </button>
                            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#importCompetitorsModal">
                                <i class="fas fa-upload me-1"></i> Import Excel
                            </button>
                            <button type="button" class="btn btn-success" id="downloadExcel">
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                        </div>
                    </div>

                    <!-- Import Modal -->
                    <div class="modal fade" id="importCompetitorsModal" tabindex="-1" aria-labelledby="importCompetitorsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="importCompetitorsModalLabel">
                                        <i class="fas fa-upload me-2"></i>Import Competitors Data
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Instructions:</strong>
                                        <ol class="mb-0 mt-2">
                                            <li>Download the sample file below</li>
                                            <li>Fill in the competitors data (Brand 1, Link 1, Brand 2, Link 2, ... Brand 5, Link 5)</li>
                                            <li>Upload the completed file</li>
                                        </ol>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleCompetitorsBtn">
                                            <i class="fas fa-download me-2"></i>Download Sample File
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                        <label for="competitorsImportFile" class="form-label fw-bold">Select Excel File</label>
                                        <input type="file" class="form-control" id="competitorsImportFile" accept=".xlsx,.xls,.csv">
                                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                                        <div id="competitorsFileError" class="text-danger mt-2" style="display: none;"></div>
                                    </div>

                                    <div id="competitorsImportProgress" class="progress mb-3" style="display: none;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                    </div>

                                    <div id="competitorsImportResult" class="alert" style="display: none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="importCompetitorsBtn" disabled>
                                        <i class="fas fa-upload me-2"></i>Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="competitors-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent</span>
                                            <span id="parentCount">(0)</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                            <span id="skuCount">(0)</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search SKU">
                                    </th>
                                    <th>Status</th>
                                    <th>INV</th>
                                    <th>
                                        <div>Brand 1 <span id="brand1MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrand1" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Link 1 <span id="link1MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLink1" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Brand 2 <span id="brand2MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrand2" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Link 2 <span id="link2MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLink2" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Brand 3 <span id="brand3MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrand3" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Link 3 <span id="link3MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLink3" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Brand 4 <span id="brand4MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrand4" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Link 4 <span id="link4MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLink4" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Brand 5 <span id="brand5MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrand5" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Link 5 <span id="link5MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterLink5" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>Action</th>
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
                        <div class="loading-text">Loading Competitors Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Competitors Master Modal -->
    <div class="modal fade" id="addCompetitorsModal" tabindex="-1" aria-labelledby="addCompetitorsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompetitorsModalLabel">Add Competitors Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCompetitorsForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addCompetitorsSku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <select class="form-control" id="addCompetitorsSku" name="sku" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addBrand1" class="form-label">Brand 1</label>
                                <input type="text" class="form-control" id="addBrand1" name="brand_1" placeholder="Enter Brand 1">
                            </div>
                            <div class="col-md-6">
                                <label for="addLink1" class="form-label">Link 1</label>
                                <input type="text" class="form-control" id="addLink1" name="link_1" placeholder="Enter Link 1">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addBrand2" class="form-label">Brand 2</label>
                                <input type="text" class="form-control" id="addBrand2" name="brand_2" placeholder="Enter Brand 2">
                            </div>
                            <div class="col-md-6">
                                <label for="addLink2" class="form-label">Link 2</label>
                                <input type="text" class="form-control" id="addLink2" name="link_2" placeholder="Enter Link 2">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addBrand3" class="form-label">Brand 3</label>
                                <input type="text" class="form-control" id="addBrand3" name="brand_3" placeholder="Enter Brand 3">
                            </div>
                            <div class="col-md-6">
                                <label for="addLink3" class="form-label">Link 3</label>
                                <input type="text" class="form-control" id="addLink3" name="link_3" placeholder="Enter Link 3">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addBrand4" class="form-label">Brand 4</label>
                                <input type="text" class="form-control" id="addBrand4" name="brand_4" placeholder="Enter Brand 4">
                            </div>
                            <div class="col-md-6">
                                <label for="addLink4" class="form-label">Link 4</label>
                                <input type="text" class="form-control" id="addLink4" name="link_4" placeholder="Enter Link 4">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="addBrand5" class="form-label">Brand 5</label>
                                <input type="text" class="form-control" id="addBrand5" name="brand_5" placeholder="Enter Brand 5">
                            </div>
                            <div class="col-md-6">
                                <label for="addLink5" class="form-label">Link 5</label>
                                <input type="text" class="form-control" id="addLink5" name="link_5" placeholder="Enter Link 5">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddCompetitorsBtn">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            // Load competitors data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/competitors-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                            setupSearch();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load competitors data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="16" class="text-center">No competitors data found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');

                    // Image column
                    const imageCell = document.createElement('td');
                    imageCell.innerHTML = item.image_path 
                        ? `<img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`
                        : '-';
                    row.appendChild(imageCell);

                    // Parent column
                    const parentCell = document.createElement('td');
                    parentCell.textContent = escapeHtml(item.Parent) || '-';
                    row.appendChild(parentCell);

                    // SKU column
                    const skuCell = document.createElement('td');
                    skuCell.textContent = escapeHtml(item.SKU) || '-';
                    row.appendChild(skuCell);

                    // Status column
                    const statusCell = document.createElement('td');
                    statusCell.textContent = escapeHtml(item.status) || '-';
                    row.appendChild(statusCell);

                    // INV column
                    const invCell = document.createElement('td');
                    if (item.shopify_inv === 0 || item.shopify_inv === "0") {
                        invCell.textContent = "0";
                    } else if (item.shopify_inv === null || item.shopify_inv === undefined || item.shopify_inv === "") {
                        invCell.textContent = "-";
                    } else {
                        invCell.textContent = escapeHtml(item.shopify_inv);
                    }
                    row.appendChild(invCell);

                    // Brand 1 column
                    const brand1Cell = document.createElement('td');
                    brand1Cell.textContent = escapeHtml(item.brand_1) || '-';
                    row.appendChild(brand1Cell);

                    // Link 1 column
                    const link1Cell = document.createElement('td');
                    const link1Value = item.link_1 || '';
                    if (link1Value && link1Value.trim()) {
                        const cleanUrl = link1Value.trim();
                        if (cleanUrl.match(/^https?:\/\//i)) {
                            link1Cell.innerHTML = `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(cleanUrl)}">${escapeHtml(cleanUrl.length > 30 ? cleanUrl.substring(0, 30) + '...' : cleanUrl)}</a>`;
                        } else {
                            link1Cell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        link1Cell.textContent = '-';
                    }
                    row.appendChild(link1Cell);

                    // Brand 2 column
                    const brand2Cell = document.createElement('td');
                    brand2Cell.textContent = escapeHtml(item.brand_2) || '-';
                    row.appendChild(brand2Cell);

                    // Link 2 column
                    const link2Cell = document.createElement('td');
                    const link2Value = item.link_2 || '';
                    if (link2Value && link2Value.trim()) {
                        const cleanUrl = link2Value.trim();
                        if (cleanUrl.match(/^https?:\/\//i)) {
                            link2Cell.innerHTML = `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(cleanUrl)}">${escapeHtml(cleanUrl.length > 30 ? cleanUrl.substring(0, 30) + '...' : cleanUrl)}</a>`;
                        } else {
                            link2Cell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        link2Cell.textContent = '-';
                    }
                    row.appendChild(link2Cell);

                    // Brand 3 column
                    const brand3Cell = document.createElement('td');
                    brand3Cell.textContent = escapeHtml(item.brand_3) || '-';
                    row.appendChild(brand3Cell);

                    // Link 3 column
                    const link3Cell = document.createElement('td');
                    const link3Value = item.link_3 || '';
                    if (link3Value && link3Value.trim()) {
                        const cleanUrl = link3Value.trim();
                        if (cleanUrl.match(/^https?:\/\//i)) {
                            link3Cell.innerHTML = `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(cleanUrl)}">${escapeHtml(cleanUrl.length > 30 ? cleanUrl.substring(0, 30) + '...' : cleanUrl)}</a>`;
                        } else {
                            link3Cell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        link3Cell.textContent = '-';
                    }
                    row.appendChild(link3Cell);

                    // Brand 4 column
                    const brand4Cell = document.createElement('td');
                    brand4Cell.textContent = escapeHtml(item.brand_4) || '-';
                    row.appendChild(brand4Cell);

                    // Link 4 column
                    const link4Cell = document.createElement('td');
                    const link4Value = item.link_4 || '';
                    if (link4Value && link4Value.trim()) {
                        const cleanUrl = link4Value.trim();
                        if (cleanUrl.match(/^https?:\/\//i)) {
                            link4Cell.innerHTML = `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(cleanUrl)}">${escapeHtml(cleanUrl.length > 30 ? cleanUrl.substring(0, 30) + '...' : cleanUrl)}</a>`;
                        } else {
                            link4Cell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        link4Cell.textContent = '-';
                    }
                    row.appendChild(link4Cell);

                    // Brand 5 column
                    const brand5Cell = document.createElement('td');
                    brand5Cell.textContent = escapeHtml(item.brand_5) || '-';
                    row.appendChild(brand5Cell);

                    // Link 5 column
                    const link5Cell = document.createElement('td');
                    const link5Value = item.link_5 || '';
                    if (link5Value && link5Value.trim()) {
                        const cleanUrl = link5Value.trim();
                        if (cleanUrl.match(/^https?:\/\//i)) {
                            link5Cell.innerHTML = `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" title="${escapeHtml(cleanUrl)}">${escapeHtml(cleanUrl.length > 30 ? cleanUrl.substring(0, 30) + '...' : cleanUrl)}</a>`;
                        } else {
                            link5Cell.textContent = escapeHtml(cleanUrl);
                        }
                    } else {
                        link5Cell.textContent = '-';
                    }
                    row.appendChild(link5Cell);

                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    actionCell.innerHTML = `
                        <div class="d-inline-flex">
                            <button class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="${escapeHtml(item.SKU)}">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${escapeHtml(item.id)}" data-sku="${escapeHtml(item.SKU)}">
                                <i class="bi bi-archive"></i>
                            </button>
                        </div>
                    `;
                    row.appendChild(actionCell);

                    tbody.appendChild(row);
                });
            }

            // Check if value is missing (null, undefined, empty)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                const brandMissingCounts = {};
                const linkMissingCounts = {};
                
                // Initialize missing counts for all brands and links
                for (let i = 1; i <= 5; i++) {
                    brandMissingCounts[i] = 0;
                    linkMissingCounts[i] = 0;
                }

                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Count missing data for each brand and link column
                    for (let i = 1; i <= 5; i++) {
                        const brandKey = `brand_${i}`;
                        const linkKey = `link_${i}`;
                        
                        if (isMissing(item[brandKey])) {
                            brandMissingCounts[i]++;
                        }
                        if (isMissing(item[linkKey])) {
                            linkMissingCounts[i]++;
                        }
                    }
                });
                
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
                
                // Update missing counts for all brands and links
                for (let i = 1; i <= 5; i++) {
                    document.getElementById(`brand${i}MissingCount`).textContent = `(${brandMissingCounts[i]})`;
                    document.getElementById(`link${i}MissingCount`).textContent = `(${linkMissingCounts[i]})`;
                }
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Parent search filter
                    const parentSearch = document.getElementById('parentSearch').value.toLowerCase();
                    if (parentSearch && !(item.Parent || '').toLowerCase().includes(parentSearch)) {
                        return false;
                    }

                    // SKU search filter
                    const skuSearch = document.getElementById('skuSearch').value.toLowerCase();
                    if (skuSearch && !(item.SKU || '').toLowerCase().includes(skuSearch)) {
                        return false;
                    }

                    // Custom search filter
                    const customSearch = document.getElementById('customSearch').value.toLowerCase();
                    if (customSearch) {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const status = (item.status || '').toLowerCase();
                        if (!parent.includes(customSearch) && !sku.includes(customSearch) && !status.includes(customSearch)) {
                            return false;
                        }
                    }

                    // Brand and Link filters (Brand 1-5, Link 1-5)
                    for (let i = 1; i <= 5; i++) {
                        // Brand filter
                        const filterBrand = document.getElementById(`filterBrand${i}`).value;
                        if (filterBrand === 'missing') {
                            const brandKey = `brand_${i}`;
                            if (!isMissing(item[brandKey])) {
                                return false;
                            }
                        }

                        // Link filter
                        const filterLink = document.getElementById(`filterLink${i}`).value;
                        if (filterLink === 'missing') {
                            const linkKey = `link_${i}`;
                            if (!isMissing(item[linkKey])) {
                                return false;
                            }
                        }
                    }

                    return true;
                });
                renderTable(filteredData);
            }

            // Setup search functionality
            function setupSearch() {
                // Parent search
                const parentSearch = document.getElementById('parentSearch');
                parentSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // SKU search
                const skuSearch = document.getElementById('skuSearch');
                skuSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Custom search
                const customSearch = document.getElementById('customSearch');
                customSearch.addEventListener('input', function() {
                    applyFilters();
                });

                // Clear search
                document.getElementById('clearSearch').addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    // Reset all column filters
                    for (let i = 1; i <= 5; i++) {
                        document.getElementById(`filterBrand${i}`).value = 'all';
                        document.getElementById(`filterLink${i}`).value = 'all';
                    }
                    applyFilters();
                });

                // Column filters for all brands and links
                for (let i = 1; i <= 5; i++) {
                    document.getElementById(`filterBrand${i}`).addEventListener('change', function() {
                        applyFilters();
                    });

                    document.getElementById(`filterLink${i}`).addEventListener('change', function() {
                        applyFilters();
                    });
                }
            }

            // Toast notification function
            function showToast(type, message) {
                // Remove existing toasts
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());
                
                const toast = document.createElement('div');
                toast.className = `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                toast.style.zIndex = 2000;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    // Columns to export (excluding Image and Action)
                    const columns = ["Parent", "SKU", "Status", "INV", "Brand 1", "Link 1", "Brand 2", "Link 2", "Brand 3", "Link 3", "Brand 4", "Link 4", "Brand 5", "Link 5"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": { key: "Parent" },
                        "SKU": { key: "SKU" },
                        "Status": { key: "status" },
                        "INV": { key: "shopify_inv" },
                        "Brand 1": { key: "brand_1" },
                        "Link 1": { key: "link_1" },
                        "Brand 2": { key: "brand_2" },
                        "Link 2": { key: "link_2" },
                        "Brand 3": { key: "brand_3" },
                        "Link 3": { key: "link_3" },
                        "Brand 4": { key: "brand_4" },
                        "Link 4": { key: "link_4" },
                        "Brand 5": { key: "brand_5" },
                        "Link 5": { key: "link_5" }
                    };

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    document.getElementById('downloadExcel').disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Use filteredData if available, otherwise use tableData
                            const dataToExport = filteredData.length > 0 ? filteredData : tableData;

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows
                            dataToExport.forEach(item => {
                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        // Format INV column
                                        if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columns.map(col => {
                                if (["Parent", "SKU"].includes(col)) {
                                    return { wch: 20 };
                                } else if (["Status"].includes(col)) {
                                    return { wch: 15 };
                                } else if (["Brand 1", "Brand 2", "Brand 3", "Brand 4", "Brand 5"].includes(col)) {
                                    return { wch: 15 };
                                } else if (["Link 1", "Link 2", "Link 3", "Link 4", "Link 5"].includes(col)) {
                                    return { wch: 40 };
                                } else {
                                    return { wch: 10 };
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Competitors Master");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "competitors_master_export.xlsx");

                            // Show success toast
                            showToast('success', 'Excel file downloaded successfully!');
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Download Excel';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100);
                });
            }

            // Setup add button handler
            function setupAddButton() {
                document.getElementById('addCompetitorsBtn').addEventListener('click', function() {
                    openAddCompetitorsModal();
                });
            }

            // Open Add Competitors Modal
            async function openAddCompetitorsModal() {
                const modalElement = document.getElementById('addCompetitorsModal');
                const modal = new bootstrap.Modal(modalElement);
                
                // Reset form
                document.getElementById('addCompetitorsForm').reset();
                
                // Destroy Select2 if already initialized
                const skuSelect = document.getElementById('addCompetitorsSku');
                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                
                // Load SKUs into dropdown
                await loadSkusIntoDropdown();
                
                // Setup save button handler
                const saveBtn = document.getElementById('saveAddCompetitorsBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                
                newSaveBtn.addEventListener('click', async function() {
                    await saveAddCompetitors();
                });
                
                // Clean up Select2 when modal is hidden
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                }, { once: true });
                
                modal.show();
            }

            // Load SKUs into dropdown
            async function loadSkusIntoDropdown() {
                try {
                    const response = await fetch('/general-specific-master/skus', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const skuSelect = document.getElementById('addCompetitorsSku');
                        
                        // Destroy Select2 if already initialized
                        if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('destroy');
                        }
                        
                        // Clear existing options except the first one
                        skuSelect.innerHTML = '<option value="">Select SKU</option>';
                        
                        // Add SKU options
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.sku;
                            option.textContent = item.sku;
                            skuSelect.appendChild(option);
                        });
                        
                        // Initialize Select2 with searchable dropdown
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Select SKU',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#addCompetitorsModal')
                        });
                    }
                } catch (error) {
                    console.error('Error loading SKUs:', error);
                    showToast('warning', 'Failed to load SKUs. Please refresh the page.');
                }
            }

            // Save Add Competitors Master
            async function saveAddCompetitors() {
                const saveBtn = document.getElementById('saveAddCompetitorsBtn');
                const originalText = saveBtn.innerHTML;
                
                // Validate required fields
                const skuSelect = document.getElementById('addCompetitorsSku');
                const sku = $(skuSelect).val() ? $(skuSelect).val().trim() : '';
                if (!sku) {
                    showToast('warning', 'Please select SKU');
                    $(skuSelect).select2('open');
                    return;
                }
                
                try {
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                    saveBtn.disabled = true;
                    
                    const formData = {
                        sku: sku,
                        brand_1: document.getElementById('addBrand1').value.trim(),
                        link_1: document.getElementById('addLink1').value.trim(),
                        brand_2: document.getElementById('addBrand2').value.trim(),
                        link_2: document.getElementById('addLink2').value.trim(),
                        brand_3: document.getElementById('addBrand3').value.trim(),
                        link_3: document.getElementById('addLink3').value.trim(),
                        brand_4: document.getElementById('addBrand4').value.trim(),
                        link_4: document.getElementById('addLink4').value.trim(),
                        brand_5: document.getElementById('addBrand5').value.trim(),
                        link_5: document.getElementById('addLink5').value.trim()
                    };
                    
                    const response = await fetch('/competitors-master/store', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to save data');
                    }
                    
                    showToast('success', 'Competitors Data added successfully!');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addCompetitorsModal'));
                    modal.hide();
                    
                    // Clear cache and reload data
                    tableData = [];
                    filteredData = [];
                    setTimeout(() => {
                        loadData();
                    }, 500);
                } catch (error) {
                    console.error('Error saving:', error);
                    showToast('danger', error.message || 'Failed to save data');
                } finally {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('competitorsImportFile');
                const importBtn = document.getElementById('importCompetitorsBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleCompetitorsBtn');
                const importModal = document.getElementById('importCompetitorsModal');
                const fileError = document.getElementById('competitorsFileError');
                const importProgress = document.getElementById('competitorsImportProgress');
                const importResult = document.getElementById('competitorsImportResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data with headers
                    const sampleData = [
                        ['SKU', 'Brand 1', 'Link 1', 'Brand 2', 'Link 2', 'Brand 3', 'Link 3', 'Brand 4', 'Link 4', 'Brand 5', 'Link 5']
                    ];
                    
                    // Add example rows
                    sampleData.push([
                        'SKU001',
                        'Competitor A',
                        'https://example.com/competitor-a',
                        'Competitor B',
                        'https://example.com/competitor-b',
                        'Competitor C',
                        'https://example.com/competitor-c',
                        '',
                        '',
                        '',
                        ''
                    ]);
                    sampleData.push([
                        'SKU002',
                        'Brand X',
                        'https://example.com/brand-x',
                        'Brand Y',
                        'https://example.com/brand-y',
                        '',
                        '',
                        '',
                        '',
                        '',
                        ''
                    ]);

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 20 }, // Brand 1
                        { wch: 40 }, // Link 1
                        { wch: 20 }, // Brand 2
                        { wch: 40 }, // Link 2
                        { wch: 20 }, // Brand 3
                        { wch: 40 }, // Link 3
                        { wch: 20 }, // Brand 4
                        { wch: 40 }, // Link 4
                        { wch: 20 }, // Brand 5
                        { wch: 40 }  // Link 5
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "Competitors Data");
                    XLSX.writeFile(wb, "competitors_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/competitors-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });

                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                // Clear cache and reload data
                                tableData = [];
                                filteredData = [];
                                const cacheParam = '?ts=' + new Date().getTime();
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            // Initialize
            loadData();
            setupExcelExport();
            setupAddButton();
            setupImport();
        });
    </script>
@endsection

