@extends('layouts.vertical', ['title' => 'Images'])

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

        .image-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin: 2px;
            cursor: pointer;
        }

        .image-preview:hover {
            opacity: 0.8;
        }

        .image-preview.error {
            display: none;
        }

        .form-control.url-input {
            font-size: 12px;
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
                            <li class="breadcrumb-item active">Images</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Images</h4>
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
                                <i class="fas fa-plus"></i> Add Images
                            </button>
                        </div>

                        <div class="table-container">
                            <table id="product-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Parent</th>
                                        <th>SKU</th>
                                        <th>
                                            <div>Main Image <span id="mainImageMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterMainImage" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Main Image Brand <span id="mainImageBrandMissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterMainImageBrand" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 1 <span id="image1MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage1" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 2 <span id="image2MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage2" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 3 <span id="image3MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage3" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 4 <span id="image4MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage4" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 5 <span id="image5MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage5" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 6 <span id="image6MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage6" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 7 <span id="image7MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage7" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 8 <span id="image8MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage8" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 9 <span id="image9MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage9" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 10 <span id="image10MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage10" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 11 <span id="image11MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage11" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>
                                            <div>Image 12 <span id="image12MissingCount" class="text-danger" style="font-weight: bold;">(0)</span></div>
                                            <select id="filterImage12" class="form-control form-control-sm mt-1" style="font-size: 11px;">
                                                <option value="all">All Data</option>
                                                <option value="missing">Missing Data</option>
                                            </select>
                                        </th>
                                        <th>Action</th>
                                    </tr>
                                    <tr>
                                        <th></th>
                                        <th><input type="text" class="form-control form-control-sm search-input" placeholder="Search Parent" data-column="Parent"></th>
                                        <th><input type="text" class="form-control form-control-sm search-input" placeholder="Search SKU" data-column="SKU"></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="table-body">
                                    <tr>
                                        <td colspan="18" class="text-center">Loading...</td>
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
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <form id="imageForm">
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
                            <label for="mainImage" class="form-label">Main Image</label>
                            <input type="url" class="form-control url-input" id="mainImage" name="main_image" placeholder="Enter image URL">
                        </div>

                        <div class="mb-3">
                            <label for="mainImageBrand" class="form-label">Main Image Brand</label>
                            <input type="url" class="form-control url-input" id="mainImageBrand" name="main_image_brand" placeholder="Enter image URL">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image1" class="form-label">Image 1</label>
                                <input type="url" class="form-control url-input" id="image1" name="image1" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image2" class="form-label">Image 2</label>
                                <input type="url" class="form-control url-input" id="image2" name="image2" placeholder="Enter image URL">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image3" class="form-label">Image 3</label>
                                <input type="url" class="form-control url-input" id="image3" name="image3" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image4" class="form-label">Image 4</label>
                                <input type="url" class="form-control url-input" id="image4" name="image4" placeholder="Enter image URL">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image5" class="form-label">Image 5</label>
                                <input type="url" class="form-control url-input" id="image5" name="image5" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image6" class="form-label">Image 6</label>
                                <input type="url" class="form-control url-input" id="image6" name="image6" placeholder="Enter image URL">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image7" class="form-label">Image 7</label>
                                <input type="url" class="form-control url-input" id="image7" name="image7" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image8" class="form-label">Image 8</label>
                                <input type="url" class="form-control url-input" id="image8" name="image8" placeholder="Enter image URL">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image9" class="form-label">Image 9</label>
                                <input type="url" class="form-control url-input" id="image9" name="image9" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image10" class="form-label">Image 10</label>
                                <input type="url" class="form-control url-input" id="image10" name="image10" placeholder="Enter image URL">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="image11" class="form-label">Image 11</label>
                                <input type="url" class="form-control url-input" id="image11" name="image11" placeholder="Enter image URL">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image12" class="form-label">Image 12</label>
                                <input type="url" class="form-control url-input" id="image12" name="image12" placeholder="Enter image URL">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveImages()">
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
        let imageModal;

        const imageFields = [
            'main_image', 'main_image_brand', 
            'image1', 'image2', 'image3', 'image4', 'image5', 'image6',
            'image7', 'image8', 'image9', 'image10', 'image11', 'image12'
        ];

        document.addEventListener('DOMContentLoaded', function() {
            imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            loadImageData();
            setupSearchHandlers();
            setupSKUChangeHandler();
        });

        function loadImageData() {
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
                tbody.innerHTML = '<tr><td colspan="18" class="text-center">No products found</td></tr>';
                return;
            }

            // Filter out parent rows before rendering
            const filteredData = data.filter(item => {
                return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
            });

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="18" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
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

                // Main Image
                const mainImageCell = document.createElement('td');
                if (item.main_image && item.main_image.trim()) {
                    const cleanUrl = item.main_image.trim();
                    mainImageCell.innerHTML = `<img src="${cleanUrl}" class="image-preview" onerror="this.style.display='none'; this.parentElement.textContent='-';" onclick="window.open('${cleanUrl}', '_blank')">`;
                } else {
                    mainImageCell.textContent = '-';
                }
                row.appendChild(mainImageCell);

                // Main Image Brand
                const mainImageBrandCell = document.createElement('td');
                if (item.main_image_brand && item.main_image_brand.trim()) {
                    const cleanUrl = item.main_image_brand.trim();
                    mainImageBrandCell.innerHTML = `<img src="${cleanUrl}" class="image-preview" onerror="this.style.display='none'; this.parentElement.textContent='-';" onclick="window.open('${cleanUrl}', '_blank')">`;
                } else {
                    mainImageBrandCell.textContent = '-';
                }
                row.appendChild(mainImageBrandCell);

                // Image 1-12
                for (let i = 1; i <= 12; i++) {
                    const imgCell = document.createElement('td');
                    const imgValue = item[`image${i}`];
                    
                    if (imgValue && imgValue.trim()) {
                        // Clean the URL - handle cases where URLs might be concatenated
                        let cleanUrl = imgValue.trim();
                        
                        // Check if URL contains multiple http/https - take only the first one
                        const urlMatch = cleanUrl.match(/(https?:\/\/[^\s]+?)(?=https?:\/\/|$)/);
                        if (urlMatch) {
                            cleanUrl = urlMatch[1];
                        }
                        
                        // Remove any trailing junk after valid image extensions
                        cleanUrl = cleanUrl.replace(/\.(jpg|jpeg|png|gif|webp|svg)\..*/i, '.$1');
                        
                        imgCell.innerHTML = `<img src="${cleanUrl}" class="image-preview" onerror="this.style.display='none'; this.parentElement.textContent='-';" onclick="window.open('${cleanUrl}', '_blank')" title="${cleanUrl}">`;
                    } else {
                        imgCell.textContent = '-';
                    }
                    row.appendChild(imgCell);
                }

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

        // Check if value is missing (null, undefined, empty)
        function isMissing(value) {
            return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
        }

        function updateCounts() {
            let mainImageMissingCount = 0;
            let mainImageBrandMissingCount = 0;
            let image1MissingCount = 0;
            let image2MissingCount = 0;
            let image3MissingCount = 0;
            let image4MissingCount = 0;
            let image5MissingCount = 0;
            let image6MissingCount = 0;
            let image7MissingCount = 0;
            let image8MissingCount = 0;
            let image9MissingCount = 0;
            let image10MissingCount = 0;
            let image11MissingCount = 0;
            let image12MissingCount = 0;

            tableData.forEach(item => {
                if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT')) {
                    // Count missing data for each image column
                    if (isMissing(item.main_image)) mainImageMissingCount++;
                    if (isMissing(item.main_image_brand)) mainImageBrandMissingCount++;
                    if (isMissing(item.image1)) image1MissingCount++;
                    if (isMissing(item.image2)) image2MissingCount++;
                    if (isMissing(item.image3)) image3MissingCount++;
                    if (isMissing(item.image4)) image4MissingCount++;
                    if (isMissing(item.image5)) image5MissingCount++;
                    if (isMissing(item.image6)) image6MissingCount++;
                    if (isMissing(item.image7)) image7MissingCount++;
                    if (isMissing(item.image8)) image8MissingCount++;
                    if (isMissing(item.image9)) image9MissingCount++;
                    if (isMissing(item.image10)) image10MissingCount++;
                    if (isMissing(item.image11)) image11MissingCount++;
                    if (isMissing(item.image12)) image12MissingCount++;
                }
            });

            document.getElementById('mainImageMissingCount').textContent = `(${mainImageMissingCount})`;
            document.getElementById('mainImageBrandMissingCount').textContent = `(${mainImageBrandMissingCount})`;
            document.getElementById('image1MissingCount').textContent = `(${image1MissingCount})`;
            document.getElementById('image2MissingCount').textContent = `(${image2MissingCount})`;
            document.getElementById('image3MissingCount').textContent = `(${image3MissingCount})`;
            document.getElementById('image4MissingCount').textContent = `(${image4MissingCount})`;
            document.getElementById('image5MissingCount').textContent = `(${image5MissingCount})`;
            document.getElementById('image6MissingCount').textContent = `(${image6MissingCount})`;
            document.getElementById('image7MissingCount').textContent = `(${image7MissingCount})`;
            document.getElementById('image8MissingCount').textContent = `(${image8MissingCount})`;
            document.getElementById('image9MissingCount').textContent = `(${image9MissingCount})`;
            document.getElementById('image10MissingCount').textContent = `(${image10MissingCount})`;
            document.getElementById('image11MissingCount').textContent = `(${image11MissingCount})`;
            document.getElementById('image12MissingCount').textContent = `(${image12MissingCount})`;
        }

        function setupSearchHandlers() {
            document.querySelectorAll('.search-input').forEach(input => {
                input.addEventListener('input', function() {
                    applyFilters();
                });
            });

            // Column filters
            document.getElementById('filterMainImage').addEventListener('change', function() {
                applyFilters();
            });

            document.getElementById('filterMainImageBrand').addEventListener('change', function() {
                applyFilters();
            });

            for (let i = 1; i <= 12; i++) {
                document.getElementById(`filterImage${i}`).addEventListener('change', function() {
                    applyFilters();
                });
            }
        }

        // Apply all filters
        function applyFilters() {
            const filters = {};
            document.querySelectorAll('.search-input').forEach(input => {
                const column = input.getAttribute('data-column');
                const value = input.value.toLowerCase().trim();
                if (value) {
                    filters[column] = value;
                }
            });

            const filterMainImage = document.getElementById('filterMainImage').value;
            const filterMainImageBrand = document.getElementById('filterMainImageBrand').value;
            const filterImage1 = document.getElementById('filterImage1').value;
            const filterImage2 = document.getElementById('filterImage2').value;
            const filterImage3 = document.getElementById('filterImage3').value;
            const filterImage4 = document.getElementById('filterImage4').value;
            const filterImage5 = document.getElementById('filterImage5').value;
            const filterImage6 = document.getElementById('filterImage6').value;
            const filterImage7 = document.getElementById('filterImage7').value;
            const filterImage8 = document.getElementById('filterImage8').value;
            const filterImage9 = document.getElementById('filterImage9').value;
            const filterImage10 = document.getElementById('filterImage10').value;
            const filterImage11 = document.getElementById('filterImage11').value;
            const filterImage12 = document.getElementById('filterImage12').value;

            const filteredData = tableData.filter(item => {
                // Skip parent rows
                if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) {
                    return false;
                }

                // Text search filters
                for (const [column, value] of Object.entries(filters)) {
                    const itemValue = String(item[column] || '').toLowerCase();
                    if (!itemValue.includes(value)) {
                        return false;
                    }
                }

                // Main Image filter
                if (filterMainImage === 'missing' && !isMissing(item.main_image)) {
                    return false;
                }

                // Main Image Brand filter
                if (filterMainImageBrand === 'missing' && !isMissing(item.main_image_brand)) {
                    return false;
                }

                // Image 1-12 filters
                if (filterImage1 === 'missing' && !isMissing(item.image1)) {
                    return false;
                }
                if (filterImage2 === 'missing' && !isMissing(item.image2)) {
                    return false;
                }
                if (filterImage3 === 'missing' && !isMissing(item.image3)) {
                    return false;
                }
                if (filterImage4 === 'missing' && !isMissing(item.image4)) {
                    return false;
                }
                if (filterImage5 === 'missing' && !isMissing(item.image5)) {
                    return false;
                }
                if (filterImage6 === 'missing' && !isMissing(item.image6)) {
                    return false;
                }
                if (filterImage7 === 'missing' && !isMissing(item.image7)) {
                    return false;
                }
                if (filterImage8 === 'missing' && !isMissing(item.image8)) {
                    return false;
                }
                if (filterImage9 === 'missing' && !isMissing(item.image9)) {
                    return false;
                }
                if (filterImage10 === 'missing' && !isMissing(item.image10)) {
                    return false;
                }
                if (filterImage11 === 'missing' && !isMissing(item.image11)) {
                    return false;
                }
                if (filterImage12 === 'missing' && !isMissing(item.image12)) {
                    return false;
                }

                return true;
            });

            renderTable(filteredData);
        }

        function filterTable() {
            applyFilters();
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
                        imageFields.forEach(field => {
                            const inputId = field === 'main_image' ? 'mainImage' : 
                                          field === 'main_image_brand' ? 'mainImageBrand' : field;
                            document.getElementById(inputId).value = item[field] || '';
                        });
                    }
                }
            });
        }

        function openModal(sku = null) {
            // Reset form
            document.getElementById('imageForm').reset();
            document.getElementById('modalParent').value = '';

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
                document.getElementById('modalTitle').textContent = 'Edit Images';
                const item = tableData.find(d => d.SKU === sku);
                if (item) {
                    document.getElementById('modalParent').value = item.Parent || '';
                    imageFields.forEach(field => {
                        const inputId = field === 'main_image' ? 'mainImage' : 
                                      field === 'main_image_brand' ? 'mainImageBrand' : field;
                        document.getElementById(inputId).value = item[field] || '';
                    });
                }
            } else {
                document.getElementById('modalTitle').textContent = 'Add Images';
                
                // Initialize Select2 with searchable dropdown for add mode
                $(skuSelect).select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select SKU',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#imageModal')
                });
            }

            // Clean up Select2 when modal is hidden
            const modalElement = document.getElementById('imageModal');
            modalElement.addEventListener('hidden.bs.modal', function() {
                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
            }, { once: true });

            imageModal.show();
        }

        function saveImages() {
            const skuSelect = document.getElementById('modalSKU');
            // Get SKU value - use Select2 .val() if initialized, otherwise use .value
            const sku = $(skuSelect).hasClass('select2-hidden-accessible') ? $(skuSelect).val() : skuSelect.value;
            
            if (!sku) {
                showError('Please select a SKU');
                return;
            }

            const imageData = {
                sku: sku,
                main_image: document.getElementById('mainImage').value,
                main_image_brand: document.getElementById('mainImageBrand').value
            };

            for (let i = 1; i <= 12; i++) {
                imageData[`image${i}`] = document.getElementById(`image${i}`).value;
            }

            document.getElementById('rainbow-loader').style.display = 'block';

            fetch('/product-images/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(imageData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message || 'Images saved successfully!');
                        imageModal.hide();
                        loadImageData();
                    } else {
                        showError(data.message || 'Failed to save images');
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
                    'Main Image': item.main_image || '',
                    'Main Image Brand': item.main_image_brand || '',
                    'Image 1': item.image1 || '',
                    'Image 2': item.image2 || '',
                    'Image 3': item.image3 || '',
                    'Image 4': item.image4 || '',
                    'Image 5': item.image5 || '',
                    'Image 6': item.image6 || '',
                    'Image 7': item.image7 || '',
                    'Image 8': item.image8 || '',
                    'Image 9': item.image9 || '',
                    'Image 10': item.image10 || '',
                    'Image 11': item.image11 || '',
                    'Image 12': item.image12 || ''
                }));

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Images');

            const timestamp = new Date().toISOString().slice(0, 10);
            XLSX.writeFile(wb, `images_${timestamp}.xlsx`);
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
                        
                        if (!sku) {
                            errors++;
                            return Promise.resolve();
                        }

                        const imageData = {
                            sku: sku,
                            main_image: row['Main Image'] || row['main_image'] || '',
                            main_image_brand: row['Main Image Brand'] || row['main_image_brand'] || ''
                        };

                        for (let i = 1; i <= 12; i++) {
                            imageData[`image${i}`] = row[`Image ${i}`] || row[`image${i}`] || '';
                        }

                        return fetch('/product-images/save', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(imageData)
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
                        loadImageData();
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

