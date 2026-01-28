@extends('layouts.vertical', ['title' => 'Videos Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            margin: 0 2px;
        }

        .edit-btn {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
        }

        .edit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
        }

        #rainbow-loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .rainbow-loader .loading-text {
            margin-top: 20px;
            font-weight: bold;
            color: #2c6ed5;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: white;
        }

        .video-link {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #0066cc;
            text-decoration: none;
        }

        .video-link:hover {
            text-decoration: underline;
        }

        .video-link-icon {
            color: #2c6ed5;
            font-size: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .video-link-icon:hover {
            color: #1a56b7;
            transform: scale(1.2);
        }

        .video-link-icon i {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Videos Master',
        'sub_title' => 'Manage Product Videos',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <button id="addVideoBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Videos
                            </button>
                            <button id="exportBtn" class="btn btn-primary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-info ms-2">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="videos-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Images</th>
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
                                    <th>
                                        <div>Product Overview <span id="productOverviewMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterProductOverview" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Unboxing <span id="unboxingMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterUnboxing" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>How To <span id="howToMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterHowTo" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Setup <span id="setupMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterSetup" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Troubleshooting <span id="troubleshootingMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTroubleshooting" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Brand Story <span id="brandStoryMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterBrandStory" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Product Benefits <span id="productBenefitsMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterProductBenefits" class="form-control form-control-sm mt-1" style="font-size: 11px;">
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
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="loading-text">Loading Videos Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Videos Modal -->
    <div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="videoModalLabel">
                        <i class="fas fa-video me-2"></i><span id="modalTitle">Add Videos</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="videoForm">
                        <input type="hidden" id="editSku" name="sku">
                        
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_product_overview" class="form-label">Product Overview</label>
                            <input type="url" class="form-control" id="video_product_overview" name="video_product_overview" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_unboxing" class="form-label">Unboxing</label>
                            <input type="url" class="form-control" id="video_unboxing" name="video_unboxing" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_how_to" class="form-label">How To</label>
                            <input type="url" class="form-control" id="video_how_to" name="video_how_to" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_setup" class="form-label">Setup</label>
                            <input type="url" class="form-control" id="video_setup" name="video_setup" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_troubleshooting" class="form-label">Troubleshooting</label>
                            <input type="url" class="form-control" id="video_troubleshooting" name="video_troubleshooting" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_brand_story" class="form-label">Brand Story</label>
                            <input type="url" class="form-control" id="video_brand_story" name="video_brand_story" placeholder="https://">
                        </div>

                        <div class="mb-3">
                            <label for="video_product_benefits" class="form-label">Product Benefits</label>
                            <input type="url" class="form-control" id="video_product_benefits" name="video_product_benefits" placeholder="https://">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveVideoBtn">
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
        @verbatim
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let videoModal;

        document.addEventListener('DOMContentLoaded', function() {
            videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
            loadVideoData();
            setupSearchHandlers();
            setupButtonHandlers();
        });

        function setupButtonHandlers() {
            // Add Videos Button
            document.getElementById('addVideoBtn').addEventListener('click', function() {
                openModal('add');
            });

            // Export Button
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportToExcel();
            });

            // Import Button
            document.getElementById('importBtn').addEventListener('click', function() {
                document.getElementById('importFile').click();
            });

            // Import File Handler
            document.getElementById('importFile').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    importFromExcel(file);
                }
            });

            // Save button
            document.getElementById('saveVideoBtn').addEventListener('click', function() {
                saveVideoFromModal();
            });
        }

        function loadVideoData() {
            document.getElementById('rainbow-loader').style.display = 'block';
            
            fetch('/product-master-data-view')
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
                        updateCounts();
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
                tbody.innerHTML = '<tr><td colspan="11" class="text-center">No products found</td></tr>';
                return;
            }

            // Filter out parent rows before rendering
            const filteredData = data.filter(item => {
                return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
            });

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
                const row = document.createElement('tr');

                // Images
                const imageCell = document.createElement('td');
                imageCell.innerHTML = item.image_path 
                    ? '<img src="' + item.image_path + '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">'
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

                // Video fields
                const videoFields = [
                    'video_product_overview',
                    'video_unboxing',
                    'video_how_to',
                    'video_setup',
                    'video_troubleshooting',
                    'video_brand_story',
                    'video_product_benefits'
                ];

                videoFields.forEach(field => {
                    const cell = document.createElement('td');
                    cell.style.textAlign = 'center';
                    if (item[field]) {
                        const link = document.createElement('a');
                        link.href = item[field];
                        link.target = '_blank';
                        link.className = 'video-link-icon';
                        link.innerHTML = '<i class="fas fa-play-circle"></i>';
                        link.title = item[field];
                        cell.appendChild(link);
                    } else {
                        cell.textContent = '-';
                    }
                    row.appendChild(cell);
                });

                // Action - Edit Button
                const actionCell = document.createElement('td');
                actionCell.innerHTML = '<button class="action-btn edit-btn" data-sku="' + escapeHtml(item.SKU) + '"><i class="fas fa-edit"></i> Edit</button>';
                row.appendChild(actionCell);

                tbody.appendChild(row);
            });

            setupEditButtons();
        }

        function setupEditButtons() {
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    openModal('edit', sku);
                });
            });
        }

        function openModal(mode, sku = null) {
            const modal = document.getElementById('videoModal');
            const modalTitle = document.getElementById('modalTitle');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            
            // Reset form
            document.getElementById('videoForm').reset();

            if (mode === 'add') {
                modalTitle.textContent = 'Add Videos';
                selectSku.style.display = 'block';
                selectSku.required = true;
                editSku.value = '';
                
                // Destroy Select2 if already initialized
                if ($(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).select2('destroy');
                }
                
                // Populate SKU dropdown
                selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                tableData.forEach(item => {
                    if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                        selectSku.innerHTML += '<option value="' + escapeHtml(item.SKU) + '">' + escapeHtml(item.SKU) + '</option>';
                    }
                });
                
                // Initialize Select2 with searchable dropdown
                $(selectSku).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Choose SKU...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#videoModal')
                });
            } else if (mode === 'edit' && sku) {
                modalTitle.textContent = 'Edit Videos';
                selectSku.style.display = 'none';
                selectSku.required = false;
                editSku.value = sku;
                
                // Destroy Select2 if initialized
                if ($(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).select2('destroy');
                }
                
                // Load existing data
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    const videoFields = [
                        'video_product_overview',
                        'video_unboxing',
                        'video_how_to',
                        'video_setup',
                        'video_troubleshooting',
                        'video_brand_story',
                        'video_product_benefits'
                    ];
                    
                    videoFields.forEach(field => {
                        const input = document.getElementById(field);
                        if (input) {
                            input.value = item[field] || '';
                        }
                    });
                }
            }

            // Clean up Select2 when modal is hidden
            const modalElement = document.getElementById('videoModal');
            modalElement.addEventListener('hidden.bs.modal', function() {
                if ($(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).select2('destroy');
                }
            }, { once: true });

            videoModal.show();
        }

        function saveVideoFromModal() {
            const form = document.getElementById('videoForm');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            // Get SKU value - use Select2 .val() if initialized, otherwise use .value
            const sku = editSku.value || ($(selectSku).hasClass('select2-hidden-accessible') ? $(selectSku).val() : selectSku.value);

            if (!sku) {
                alert('Please select a SKU');
                return;
            }

            const videoData = {
                sku: sku,
                video_product_overview: document.getElementById('video_product_overview').value,
                video_unboxing: document.getElementById('video_unboxing').value,
                video_how_to: document.getElementById('video_how_to').value,
                video_setup: document.getElementById('video_setup').value,
                video_troubleshooting: document.getElementById('video_troubleshooting').value,
                video_brand_story: document.getElementById('video_brand_story').value,
                video_product_benefits: document.getElementById('video_product_benefits').value
            };

            const saveBtn = document.getElementById('saveVideoBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/videos-master/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(videoData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    videoModal.hide();
                    loadVideoData();
                    alert('Videos saved successfully!');
                } else {
                    alert(data.message || 'Failed to save videos');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving videos: ' + error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        function exportToExcel() {
            const exportData = tableData
                .filter(item => item.SKU && !item.SKU.toUpperCase().includes('PARENT'))
                .map(item => ({
                    'Parent': item.Parent || '',
                    'SKU': item.SKU || '',
                    'Product Overview': item.video_product_overview || '',
                    'Unboxing': item.video_unboxing || '',
                    'How To': item.video_how_to || '',
                    'Setup': item.video_setup || '',
                    'Troubleshooting': item.video_troubleshooting || '',
                    'Brand Story': item.video_brand_story || '',
                    'Product Benefits': item.video_product_benefits || ''
                }));

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Videos');
            XLSX.writeFile(wb, 'videos_master_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        function importFromExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(firstSheet);

                    if (jsonData.length === 0) {
                        alert('No data found in the file');
                        return;
                    }

                    processImportedData(jsonData);
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error reading file: ' + error.message);
                }
            };
            reader.readAsArrayBuffer(file);
            document.getElementById('importFile').value = '';
        }

        function processImportedData(jsonData) {
            let successCount = 0;
            let errorCount = 0;
            const errors = [];

            const savePromises = jsonData.map((row, index) => {
                const sku = row['SKU'] || row['sku'];
                
                if (!sku || sku === '') {
                    errorCount++;
                    return Promise.resolve();
                }

                const videoData = {
                    sku: sku,
                    video_product_overview: row['Product Overview'] || '',
                    video_unboxing: row['Unboxing'] || '',
                    video_how_to: row['How To'] || '',
                    video_setup: row['Setup'] || '',
                    video_troubleshooting: row['Troubleshooting'] || '',
                    video_brand_story: row['Brand Story'] || '',
                    video_product_benefits: row['Product Benefits'] || ''
                };

                return fetch('/videos-master/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(videoData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successCount++;
                    } else {
                        errorCount++;
                        if (errors.length < 10) {
                            errors.push('Row ' + (index + 2) + ' (' + sku + '): ' + (data.message || 'Unknown error'));
                        }
                    }
                })
                .catch(err => {
                    errorCount++;
                    if (errors.length < 10) {
                        errors.push('Row ' + (index + 2) + ' (' + sku + '): ' + err.message);
                    }
                });
            });

            Promise.all(savePromises).then(() => {
                let message = 'Import completed!\n\nSuccess: ' + successCount + '\nErrors: ' + errorCount;
                
                if (errors.length > 0) {
                    message += '\n\nFirst errors:\n' + errors.join('\n');
                }
                
                alert(message);
                
                if (successCount > 0) {
                    loadVideoData();
                }
            });
        }

        // Apply all filters
        function applyFilters() {
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();
            const filterProductOverview = document.getElementById('filterProductOverview').value;
            const filterUnboxing = document.getElementById('filterUnboxing').value;
            const filterHowTo = document.getElementById('filterHowTo').value;
            const filterSetup = document.getElementById('filterSetup').value;
            const filterTroubleshooting = document.getElementById('filterTroubleshooting').value;
            const filterBrandStory = document.getElementById('filterBrandStory').value;
            const filterProductBenefits = document.getElementById('filterProductBenefits').value;

            const filteredData = tableData.filter(item => {
                // Skip parent rows
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                    return false;
                }

                // Parent search filter
                if (parentFilter && !(item.Parent && item.Parent.toLowerCase().includes(parentFilter))) {
                    return false;
                }

                // SKU search filter
                if (skuFilter && !(item.SKU && item.SKU.toLowerCase().includes(skuFilter))) {
                    return false;
                }

                // Product Overview filter
                if (filterProductOverview === 'missing' && !isMissing(item.video_product_overview)) {
                    return false;
                }

                // Unboxing filter
                if (filterUnboxing === 'missing' && !isMissing(item.video_unboxing)) {
                    return false;
                }

                // How To filter
                if (filterHowTo === 'missing' && !isMissing(item.video_how_to)) {
                    return false;
                }

                // Setup filter
                if (filterSetup === 'missing' && !isMissing(item.video_setup)) {
                    return false;
                }

                // Troubleshooting filter
                if (filterTroubleshooting === 'missing' && !isMissing(item.video_troubleshooting)) {
                    return false;
                }

                // Brand Story filter
                if (filterBrandStory === 'missing' && !isMissing(item.video_brand_story)) {
                    return false;
                }

                // Product Benefits filter
                if (filterProductBenefits === 'missing' && !isMissing(item.video_product_benefits)) {
                    return false;
                }

                return true;
            });

            renderTable(filteredData);
        }

        function setupSearchHandlers() {
            const parentSearch = document.getElementById('parentSearch');
            const skuSearch = document.getElementById('skuSearch');

            parentSearch.addEventListener('input', applyFilters);
            skuSearch.addEventListener('input', applyFilters);

            // Column filters
            document.getElementById('filterProductOverview').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterUnboxing').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterHowTo').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterSetup').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterTroubleshooting').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterBrandStory').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterProductBenefits').addEventListener('change', function() {
                applyFilters();
            });
        }

        function filterTable() {
            applyFilters();
        }

        // Check if value is missing (null, undefined, empty)
        function isMissing(value) {
            return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
        }

        function updateCounts() {
            const parentSet = new Set();
            let skuCount = 0;
            let productOverviewMissingCount = 0;
            let unboxingMissingCount = 0;
            let howToMissingCount = 0;
            let setupMissingCount = 0;
            let troubleshootingMissingCount = 0;
            let brandStoryMissingCount = 0;
            let productBenefitsMissingCount = 0;

            tableData.forEach(item => {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                    
                    // Count missing data for each video column
                    if (isMissing(item.video_product_overview)) productOverviewMissingCount++;
                    if (isMissing(item.video_unboxing)) unboxingMissingCount++;
                    if (isMissing(item.video_how_to)) howToMissingCount++;
                    if (isMissing(item.video_setup)) setupMissingCount++;
                    if (isMissing(item.video_troubleshooting)) troubleshootingMissingCount++;
                    if (isMissing(item.video_brand_story)) brandStoryMissingCount++;
                    if (isMissing(item.video_product_benefits)) productBenefitsMissingCount++;
                }
            });

            document.getElementById('parentCount').textContent = '(' + parentSet.size + ')';
            document.getElementById('skuCount').textContent = '(' + skuCount + ')';
            document.getElementById('productOverviewMissingCount').textContent = '(' + productOverviewMissingCount + ')';
            document.getElementById('unboxingMissingCount').textContent = '(' + unboxingMissingCount + ')';
            document.getElementById('howToMissingCount').textContent = '(' + howToMissingCount + ')';
            document.getElementById('setupMissingCount').textContent = '(' + setupMissingCount + ')';
            document.getElementById('troubleshootingMissingCount').textContent = '(' + troubleshootingMissingCount + ')';
            document.getElementById('brandStoryMissingCount').textContent = '(' + brandStoryMissingCount + ')';
            document.getElementById('productBenefitsMissingCount').textContent = '(' + productBenefitsMissingCount + ')';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function showError(message) {
            alert(message);
        }
        @endverbatim
    </script>
@endsection
