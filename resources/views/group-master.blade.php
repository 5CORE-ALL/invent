@extends('layouts.vertical', ['title' => 'Group Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

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

        /* Editable cell styles */
        .editable-cell {
            position: relative;
            min-width: 120px;
        }

        .editable-cell:hover {
            background-color: #f0f8ff !important;
        }

        .editable-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .editable-cell:hover .editable-value {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .editable-input,
        .editable-select {
            width: 100%;
            min-width: 150px;
            padding: 4px 8px;
            border: 2px solid #1976d2;
            border-radius: 4px;
            font-size: 13px;
        }

        .editable-input:focus,
        .editable-select:focus {
            outline: none;
            border-color: #0d47a1;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Group Masters',
        'sub_title' => 'Group Masters Analysis',
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
                                    placeholder="Search groups...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <input type="file" id="uploadExcel" accept=".xlsx,.xls" style="display: none;">
                            <button type="button" class="btn btn-primary me-2" id="uploadExcelBtn">
                                <i class="fas fa-upload me-1"></i> Upload Excel
                            </button>
                            <button type="button" class="btn btn-success" id="downloadExcel">
                                <i class="fas fa-file-excel me-1"></i> Download Excel
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="group-master-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>CATEGORY</span>
                                            <button type="button" class="btn btn-sm btn-success" id="createCategoryBtn" 
                                                style="padding: 2px 8px; font-size: 11px;" title="Create New Category">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>GROUPS</span>
                                            <button type="button" class="btn btn-sm btn-success" id="createGroupBtn" 
                                                style="padding: 2px 8px; font-size: 11px;" title="Create New Group">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </th>
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
                        <div class="loading-text">Loading Group Masters Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createGroupModalLabel">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createGroupForm">
                        <div class="mb-3">
                            <label for="group_name" class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="group_name" name="group_name" 
                                placeholder="Enter group name" required>
                        </div>
                        <div class="mb-3">
                            <label for="group_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="group_description" name="group_description" rows="3" 
                                placeholder="Optional description for this group"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveGroupBtn">
                        <i class="fas fa-save me-1"></i> Create Group
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Category Modal -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createCategoryModalLabel">Create New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="createCategoryForm">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category_name" name="category_name" 
                                placeholder="Enter category name" required>
                        </div>
                        <div class="mb-3">
                            <label for="category_code" class="form-label">Category Code (Optional)</label>
                            <input type="text" class="form-control" id="category_code" name="category_code" 
                                placeholder="Enter category code">
                        </div>
                        <div class="mb-3">
                            <label for="category_description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control" id="category_description" name="category_description" rows="3" 
                                placeholder="Optional description for this category"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCategoryBtn">
                        <i class="fas fa-save me-1"></i> Create Category
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];
            let allGroups = [];
            let allCategories = [];

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

            // Load group data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/group-master-data-view' + cacheParam, 'GET')
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
                        console.error('Failed to load group data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            // Render table
            function renderTable(data) {
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '';

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center">No groups found</td></tr>';
                    return;
                }

                data.forEach(item => {
                    const row = document.createElement('tr');

                    // CATEGORY column - dropdown
                    const categoryCell = document.createElement('td');
                    categoryCell.className = 'editable-cell';
                    categoryCell.dataset.field = 'category_id';
                    categoryCell.dataset.productId = item.id;
                    categoryCell.dataset.sku = item.SKU;
                    const categoryId = item.category_id || '';
                    const categoryName = escapeHtml(item.category) || '';
                    
                    let categoryOptions = '<option value="">-- No Category --</option>';
                    if (allCategories && allCategories.length > 0) {
                        allCategories.forEach(cat => {
                            const selected = cat.id == categoryId ? 'selected' : '';
                            categoryOptions += `<option value="${cat.id}" ${selected}>${escapeHtml(cat.category_name)}</option>`;
                        });
                    }
                    
                    categoryCell.innerHTML = `
                        <span class="editable-value">${categoryName || '-'}</span>
                        <select class="editable-select form-select form-select-sm" style="display:none;" 
                            data-original="${categoryId}">
                            ${categoryOptions}
                        </select>
                    `;
                    categoryCell.style.cursor = 'pointer';
                    categoryCell.title = 'Click to edit category';
                    row.appendChild(categoryCell);

                    // GROUPS column - dropdown
                    const groupsCell = document.createElement('td');
                    groupsCell.className = 'editable-cell';
                    groupsCell.dataset.field = 'group_id';
                    groupsCell.dataset.productId = item.id;
                    groupsCell.dataset.sku = item.SKU;
                    const groupId = item.group_id || '';
                    const groupName = escapeHtml(item.group) || '';
                    
                    let groupOptions = '<option value="">-- No Group --</option>';
                    if (allGroups && allGroups.length > 0) {
                        allGroups.forEach(grp => {
                            const selected = grp.id == groupId ? 'selected' : '';
                            groupOptions += `<option value="${grp.id}" ${selected}>${escapeHtml(grp.group_name)}</option>`;
                        });
                    }
                    
                    groupsCell.innerHTML = `
                        <span class="editable-value">${groupName || '-'}</span>
                        <select class="editable-select form-select form-select-sm" style="display:none;" 
                            data-original="${groupId}">
                            ${groupOptions}
                        </select>
                    `;
                    groupsCell.style.cursor = 'pointer';
                    groupsCell.title = 'Click to edit group';
                    row.appendChild(groupsCell);

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

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                tableData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                });
                document.getElementById('parentCount').textContent = `(${parentSet.size})`;
                document.getElementById('skuCount').textContent = `(${skuCount})`;
            }

            // Setup search functionality
            function setupSearch() {
                // Parent search
                const parentSearch = document.getElementById('parentSearch');
                parentSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filteredData = tableData.filter(item => {
                        const parent = (item.Parent || '').toLowerCase();
                        return parent.includes(searchTerm);
                    });
                    renderTable(filteredData);
                });

                // SKU search
                const skuSearch = document.getElementById('skuSearch');
                skuSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filteredData = tableData.filter(item => {
                        const sku = (item.SKU || '').toLowerCase();
                        return sku.includes(searchTerm);
                    });
                    renderTable(filteredData);
                });

                // Custom search
                const customSearch = document.getElementById('customSearch');
                customSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filteredData = tableData.filter(item => {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const status = (item.status || '').toLowerCase();
                        return parent.includes(searchTerm) || sku.includes(searchTerm) || status.includes(searchTerm);
                    });
                    renderTable(filteredData);
                });

                // Clear search
                document.getElementById('clearSearch').addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    filteredData = [...tableData];
                    renderTable(filteredData);
                });
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
                    // Use filteredData if available, otherwise use tableData
                    const dataToExport = filteredData.length > 0 ? filteredData : tableData;
                    
                    if (dataToExport.length === 0) {
                        showToast('warning', 'No data to export.');
                        return;
                    }

                    // Collect all unique keys from all data objects to include all parameters
                    const allKeys = new Set();
                    dataToExport.forEach(item => {
                        Object.keys(item).forEach(key => {
                            // Exclude internal/display-only fields
                            if (key !== 'shopify_quantity') {
                                allKeys.add(key);
                            }
                        });
                    });

                    // Define column order (priority columns first)
                    const priorityColumns = [
                        'id', 'category', 'group_id', 'image_path', 'Parent', 'SKU', 'status', 'shopify_inv',
                        'title150', 'title100', 'title80', 'title60',
                        'bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5',
                        'product_description', 'feature1', 'feature2', 'feature3', 'feature4',
                        'main_image', 'main_image_brand',
                        'image1', 'image2', 'image3', 'image4', 'image5', 'image6',
                        'image7', 'image8', 'image9', 'image10', 'image11', 'image12'
                    ];

                    // Sort columns: priority first, then alphabetically for the rest
                    const sortedKeys = [];
                    priorityColumns.forEach(key => {
                        if (allKeys.has(key)) {
                            sortedKeys.push(key);
                            allKeys.delete(key);
                        }
                    });
                    const remainingKeys = Array.from(allKeys).sort();
                    const columns = [...sortedKeys, ...remainingKeys];

                    // Create friendly column names
                    const columnNameMap = {
                        'id': 'ID',
                        'category': 'CATEGORY',
                        'group_id': 'GROUPS',
                        'image_path': 'Image Path',
                        'Parent': 'Parent',
                        'SKU': 'SKU',
                        'status': 'Status',
                        'shopify_inv': 'INV',
                        'title150': 'Title150',
                        'title100': 'Title100',
                        'title80': 'Title80',
                        'title60': 'Title60',
                        'bullet1': 'Bullet1',
                        'bullet2': 'Bullet2',
                        'bullet3': 'Bullet3',
                        'bullet4': 'Bullet4',
                        'bullet5': 'Bullet5',
                        'product_description': 'Product Description',
                        'feature1': 'Feature1',
                        'feature2': 'Feature2',
                        'feature3': 'Feature3',
                        'feature4': 'Feature4',
                        'main_image': 'Main Image',
                        'main_image_brand': 'Main Image Brand',
                        'image1': 'Image1',
                        'image2': 'Image2',
                        'image3': 'Image3',
                        'image4': 'Image4',
                        'image5': 'Image5',
                        'image6': 'Image6',
                        'image7': 'Image7',
                        'image8': 'Image8',
                        'image9': 'Image9',
                        'image10': 'Image10',
                        'image11': 'Image11',
                        'image12': 'Image12'
                    };

                    // Create column headers
                    const columnHeaders = columns.map(key => columnNameMap[key] || key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' '));

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    document.getElementById('downloadExcel').disabled = true;

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columnHeaders);

                            // Add data rows
                            dataToExport.forEach(item => {
                                const row = [];
                                columns.forEach(key => {
                                    let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                    // Format specific columns
                                    if (key === "shopify_inv") {
                                        if (value === 0 || value === "0") {
                                            value = 0;
                                        } else if (value === null || value === undefined || value === "") {
                                            value = '';
                                        } else {
                                            value = parseFloat(value) || 0;
                                        }
                                    }
                                    
                                    // Handle arrays and objects (convert to JSON string)
                                    if (typeof value === 'object' && value !== null) {
                                        value = JSON.stringify(value);
                                    }
                                    
                                    // Handle null/undefined values
                                    if (value === null || value === undefined) {
                                        value = '';
                                    }

                                    row.push(value);
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columnHeaders.map((header, index) => {
                                const key = columns[index];
                                // Adjust width based on column type
                                if (["Parent", "SKU", "Product Description"].includes(header)) {
                                    return { wch: 25 };
                                } else if (["Title150", "Title100", "Title80", "Title60"].includes(header)) {
                                    return { wch: 30 };
                                } else if (["Bullet1", "Bullet2", "Bullet3", "Bullet4", "Bullet5", "Feature1", "Feature2", "Feature3", "Feature4"].includes(header)) {
                                    return { wch: 40 };
                                } else if (header.includes("Image") || header.includes("image")) {
                                    return { wch: 50 };
                                } else if (["Status", "CATEGORY", "GROUPS", "INV"].includes(header)) {
                                    return { wch: 15 };
                                } else if (key && typeof dataToExport[0]?.[key] === 'object') {
                                    return { wch: 50 }; // Wider for JSON fields
                                } else {
                                    return { wch: 20 }; // Default width
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
                            XLSX.utils.book_append_sheet(wb, ws, "Group Masters");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "group_master_export.xlsx");

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
                    }, 100); // Small timeout to allow UI to update
                });
            }

            // Setup Excel upload function
            function setupExcelUpload() {
                const uploadBtn = document.getElementById('uploadExcelBtn');
                const fileInput = document.getElementById('uploadExcel');

                uploadBtn.addEventListener('click', function() {
                    fileInput.click();
                });

                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    // Validate file type
                    const validTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 
                                      'application/vnd.ms-excel'];
                    if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/i)) {
                        showToast('danger', 'Please upload a valid Excel file (.xlsx or .xls)');
                        fileInput.value = '';
                        return;
                    }

                    // Show loading state
                    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
                    uploadBtn.disabled = true;

                    // Create FormData
                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    // Upload file
                    fetch('/group-master-upload-excel', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('success', data.message || 'Excel file uploaded and data updated successfully!');
                            // Reload data after successful upload
                            setTimeout(() => {
                                loadData();
                            }, 1000);
                        } else {
                            showToast('danger', data.message || 'Failed to upload Excel file.');
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        showToast('danger', 'An error occurred while uploading the file.');
                    })
                    .finally(() => {
                        // Reset button state
                        uploadBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload Excel';
                        uploadBtn.disabled = false;
                        fileInput.value = '';
                    });
                });
            }

            // Load groups and categories
            function loadGroupsAndCategories() {
                return Promise.all([
                    // Load groups
                    fetch('/group-master-groups')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                allGroups = data.groups || [];
                            }
                        })
                        .catch(error => {
                            console.error('Error loading groups:', error);
                            allGroups = [];
                        }),
                    // Load categories
                    fetch('/group-master-categories')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                allCategories = data.categories || [];
                            }
                        })
                        .catch(error => {
                            console.error('Error loading categories:', error);
                            allCategories = [];
                        })
                ]);
            }

            // Setup inline editing for category and group columns
            function setupInlineEditing() {
                // Use event delegation for dynamically created rows
                document.addEventListener('click', function(e) {
                    const cell = e.target.closest('.editable-cell');
                    if (!cell) return;
                    
                    const span = cell.querySelector('.editable-value');
                    const select = cell.querySelector('.editable-select');
                    
                    if (e.target === span || e.target === cell) {
                        // Enter edit mode
                        span.style.display = 'none';
                        select.style.display = 'block';
                        select.focus();
                    }
                });

                // Handle select change (save on change)
                document.addEventListener('change', function(e) {
                    const select = e.target;
                    if (!select.classList.contains('editable-select')) return;
                    
                    const cell = select.closest('.editable-cell');
                    const span = cell.querySelector('.editable-value');
                    const field = cell.dataset.field;
                    const productId = cell.dataset.productId;
                    const sku = cell.dataset.sku;
                    const newValue = select.value;
                    const originalValue = select.dataset.original || '';

                    // If value changed, save it
                    if (newValue !== originalValue) {
                        saveFieldValue(productId, sku, field, newValue, cell, span, select);
                    } else {
                        // Just exit edit mode
                        updateDisplayValue(cell, newValue, field);
                        span.style.display = '';
                        select.style.display = 'none';
                    }
                });

                // Handle blur to exit edit mode
                document.addEventListener('blur', function(e) {
                    const select = e.target;
                    if (!select.classList.contains('editable-select')) return;
                    
                    const cell = select.closest('.editable-cell');
                    const span = cell.querySelector('.editable-value');
                    const originalValue = select.dataset.original || '';
                    
                    // Reset to original if not saved
                    if (select.value !== originalValue && !cell.dataset.saving) {
                        select.value = originalValue;
                        updateDisplayValue(cell, originalValue, cell.dataset.field);
                    }
                    
                    span.style.display = '';
                    select.style.display = 'none';
                    delete cell.dataset.saving;
                }, true);

                // Handle Escape key to cancel
                document.addEventListener('keydown', function(e) {
                    const select = e.target;
                    if (!select.classList.contains('editable-select')) return;
                    
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        const cell = select.closest('.editable-cell');
                        const span = cell.querySelector('.editable-value');
                        const originalValue = select.dataset.original || '';
                        select.value = originalValue;
                        updateDisplayValue(cell, originalValue, cell.dataset.field);
                        span.style.display = '';
                        select.style.display = 'none';
                    }
                });
            }

            // Update display value based on selected ID
            function updateDisplayValue(cell, valueId, field) {
                const span = cell.querySelector('.editable-value');
                if (!valueId || valueId === '') {
                    span.textContent = '-';
                    return;
                }
                
                if (field === 'group_id') {
                    const group = allGroups.find(g => g.id == valueId);
                    span.textContent = group ? group.group_name : '-';
                } else if (field === 'category_id') {
                    const category = allCategories.find(c => c.id == valueId);
                    span.textContent = category ? category.category_name : '-';
                }
            }

            // Save field value to backend
            function saveFieldValue(productId, sku, field, value, cell, span, select) {
                cell.dataset.saving = 'true';
                
                // Show loading state
                span.textContent = 'Saving...';
                span.style.display = '';
                select.style.display = 'none';
                cell.style.opacity = '0.6';

                fetch('/group-master-update-field', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        sku: sku,
                        field: field,
                        value: value ? parseInt(value) : null
                    })
                })
                .then(response => response.json())
                .then(data => {
                    cell.style.opacity = '1';
                    delete cell.dataset.saving;
                    
                    if (data.success) {
                        // Update display value
                        updateDisplayValue(cell, value, field);
                        select.value = value;
                        select.dataset.original = value;
                        
                        // Update data in tableData
                        const item = tableData.find(d => d.id == productId);
                        if (item) {
                            if (field === 'group_id') {
                                item.group_id = value ? parseInt(value) : null;
                                item.group = data.data.group_name || null;
                            } else if (field === 'category_id') {
                                item.category_id = value ? parseInt(value) : null;
                                item.category = data.data.category_name || null;
                            }
                        }
                        
                        showToast('success', data.message || `${field.replace('_id', '')} updated successfully!`);
                    } else {
                        // Revert to original value on error
                        const originalValue = select.dataset.original || '';
                        select.value = originalValue;
                        updateDisplayValue(cell, originalValue, field);
                        showToast('danger', data.message || `Failed to update ${field}.`);
                    }
                })
                .catch(error => {
                    cell.style.opacity = '1';
                    delete cell.dataset.saving;
                    const originalValue = select.dataset.original || '';
                    select.value = originalValue;
                    updateDisplayValue(cell, originalValue, field);
                    console.error('Error:', error);
                    showToast('danger', `Error updating ${field}. Please try again.`);
                });
            }

            // Setup create group functionality
            function setupCreateGroup() {
                const createBtn = document.getElementById('createGroupBtn');
                const modal = new bootstrap.Modal(document.getElementById('createGroupModal'));
                const saveBtn = document.getElementById('saveGroupBtn');
                const form = document.getElementById('createGroupForm');

                createBtn.addEventListener('click', function() {
                    form.reset();
                    modal.show();
                });

                saveBtn.addEventListener('click', function() {
                    const groupName = document.getElementById('group_name').value.trim();
                    const description = document.getElementById('group_description').value.trim();

                    if (!groupName) {
                        showToast('danger', 'Group name is required.');
                        return;
                    }

                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating...';

                    fetch('/group-master-store-group', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            group_name: groupName,
                            description: description,
                            status: 'active'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('success', data.message || 'Group created successfully!');
                            modal.hide();
                            form.reset();
                            // Reload groups and refresh table
                            loadGroupsAndCategories();
                            setTimeout(() => {
                                loadData();
                            }, 500);
                        } else {
                            showToast('danger', data.message || 'Failed to create group.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('danger', 'An error occurred while creating the group.');
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Create Group';
                    });
                });
            }

            // Setup create category functionality
            function setupCreateCategory() {
                const createBtn = document.getElementById('createCategoryBtn');
                const modal = new bootstrap.Modal(document.getElementById('createCategoryModal'));
                const saveBtn = document.getElementById('saveCategoryBtn');
                const form = document.getElementById('createCategoryForm');

                createBtn.addEventListener('click', function() {
                    form.reset();
                    modal.show();
                });

                saveBtn.addEventListener('click', function() {
                    const categoryName = document.getElementById('category_name').value.trim();
                    const code = document.getElementById('category_code').value.trim();
                    const description = document.getElementById('category_description').value.trim();

                    if (!categoryName) {
                        showToast('danger', 'Category name is required.');
                        return;
                    }

                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Creating...';

                    fetch('/group-master-store-category', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            category_name: categoryName,
                            code: code || null,
                            description: description,
                            status: 'active'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('success', data.message || 'Category created successfully!');
                            modal.hide();
                            form.reset();
                            // Reload categories and refresh table
                            loadGroupsAndCategories();
                            setTimeout(() => {
                                loadData();
                            }, 500);
                        } else {
                            showToast('danger', data.message || 'Failed to create category.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('danger', 'An error occurred while creating the category.');
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save me-1"></i> Create Category';
                    });
                });
            }

            // Initialize
            loadGroupsAndCategories().then(() => {
                loadData();
            });
            setupExcelExport();
            setupExcelUpload();
            setupInlineEditing();
            setupCreateGroup();
            setupCreateCategory();
        });
    </script>
@endsection
