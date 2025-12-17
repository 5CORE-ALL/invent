@extends('layouts.vertical', ['title' => 'Product Description'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .table-container {
            overflow-x: auto;
        }

        #product-table {
            width: 100%;
            border-collapse: collapse;
        }

        #product-table th,
        #product-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            white-space: nowrap;
        }

        #product-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .char-counter {
            float: right;
            font-size: 0.85em;
            color: #6c757d;
        }

        .btn-action {
            padding: 4px 8px;
            font-size: 12px;
            margin: 0 2px;
        }

        .rainbow-loader {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, red, orange, yellow, green, blue, indigo, violet);
            background-size: 200% 100%;
            animation: rainbow 2s linear infinite;
            z-index: 9999;
        }

        @keyframes rainbow {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        .action-buttons {
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    <div id="rainbow-loader" class="rainbow-loader"></div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript: void(0);">Inventory</a></li>
                            <li class="breadcrumb-item active">Product Description</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Product Description</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" class="btn btn-success btn-sm me-2" onclick="exportToExcel()">
                                    <i class="fas fa-file-excel"></i> Export to Excel
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('importFile').click()">
                                    <i class="fas fa-file-upload"></i> Import from Excel
                                </button>
                                <input type="file" id="importFile" accept=".xlsx,.xls" style="display:none;" onchange="importFromExcel(event)">
                            </div>
                            <button type="button" class="btn btn-primary" onclick="openModal()">
                                <i class="fas fa-plus"></i> Add Product Description
                            </button>
                        </div>

                        <div class="table-container">
                            <table id="product-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Parent</th>
                                        <th>SKU</th>
                                        <th>Product Description</th>
                                        <th>Action</th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm search-input" placeholder="Search Parent" data-column="Parent"></th>
                                        <th><input type="text" class="form-control form-control-sm search-input" placeholder="Search SKU" data-column="SKU"></th>
                                        <th><input type="text" class="form-control form-control-sm search-input" placeholder="Search Description" data-column="product_description"></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    <tr>
                                        <td colspan="5" class="text-center">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="descriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Product Description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="descriptionForm">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parent</label>
                                <input type="text" class="form-control" id="modalParent" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU</label>
                                <select class="form-select" id="modalSKU" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productDescription" class="form-label">
                                Product Description <span class="char-counter" id="descCounter">0/1500</span>
                            </label>
                            <textarea class="form-control" id="productDescription" name="product_description" 
                                      rows="6" maxlength="1500"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveDescription()">
                        <i class="fas fa-save"></i> Save
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let descriptionModal;

        document.addEventListener('DOMContentLoaded', function() {
            descriptionModal = new bootstrap.Modal(document.getElementById('descriptionModal'));
            loadDescriptionData();
            setupSearchHandlers();
            setupCharCounters();
            setupSKUChangeHandler();
        });

        function loadDescriptionData() {
            document.getElementById('rainbow-loader').style.display = 'block';
            
            fetch('/product-master-data-view', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(response => {
                    const data = response.data ? response.data : response;
                    
                    if (data && Array.isArray(data)) {
                        tableData = data;
                        renderTable(tableData);
                    } else {
                        console.error('Invalid data:', response);
                        showError('Invalid data format received from server');
                    }
                    document.getElementById('rainbow-loader').style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to load product data: ' + error.message);
                    document.getElementById('rainbow-loader').style.display = 'none';
                });
        }

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No products found</td></tr>';
                return;
            }

            data.forEach(item => {
                // Skip parent rows
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                    return;
                }

                const row = document.createElement('tr');

                // Image
                const imageCell = document.createElement('td');
                imageCell.innerHTML = item.image_path 
                    ? `<img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`
                    : '-';
                row.appendChild(imageCell);

                // Parent
                const parentCell = document.createElement('td');
                parentCell.textContent = escapeHtml(item.Parent) || '-';
                row.appendChild(parentCell);

                // SKU
                const skuCell = document.createElement('td');
                skuCell.textContent = escapeHtml(item.SKU) || '-';
                row.appendChild(skuCell);

                // Product Description
                const descCell = document.createElement('td');
                const desc = item.product_description || '';
                descCell.textContent = desc.length > 100 ? desc.substring(0, 100) + '...' : desc;
                descCell.style.maxWidth = '300px';
                row.appendChild(descCell);

                // Actions
                const actionCell = document.createElement('td');
                actionCell.className = 'action-buttons';
                actionCell.innerHTML = `
                    <button class="btn btn-info btn-action" onclick="openModal('${escapeHtml(item.SKU)}')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                `;
                row.appendChild(actionCell);

                tbody.appendChild(row);
            });
        }

        function setupSearchHandlers() {
            document.querySelectorAll('.search-input').forEach(input => {
                input.addEventListener('input', function() {
                    filterTable();
                });
            });
        }

        function filterTable() {
            const filters = {};
            document.querySelectorAll('.search-input').forEach(input => {
                const column = input.getAttribute('data-column');
                const value = input.value.toLowerCase().trim();
                if (value) {
                    filters[column] = value;
                }
            });

            const filteredData = tableData.filter(item => {
                // Skip parent rows
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                    return false;
                }

                for (const [column, value] of Object.entries(filters)) {
                    const itemValue = String(item[column] || '').toLowerCase();
                    if (!itemValue.includes(value)) {
                        return false;
                    }
                }
                return true;
            });

            renderTable(filteredData);
        }

        function setupCharCounters() {
            const textarea = document.getElementById('productDescription');
            const counter = document.getElementById('descCounter');
            
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/1500`;
            });
        }

        function setupSKUChangeHandler() {
            const skuSelect = document.getElementById('modalSKU');
            // Use jQuery event for Select2 compatibility
            $(skuSelect).on('change', function() {
                const selectedSKU = $(this).val();
                if (selectedSKU) {
                    const item = tableData.find(d => d.SKU === selectedSKU);
                    if (item) {
                        document.getElementById('modalParent').value = item.Parent || '';
                        document.getElementById('productDescription').value = item.product_description || '';
                        updateCharCounter();
                    }
                }
            });
        }

        function updateCharCounter() {
            const textarea = document.getElementById('productDescription');
            const counter = document.getElementById('descCounter');
            const length = textarea.value.length;
            counter.textContent = `${length}/1500`;
        }

        function openModal(sku = null) {
            // Reset form
            document.getElementById('descriptionForm').reset();
            document.getElementById('modalParent').value = '';
            document.getElementById('productDescription').value = '';
            updateCharCounter();

            // Populate SKU dropdown
            const skuSelect = document.getElementById('modalSKU');
            
            // Destroy Select2 if already initialized
            if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                $(skuSelect).select2('destroy');
            }
            
            skuSelect.innerHTML = '<option value="">Select SKU</option>';
            
            tableData.forEach(item => {
                if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                    const option = document.createElement('option');
                    option.value = item.SKU;
                    option.textContent = item.SKU;
                    if (sku && item.SKU === sku) {
                        option.selected = true;
                    }
                    skuSelect.appendChild(option);
                }
            });

            if (sku) {
                document.getElementById('modalTitle').textContent = 'Edit Product Description';
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    document.getElementById('modalParent').value = item.Parent || '';
                    document.getElementById('productDescription').value = item.product_description || '';
                    updateCharCounter();
                }
            } else {
                document.getElementById('modalTitle').textContent = 'Add Product Description';
                
                // Initialize Select2 with searchable dropdown for add mode
                $(skuSelect).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select SKU',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#descriptionModal')
                });
            }

            // Clean up Select2 when modal is hidden
            const modalElement = document.getElementById('descriptionModal');
            modalElement.addEventListener('hidden.bs.modal', function() {
                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
            }, { once: true });

            descriptionModal.show();
        }

        function saveDescription() {
            const skuSelect = document.getElementById('modalSKU');
            // Get SKU value - use Select2 .val() if initialized, otherwise use .value
            const sku = $(skuSelect).hasClass('select2-hidden-accessible') ? $(skuSelect).val() : skuSelect.value;
            const productDescription = document.getElementById('productDescription').value;

            if (!sku) {
                showError('Please select a SKU');
                return;
            }

            if (!productDescription.trim()) {
                showError('Product Description is required');
                return;
            }

            document.getElementById('rainbow-loader').style.display = 'block';

            fetch('/product-description/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    sku: sku,
                    product_description: productDescription
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message || 'Product description saved successfully!');
                        descriptionModal.hide();
                        loadDescriptionData();
                    } else {
                        showError(data.message || 'Failed to save product description');
                    }
                    document.getElementById('rainbow-loader').style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('Failed to save: ' + error.message);
                    document.getElementById('rainbow-loader').style.display = 'none';
                });
        }

        function exportToExcel() {
            if (tableData.length === 0) {
                showError('No data to export');
                return;
            }

            const exportData = tableData
                .filter(item => item.SKU && !item.SKU.toUpperCase().includes('PARENT'))
                .map(item => ({
                    'Parent': item.Parent || '',
                    'SKU': item.SKU || '',
                    'Product Description': item.product_description || ''
                }));

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Product Description');

            const timestamp = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, `product_description_${timestamp}.xlsx`);
        }

        function importFromExcel(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet);

                    if (jsonData.length === 0) {
                        showError('No data found in Excel file');
                        return;
                    }

                    document.getElementById('rainbow-loader').style.display = 'block';
                    let imported = 0;
                    let errors = 0;

                    const importPromises = jsonData.map(row => {
                        const sku = row['SKU'] || row['sku'];
                        const productDescription = row['Product Description'] || row['product_description'] || '';

                        if (!sku) {
                            errors++;
                            return Promise.resolve();
                        }

                        return fetch('/product-description/save', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                sku: sku,
                                product_description: productDescription
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    imported++;
                                } else {
                                    errors++;
                                }
                            })
                            .catch(() => {
                                errors++;
                            });
                    });

                    Promise.all(importPromises).then(() => {
                        showSuccess(`Import completed! Imported: ${imported}, Errors: ${errors}`);
                        loadDescriptionData();
                        event.target.value = '';
                    });

                } catch (error) {
                    console.error('Error:', error);
                    showError('Failed to read Excel file');
                    document.getElementById('rainbow-loader').style.display = 'none';
                }
            };
            reader.readAsArrayBuffer(file);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showError(message) {
            alert(message);
        }

        function showSuccess(message) {
            alert(message);
        }
    </script>
@endsection
