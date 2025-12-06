@extends('layouts.vertical', ['title' => 'Bullet Points Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        .bullet-text {
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
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Bullet Points Master',
        'sub_title' => 'Manage Product Bullet Points',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <button id="addBulletBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Bullet Points
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
                        <table id="bullet-master-table" class="table dt-responsive nowrap w-100">
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
                                    <th>Bullet 1</th>
                                    <th>Bullet 2</th>
                                    <th>Bullet 3</th>
                                    <th>Bullet 4</th>
                                    <th>Bullet 5</th>
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
                        <div class="loading-text">Loading Bullet Points Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Bullet Points Modal -->
    <div class="modal fade" id="bulletModal" tabindex="-1" aria-labelledby="bulletModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="bulletModalLabel">
                        <i class="fas fa-list me-2"></i><span id="modalTitle">Add Bullet Points</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bulletForm">
                        <input type="hidden" id="editSku" name="sku">
                        
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="bullet1" class="form-label">
                                Bullet 1 <span class="char-counter" id="counter1">0/200</span>
                            </label>
                            <textarea class="form-control" id="bullet1" name="bullet1" rows="2" maxlength="200"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="bullet2" class="form-label">
                                Bullet 2 <span class="char-counter" id="counter2">0/200</span>
                            </label>
                            <textarea class="form-control" id="bullet2" name="bullet2" rows="2" maxlength="200"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="bullet3" class="form-label">
                                Bullet 3 <span class="char-counter" id="counter3">0/200</span>
                            </label>
                            <textarea class="form-control" id="bullet3" name="bullet3" rows="2" maxlength="200"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="bullet4" class="form-label">
                                Bullet 4 <span class="char-counter" id="counter4">0/200</span>
                            </label>
                            <textarea class="form-control" id="bullet4" name="bullet4" rows="2" maxlength="200"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="bullet5" class="form-label">
                                Bullet 5 <span class="char-counter" id="counter5">0/200</span>
                            </label>
                            <textarea class="form-control" id="bullet5" name="bullet5" rows="2" maxlength="200"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBulletBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let bulletModal;

        document.addEventListener('DOMContentLoaded', function() {
            bulletModal = new bootstrap.Modal(document.getElementById('bulletModal'));
            loadBulletData();
            setupSearchHandlers();
            setupModalHandlers();
            setupButtonHandlers();
        });

        function setupButtonHandlers() {
            // Add Bullet Button
            document.getElementById('addBulletBtn').addEventListener('click', function() {
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
        }

        function setupModalHandlers() {
            // Character counters
            const fields = ['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'];
            fields.forEach((field, index) => {
                const input = document.getElementById(field);
                const counter = document.getElementById('counter' + (index + 1));
                
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.textContent = `${length}/200`;
                    if (length > 200) {
                        counter.classList.add('error');
                    } else {
                        counter.classList.remove('error');
                    }
                });
            });

            // Save button
            document.getElementById('saveBulletBtn').addEventListener('click', function() {
                saveBulletFromModal();
            });
        }

        function loadBulletData() {
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
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">No products found</td></tr>';
                return;
            }

            data.forEach(item => {
                // Skip parent rows
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                    return;
                }

                const row = document.createElement('tr');

                // Images
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

                // Bullet Points 1-5
                for (let i = 1; i <= 5; i++) {
                    const bulletCell = document.createElement('td');
                    bulletCell.className = 'bullet-text';
                    bulletCell.textContent = item['bullet' + i] || '-';
                    bulletCell.title = item['bullet' + i] || '';
                    row.appendChild(bulletCell);
                }

                // Action - Edit Button
                const actionCell = document.createElement('td');
                actionCell.innerHTML = `<button class="action-btn edit-btn" data-sku="${escapeHtml(item.SKU)}">
                    <i class="fas fa-edit"></i> Edit
                </button>`;
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
            const modal = document.getElementById('bulletModal');
            const modalTitle = document.getElementById('modalTitle');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            
            // Reset form
            document.getElementById('bulletForm').reset();
            for (let i = 1; i <= 5; i++) {
                document.getElementById('counter' + i).textContent = '0/200';
                document.getElementById('counter' + i).classList.remove('error');
            }

            if (mode === 'add') {
                modalTitle.textContent = 'Add Bullet Points';
                selectSku.style.display = 'block';
                selectSku.required = true;
                editSku.value = '';
                
                // Populate SKU dropdown
                selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                tableData.forEach(item => {
                    if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                        selectSku.innerHTML += `<option value="${escapeHtml(item.SKU)}">${escapeHtml(item.SKU)}</option>`;
                    }
                });
            } else if (mode === 'edit' && sku) {
                modalTitle.textContent = 'Edit Bullet Points';
                selectSku.style.display = 'none';
                selectSku.required = false;
                editSku.value = sku;
                
                // Load existing data
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    for (let i = 1; i <= 5; i++) {
                        const input = document.getElementById('bullet' + i);
                        input.value = item['bullet' + i] || '';
                        const length = input.value.length;
                        document.getElementById('counter' + i).textContent = `${length}/200`;
                    }
                }
            }

            bulletModal.show();
        }

        function saveBulletFromModal() {
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            const sku = editSku.value || selectSku.value;

            if (!sku) {
                alert('Please select a SKU');
                return;
            }

            const bulletData = {};
            for (let i = 1; i <= 5; i++) {
                bulletData['bullet' + i] = document.getElementById('bullet' + i).value;
            }

            const saveBtn = document.getElementById('saveBulletBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/bullet-points/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    sku: sku,
                    ...bulletData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bulletModal.hide();
                    loadBulletData();
                    alert('Bullet points saved successfully!');
                } else {
                    alert(data.message || 'Failed to save bullet points');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving bullet points: ' + error.message);
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
                    'Bullet 1': item.bullet1 || '',
                    'Bullet 2': item.bullet2 || '',
                    'Bullet 3': item.bullet3 || '',
                    'Bullet 4': item.bullet4 || '',
                    'Bullet 5': item.bullet5 || ''
                }));

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Bullet Points');
            XLSX.writeFile(wb, 'bullet_points_' + new Date().toISOString().split('T')[0] + '.xlsx');
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
            const totalRows = jsonData.length;

            const savePromises = jsonData.map(row => {
                const sku = row['SKU'] || row['sku'];
                if (!sku) {
                    errorCount++;
                    return Promise.resolve();
                }

                const bulletData = {};
                for (let i = 1; i <= 5; i++) {
                    bulletData['bullet' + i] = (row['Bullet ' + i] || row['bullet' + i] || '').substring(0, 200);
                }

                return fetch('/bullet-points/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        sku: sku,
                        ...bulletData
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        successCount++;
                    } else {
                        errorCount++;
                    }
                })
                .catch(() => {
                    errorCount++;
                });
            });

            Promise.all(savePromises).then(() => {
                alert(`Import completed!\nSuccess: ${successCount}\nErrors: ${errorCount}\nTotal: ${totalRows}`);
                loadBulletData();
            });
        }

        function setupSearchHandlers() {
            const parentSearch = document.getElementById('parentSearch');
            const skuSearch = document.getElementById('skuSearch');

            parentSearch.addEventListener('input', filterTable);
            skuSearch.addEventListener('input', filterTable);
        }

        function filterTable() {
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();

            const filteredData = tableData.filter(item => {
                const parentMatch = !parentFilter || (item.Parent && item.Parent.toLowerCase().includes(parentFilter));
                const skuMatch = !skuFilter || (item.SKU && item.SKU.toLowerCase().includes(skuFilter));
                return parentMatch && skuMatch;
            });

            renderTable(filteredData);
        }

        function updateCounts() {
            const parentSet = new Set();
            let skuCount = 0;

            tableData.forEach(item => {
                if (item.Parent) parentSet.add(item.Parent);
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    skuCount++;
                }
            });

            document.getElementById('parentCount').textContent = `(${parentSet.size})`;
            document.getElementById('skuCount').textContent = `(${skuCount})`;
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
    </script>
@endsection
