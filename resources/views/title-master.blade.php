@extends('layouts.vertical', ['title' => 'Title Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        .title-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

        .char-counter {
            font-size: 11px;
            color: #6c757d;
            float: right;
        }

        .char-counter.error {
            color: #dc3545;
        }

        .platform-selector-modal .platform-item {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .platform-selector-modal .platform-item:hover {
            border-color: #2c6ed5;
            background-color: #f8f9fa;
        }

        .platform-selector-modal .platform-item.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }

        .platform-selector-modal .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        .platform-icon {
            font-size: 20px;
            margin-right: 10px;
        }

        .platform-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Title Master',
        'sub_title' => 'Manage Product Titles',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <button id="addTitleBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Title
                            </button>
                            <button id="exportBtn" class="btn btn-primary ms-2">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-info ms-2">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <button id="updateAmazonBtn" class="btn btn-warning ms-2" style="display:none;">
                                <i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">0</span> selected)
                            </button>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="title-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" title="Select All">
                                    </th>
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
                                        <div>Title 150 <span id="title150MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle150" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 100 <span id="title100MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle100" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 80 <span id="title80MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle80" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                            <option value="all">All Data</option>
                                            <option value="missing">Missing Data</option>
                                        </select>
                                    </th>
                                    <th>
                                        <div>Title 60 <span id="title60MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle60" class="form-control form-control-sm mt-1" style="font-size: 11px;">
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
                        <div class="loading-text">Loading Title Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Title Modal -->
    <div class="modal fade" id="titleModal" tabindex="-1" aria-labelledby="titleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="titleModalLabel">
                        <i class="fas fa-edit me-2"></i><span id="modalTitle">Add Title</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="titleForm">
                        <input type="hidden" id="editSku" name="sku">
                        
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="title150" class="form-label">
                                Title 150 <span class="char-counter" id="counter150">0/150</span>
                            </label>
                            <textarea class="form-control" id="title150" name="title150" rows="3" maxlength="150"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="title100" class="form-label">
                                Title 100 <span class="char-counter" id="counter100">0/100</span>
                            </label>
                            <textarea class="form-control" id="title100" name="title100" rows="2" maxlength="100"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="title80" class="form-label">
                                Title 80 <span class="char-counter" id="counter80">0/80</span>
                            </label>
                            <textarea class="form-control" id="title80" name="title80" rows="2" maxlength="80"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="title60" class="form-label">
                                Title 60 <span class="char-counter" id="counter60">0/60</span>
                            </label>
                            <textarea class="form-control" id="title60" name="title60" rows="2" maxlength="60"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTitleBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Selection Modal -->
    <div class="modal fade" id="platformModal" tabindex="-1" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content platform-selector-modal">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="platformModalLabel">
                        <i class="fas fa-globe me-2"></i>Select Platforms to Update (<span id="platformSkuCount">0</span> SKUs)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select which platforms you want to update. Each platform will update its corresponding title field.
                    </div>

                    <div class="row">
                        <!-- Amazon -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('amazon')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="amazon" id="platform_amazon">
                                    <label class="form-check-label w-100" for="platform_amazon">
                                        <i class="fab fa-amazon platform-icon text-warning"></i>
                                        <strong>Amazon</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shopify -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shopify')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shopify" id="platform_shopify">
                                    <label class="form-check-label w-100" for="platform_shopify">
                                        <i class="fab fa-shopify platform-icon text-success"></i>
                                        <strong>Shopify</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 1 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay1')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay1" id="platform_ebay1">
                                    <label class="form-check-label w-100" for="platform_ebay1">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 1</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 2 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay2')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay2" id="platform_ebay2">
                                    <label class="form-check-label w-100" for="platform_ebay2">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 2</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 3 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay3')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay3" id="platform_ebay3">
                                    <label class="form-check-label w-100" for="platform_ebay3">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 3</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Walmart -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('walmart')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="walmart" id="platform_walmart">
                                    <label class="form-check-label w-100" for="platform_walmart">
                                        <i class="fas fa-store platform-icon text-primary"></i>
                                        <strong>Walmart</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Temu -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('temu')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="temu" id="platform_temu">
                                    <label class="form-check-label w-100" for="platform_temu">
                                        <i class="fas fa-shopping-bag platform-icon text-danger"></i>
                                        <strong>Temu</strong>
                                        <span class="badge bg-primary platform-badge">Title 150</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Doba -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('doba')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="doba" id="platform_doba">
                                    <label class="form-check-label w-100" for="platform_doba">
                                        <i class="fas fa-box platform-icon text-secondary"></i>
                                        <strong>Doba</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Updates will respect platform rate limits. This may take several seconds per product.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUpdateBtn">
                        <i class="fas fa-cloud-upload-alt"></i> Update Selected Platforms
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        @verbatim
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let titleModal;
        let platformModal;
        let selectedSkusForUpdate = [];

        document.addEventListener('DOMContentLoaded', function() {
            titleModal = new bootstrap.Modal(document.getElementById('titleModal'));
            platformModal = new bootstrap.Modal(document.getElementById('platformModal'));
            loadTitleData();
            setupSearchHandlers();
            setupModalHandlers();
            setupButtonHandlers();
            setupPlatformModalHandlers();
        });

        function setupButtonHandlers() {
            // Add Title Button
            document.getElementById('addTitleBtn').addEventListener('click', function() {
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

            // Update Titles Button - opens platform selection modal
            document.getElementById('updateAmazonBtn').addEventListener('click', function() {
                openPlatformSelectionModal();
            });
        }

        function setupPlatformModalHandlers() {
            // Confirm Update Button
            document.getElementById('confirmUpdateBtn').addEventListener('click', function() {
                updateSelectedPlatforms();
            });
        }

        function togglePlatform(platformId) {
            const checkbox = document.getElementById('platform_' + platformId);
            const platformItem = checkbox.closest('.platform-item');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                platformItem.classList.add('selected');
            } else {
                platformItem.classList.remove('selected');
            }
        }

        function openPlatformSelectionModal() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedSkusForUpdate = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-sku'));

            if (selectedSkusForUpdate.length === 0) {
                alert('Please select at least one product');
                return;
            }

            // Update SKU count in modal
            document.getElementById('platformSkuCount').textContent = selectedSkusForUpdate.length;

            // Reset all platform selections
            document.querySelectorAll('.platform-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelectorAll('[id^="platform_"]').forEach(cb => {
                cb.checked = false;
            });

            // Show the platform selection modal
            platformModal.show();
        }

        function updateSelectedPlatforms() {
            // Collect selected platforms
            const platforms = [];
            if (document.getElementById('platform_amazon').checked) platforms.push('amazon');
            if (document.getElementById('platform_shopify').checked) platforms.push('shopify');
            if (document.getElementById('platform_ebay1').checked) platforms.push('ebay1');
            if (document.getElementById('platform_ebay2').checked) platforms.push('ebay2');
            if (document.getElementById('platform_ebay3').checked) platforms.push('ebay3');
            if (document.getElementById('platform_walmart').checked) platforms.push('walmart');
            if (document.getElementById('platform_temu').checked) platforms.push('temu');
            if (document.getElementById('platform_doba').checked) platforms.push('doba');

            if (platforms.length === 0) {
                alert('Please select at least one platform to update');
                return;
            }

            // Platform display names
            const platformNames = {
                'amazon': 'Amazon (Title 150)',
                'shopify': 'Shopify (Title 100)',
                'ebay1': 'eBay 1 (Title 80)',
                'ebay2': 'eBay 2 (Title 80)',
                'ebay3': 'eBay 3 (Title 80)',
                'walmart': 'Walmart (Title 80)',
                'temu': 'Temu (Title 150)',
                'doba': 'Doba (Title 100)'
            };

            const platformList = platforms.map(p => platformNames[p]).join('\n');
            const confirmMsg = 'Update ' + selectedSkusForUpdate.length + ' product(s) to:\n\n' + platformList + '\n\nThis may take several seconds. Continue?';

            if (!confirm(confirmMsg)) {
                return;
            }

            // Hide platform modal and show processing
            platformModal.hide();

            const updateBtn = document.getElementById('updateAmazonBtn');
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Send to backend
            fetch('/title-master/update-platforms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ 
                    skus: selectedSkusForUpdate,
                    platforms: platforms
                })
            })
            .then(response => {
                // Check if response is JSON or HTML
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // It's HTML (error page), read as text
                    return response.text().then(html => {
                        console.error('Server returned HTML instead of JSON:', html);
                        throw new Error('Server error - check browser console for details');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    let message = 'Update Completed!\n\n';
                    
                    // Show results by platform
                    if (data.results) {
                        for (const [platform, result] of Object.entries(data.results)) {
                            const displayName = platformNames[platform] || platform.toUpperCase();
                            message += displayName + ': ';
                            message += 'Success: ' + result.success + ', Failed: ' + result.failed + '\n';
                        }
                    }
                    
                    message += '\nTotal Success: ' + data.total_success;
                    message += '\nTotal Failed: ' + data.total_failed;
                    
                    if (data.message && data.message.trim() !== '') {
                        message += '\n\nDetails:\n' + data.message;
                    }
                    
                    alert(message);
                    
                    // Uncheck all checkboxes
                    document.querySelectorAll('.row-checkbox:checked').forEach(cb => cb.checked = false);
                    document.getElementById('selectAll').checked = false;
                    updateSelectedCount();
                    
                    // Reload data
                    loadTitleData();
                } else {
                    alert('Error: ' + (data.message || 'Failed to update platforms'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating platforms: ' + error.message);
            })
            .finally(() => {
                updateBtn.disabled = false;
                const count = document.querySelectorAll('.row-checkbox:checked').length;
                updateBtn.innerHTML = '<i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">' + count + '</span> selected)';
                if (count === 0) {
                    updateBtn.style.display = 'none';
                }
            });
        }

        function setupCheckboxHandlers() {
            // Select All checkbox
            document.getElementById('selectAll').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.row-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });

            // Individual checkboxes
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectedCount);
            });
        }

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const count = checkedBoxes.length;
            const countElement = document.getElementById('selectedCount');
            if (countElement) {
                countElement.textContent = count;
            }
            const updateBtn = document.getElementById('updateAmazonBtn');
            if (updateBtn) {
                updateBtn.style.display = count > 0 ? 'inline-block' : 'none';
            }
        }

        function setupModalHandlers() {
            // Character counters
            const fields = ['title150', 'title100', 'title80', 'title60'];
            fields.forEach(field => {
                const maxLength = parseInt(field.replace('title', ''));
                const input = document.getElementById(field);
                const counter = document.getElementById('counter' + maxLength);
                
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = length + '/' + maxLength;
                    if (length > maxLength) {
                        counter.classList.add('error');
                    } else {
                        counter.classList.remove('error');
                    }
                });
            });

            // Save button
            document.getElementById('saveTitleBtn').addEventListener('click', function() {
                saveTitleFromModal();
            });
        }

        function loadTitleData() {
            document.getElementById('rainbow-loader').style.display = 'block';
            
            fetch('/product-master-data-view')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(response => {
                    // Handle both direct array and wrapped response
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
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">No products found</td></tr>';
                return;
            }

            // Filter out parent rows before rendering
            const filteredData = data.filter(item => {
                return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
            });

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
                const row = document.createElement('tr');

                // Checkbox
                const checkboxCell = document.createElement('td');
                checkboxCell.innerHTML = '<input type="checkbox" class="row-checkbox" data-sku="' + escapeHtml(item.SKU) + '">';
                row.appendChild(checkboxCell);

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

                // Title 150
                const title150Cell = document.createElement('td');
                title150Cell.className = 'title-text';
                title150Cell.textContent = item.title150 || '-';
                title150Cell.title = item.title150 || '';
                row.appendChild(title150Cell);

                // Title 100
                const title100Cell = document.createElement('td');
                title100Cell.className = 'title-text';
                title100Cell.textContent = item.title100 || '-';
                title100Cell.title = item.title100 || '';
                row.appendChild(title100Cell);

                // Title 80
                const title80Cell = document.createElement('td');
                title80Cell.className = 'title-text';
                title80Cell.textContent = item.title80 || '-';
                title80Cell.title = item.title80 || '';
                row.appendChild(title80Cell);

                // Title 60
                const title60Cell = document.createElement('td');
                title60Cell.className = 'title-text';
                title60Cell.textContent = item.title60 || '-';
                title60Cell.title = item.title60 || '';
                row.appendChild(title60Cell);

                // Action - Edit Button
                const actionCell = document.createElement('td');
                actionCell.innerHTML = '<button class="action-btn edit-btn" data-sku="' + escapeHtml(item.SKU) + '"><i class="fas fa-edit"></i> Edit</button>';
                row.appendChild(actionCell);

                tbody.appendChild(row);
            });

            setupEditButtons();
            setupCheckboxHandlers();
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
            const modal = document.getElementById('titleModal');
            const modalTitle = document.getElementById('modalTitle');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            
            // Reset form
            document.getElementById('titleForm').reset();
            ['title150', 'title100', 'title80', 'title60'].forEach(field => {
                const maxLength = parseInt(field.replace('title', ''));
                document.getElementById('counter' + maxLength).textContent = '0/' + maxLength;
                document.getElementById('counter' + maxLength).classList.remove('error');
            });

            if (mode === 'add') {
                modalTitle.textContent = 'Add Title';
                selectSku.style.display = 'block';
                selectSku.required = true;
                editSku.value = '';
                
                // Populate SKU dropdown
                selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                tableData.forEach(item => {
                    if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                        selectSku.innerHTML += '<option value="' + escapeHtml(item.SKU) + '">' + escapeHtml(item.SKU) + '</option>';
                    }
                });
            } else if (mode === 'edit' && sku) {
                modalTitle.textContent = 'Edit Title';
                selectSku.style.display = 'none';
                selectSku.required = false;
                editSku.value = sku;
                
                // Load existing data
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    ['title150', 'title100', 'title80', 'title60'].forEach(field => {
                        const input = document.getElementById(field);
                        input.value = item[field] || '';
                        const maxLength = parseInt(field.replace('title', ''));
                        const length = input.value.length;
                        document.getElementById('counter' + maxLength).textContent = length + '/' + maxLength;
                    });
                }
            }

            titleModal.show();
        }

        function saveTitleFromModal() {
            const form = document.getElementById('titleForm');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            const sku = editSku.value || selectSku.value;

            if (!sku) {
                alert('Please select a SKU');
                return;
            }

            const title150 = document.getElementById('title150').value;
            const title100 = document.getElementById('title100').value;
            const title80 = document.getElementById('title80').value;
            const title60 = document.getElementById('title60').value;

            const saveBtn = document.getElementById('saveTitleBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/title-master/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    sku: sku,
                    title150: title150,
                    title100: title100,
                    title80: title80,
                    title60: title60
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    titleModal.hide();
                    loadTitleData(); // Reload data
                    alert('Title saved successfully!');
                } else {
                    alert(data.message || 'Failed to save title');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving title: ' + error.message);
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
                    'Title 150': item.title150 || '',
                    'Title 100': item.title100 || '',
                    'Title 80': item.title80 || '',
                    'Title 60': item.title60 || ''
                }));

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Titles');
            XLSX.writeFile(wb, 'title_master_' + new Date().toISOString().split('T')[0] + '.xlsx');
        }

        function importFromExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    
                    // First, try to read with default (row 0 as header)
                    let jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    
                    console.log('First attempt - columns:', Object.keys(jsonData[0] || {}));
                    
                    // If we get __EMPTY columns, try reading from row 1 as header
                    const firstCol = Object.keys(jsonData[0] || {})[0];
                    if (firstCol && firstCol.includes('__EMPTY')) {
                        console.log('Detected merged cells or empty headers, trying range option...');
                        // Try reading with header at row 1 (index 1)
                        jsonData = XLSX.utils.sheet_to_json(firstSheet, { range: 1 });
                        console.log('Second attempt - columns:', Object.keys(jsonData[0] || {}));
                    }
                    
                    // Still empty? Try raw data approach
                    if (!jsonData || jsonData.length === 0 || Object.keys(jsonData[0])[0].includes('__EMPTY')) {
                        console.log('Still getting empty columns, reading as raw array...');
                        const rawData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                        console.log('Raw data first 3 rows:', rawData.slice(0, 3));
                        
                        // Find the header row (first row with non-empty values)
                        let headerRowIndex = -1;
                        for (let i = 0; i < Math.min(5, rawData.length); i++) {
                            const row = rawData[i];
                            if (row && row.some(cell => cell && cell.toString().trim() !== '')) {
                                headerRowIndex = i;
                                console.log('Found header row at index:', i, 'Values:', row);
                                break;
                            }
                        }
                        
                        if (headerRowIndex >= 0) {
                            // Convert to proper JSON with detected headers
                            const headers = rawData[headerRowIndex];
                            jsonData = [];
                            for (let i = headerRowIndex + 1; i < rawData.length; i++) {
                                const row = rawData[i];
                                if (!row || row.length === 0) continue;
                                
                                const obj = {};
                                for (let j = 0; j < headers.length; j++) {
                                    const header = headers[j] || 'Column_' + j;
                                    obj[header] = row[j];
                                }
                                jsonData.push(obj);
                            }
                            console.log('Converted data - columns:', Object.keys(jsonData[0] || {}));
                            console.log('First data row:', jsonData[0]);
                        }
                    }

                    if (jsonData.length === 0) {
                        alert('No data found in the file');
                        return;
                    }

                    console.log('Final Excel data loaded!');
                    console.log('Total rows:', jsonData.length);
                    console.log('Columns:', Object.keys(jsonData[0]));
                    console.log('First 3 rows:', jsonData.slice(0, 3));
                    
                    // Show user what columns we found
                    const cols = Object.keys(jsonData[0]).join(', ');
                    const proceed = confirm('Found ' + jsonData.length + ' rows with these columns:\n\n' + cols + '\n\nProceed with import?');
                    
                    if (proceed) {
                        // Process and save imported data
                        processImportedData(jsonData);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error reading file: ' + error.message);
                }
            };
            reader.readAsArrayBuffer(file);
            document.getElementById('importFile').value = ''; // Reset file input
        }

        function processImportedData(jsonData) {
            let successCount = 0;
            let errorCount = 0;
            let skippedCount = 0;
            const totalRows = jsonData.length;
            const errors = [];

            // Log first row to see column names
            if (jsonData.length > 0) {
                console.log('=== EXCEL COLUMNS FOUND ===');
                console.log('All columns:', Object.keys(jsonData[0]));
                console.log('First row full data:', jsonData[0]);
                console.log('Second row data:', jsonData[1]);
            }

            // Detect SKU column dynamically - try all columns to find one with SKU-like data
            let skuColumnName = null;
            if (jsonData.length > 0) {
                const firstRow = jsonData[0];
                
                // First priority: columns with 'sku' or 'child' in name
                for (const colName of Object.keys(firstRow)) {
                    const lower = colName.toLowerCase();
                    if ((lower.includes('sku') || lower.includes('child')) && 
                        firstRow[colName] && 
                        firstRow[colName].toString().trim() !== '' &&
                        firstRow[colName].toString().trim() !== '__EMPTY' &&
                        firstRow[colName].toString().trim() !== '0') {
                        skuColumnName = colName;
                        console.log('✓ Found SKU column (priority): "' + skuColumnName + '" = "' + firstRow[colName] + '"');
                        break;
                    }
                }
                
                // Second priority: any column with actual data that looks like SKU
                if (!skuColumnName) {
                    for (const [colName, value] of Object.entries(firstRow)) {
                        const val = value ? value.toString().trim() : '';
                        if (val && val !== '__EMPTY' && val !== '0' && val !== '' && val.length > 2) {
                            skuColumnName = colName;
                            console.log('✓ Found SKU column (fallback): "' + skuColumnName + '" = "' + val + '"');
                            break;
                        }
                    }
                }
            }

            if (!skuColumnName) {
                console.error('❌ Available columns:', Object.keys(jsonData[0]));
                console.error('❌ First row values:', Object.values(jsonData[0]));
                alert('Error: Could not detect SKU column in Excel file.\nPlease check the console for available columns and their values.');
                return;
            }

            // Detect title columns - more flexible matching
            const titleColumns = {
                title150: null,
                title100: null,
                title80: null,
                title60: null
            };

            if (jsonData.length > 0) {
                const columns = Object.keys(jsonData[0]);
                for (const colName of columns) {
                    const lower = colName.toLowerCase();
                    
                    // Match Amazon/150 column
                    if (!titleColumns.title150 && (lower.includes('amazon') || lower.includes('150'))) {
                        titleColumns.title150 = colName;
                    }
                    // Match Shopify/100 column
                    else if (!titleColumns.title100 && (lower.includes('shopify') || (lower.includes('100') && !lower.includes('150')))) {
                        titleColumns.title100 = colName;
                    }
                    // Match eBay/80 column
                    else if (!titleColumns.title80 && (lower.includes('ebay') || (lower.includes('80') && !lower.includes('180')))) {
                        titleColumns.title80 = colName;
                    }
                    // Match Faire/60 column
                    else if (!titleColumns.title60 && (lower.includes('faire') || (lower.includes('60') && !lower.includes('160')))) {
                        titleColumns.title60 = colName;
                    }
                }
                console.log('✓ Detected title columns:', titleColumns);
            }

            const savePromises = jsonData.map((row, index) => {
                // Get SKU from detected column
                const sku = row[skuColumnName];
                
                // Check if SKU contains the word "PARENT" (case-insensitive, as a whole word)
                const skuStr = sku ? sku.toString().trim() : '';
                const isParentSKU = /\bPARENT\b/i.test(skuStr);
                
                if (!sku || skuStr === '' || sku === '__EMPTY' || skuStr === '0' || isParentSKU) {
                    skippedCount++;
                    if (skippedCount <= 3) {
                        const reason = !sku || skuStr === '' ? 'Empty' : isParentSKU ? 'Parent' : 'Invalid';
                        console.log('⊘ Skipped row ' + (index + 2) + ': "' + skuStr + '" (' + reason + ')');
                    }
                    return Promise.resolve();
                }

                // Extract title data using detected columns
                const title150 = titleColumns.title150 ? (row[titleColumns.title150] || '').toString().substring(0, 150) : '';
                const title100 = titleColumns.title100 ? (row[titleColumns.title100] || '').toString().substring(0, 100) : '';
                const title80 = titleColumns.title80 ? (row[titleColumns.title80] || '').toString().substring(0, 80) : '';
                const title60 = titleColumns.title60 ? (row[titleColumns.title60] || '').toString().substring(0, 60) : '';

                if (successCount + errorCount < 3) {
                    console.log('→ Processing row ' + (index + 2) + ': SKU="' + skuStr + '"');
                }

                return fetch('/title-master/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        sku: skuStr,
                        title150: title150,
                        title100: title100,
                        title80: title80,
                        title60: title60
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successCount++;
                        if (successCount <= 3) {
                            console.log('✓ Row ' + (index + 2) + ' success: ' + skuStr);
                        }
                    } else {
                        errorCount++;
                        const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + (data.message || 'Unknown error');
                        if (errorCount <= 10) {
                            console.error('✗ ' + errorMsg);
                            errors.push(errorMsg);
                        }
                    }
                })
                .catch(err => {
                    errorCount++;
                    const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + err.message;
                    if (errorCount <= 10) {
                        console.error('✗ ' + errorMsg);
                        errors.push(errorMsg);
                    }
                });
            });

            Promise.all(savePromises).then(() => {
                let message = `Import completed!\n\nSuccess: ${successCount}\nErrors: ${errorCount}\nSkipped (Parent/Empty): ${skippedCount}\nTotal: ${totalRows}`;
                
                if (errors.length > 0) {
                    message += '\n\nFirst errors:\n' + errors.join('\n');
                }
                
                console.log('=== IMPORT SUMMARY ===');
                console.log('Success:', successCount);
                console.log('Errors:', errorCount);
                console.log('Skipped:', skippedCount);
                console.log('Total:', totalRows);
                
                alert(message);
                
                if (successCount > 0) {
                    loadTitleData(); // Reload data to show updates
                }
            });
        }

        // Apply all filters
        function applyFilters() {
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();
            const filterTitle150 = document.getElementById('filterTitle150').value;
            const filterTitle100 = document.getElementById('filterTitle100').value;
            const filterTitle80 = document.getElementById('filterTitle80').value;
            const filterTitle60 = document.getElementById('filterTitle60').value;

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

                // Title 150 filter
                if (filterTitle150 === 'missing' && !isMissing(item.title150)) {
                    return false;
                }

                // Title 100 filter
                if (filterTitle100 === 'missing' && !isMissing(item.title100)) {
                    return false;
                }

                // Title 80 filter
                if (filterTitle80 === 'missing' && !isMissing(item.title80)) {
                    return false;
                }

                // Title 60 filter
                if (filterTitle60 === 'missing' && !isMissing(item.title60)) {
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
            document.getElementById('filterTitle150').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterTitle100').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterTitle80').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterTitle60').addEventListener('change', function() {
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
            let title150MissingCount = 0;
            let title100MissingCount = 0;
            let title80MissingCount = 0;
            let title60MissingCount = 0;

            tableData.forEach(item => {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                    
                    // Count missing data for each title column
                    if (isMissing(item.title150)) title150MissingCount++;
                    if (isMissing(item.title100)) title100MissingCount++;
                    if (isMissing(item.title80)) title80MissingCount++;
                    if (isMissing(item.title60)) title60MissingCount++;
                }
            });

            document.getElementById('parentCount').textContent = `(${parentSet.size})`;
            document.getElementById('skuCount').textContent = `(${skuCount})`;
            document.getElementById('title150MissingCount').textContent = `(${title150MissingCount})`;
            document.getElementById('title100MissingCount').textContent = `(${title100MissingCount})`;
            document.getElementById('title80MissingCount').textContent = `(${title80MissingCount})`;
            document.getElementById('title60MissingCount').textContent = `(${title60MissingCount})`;
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
