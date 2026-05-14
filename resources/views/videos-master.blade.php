@extends('layouts.vertical', ['title' => 'Videos Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical Column Headers - Same as Hero Images Master */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 100px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .parent-row {
            background-color: #fffacd !important;
        }

        .copy-sku-btn {
            cursor: pointer;
            padding: 2px 5px;
            margin-left: 5px;
        }

        /* Video Link Icon Styling */
        .video-link-icon {
            display: inline-block;
            color: #17a2b8;
            font-size: 24px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .video-link-icon:hover {
            color: #138496;
            transform: scale(1.15);
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Videos Master</h4>
                    <div class="btn-group">
                        <button id="addVideoBtn" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Add
                        </button>
                        <button id="importBtn" class="btn btn-sm btn-info">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <button id="exportBtn" class="btn btn-sm btn-primary">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                    </div>
                </div>
                
                <div class="card-body" style="padding: 0;">
                    <div id="videos-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                        <!-- Search Bar -->
                        <div class="p-2 bg-light border-bottom">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" id="general-search" class="form-control form-control-sm" placeholder="Search all columns...">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" id="parentSearch" class="form-control form-control-sm" placeholder="Search Parent... (0)">
                                </div>
                                <div class="col-md-3">
                                    <input type="text" id="skuSearch" class="form-control form-control-sm" placeholder="Search SKU... (0)">
                                </div>
                                <div class="col-md-2">
                                    <select id="filterVideos" class="form-control form-control-sm">
                                        <option value="all">All Videos</option>
                                        <option value="missing">Missing Only</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Missing Counts -->
                            <div class="mt-2">
                                <small class="text-muted">
                                    <strong>Missing:</strong>
                                    PO: <span id="productOverviewMissingCount" class="text-danger fw-bold">0</span> | 
                                    Shop: <span id="unboxingMissingCount" class="text-danger fw-bold">0</span> | 
                                    HowTo: <span id="howToMissingCount" class="text-danger fw-bold">0</span> | 
                                    Setup: <span id="setupMissingCount" class="text-danger fw-bold">0</span> | 
                                    TS: <span id="troubleshootingMissingCount" class="text-danger fw-bold">0</span> | 
                                    BS: <span id="brandStoryMissingCount" class="text-danger fw-bold">0</span> | 
                                    PB: <span id="productBenefitsMissingCount" class="text-danger fw-bold">0</span>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Table -->
                        <div id="videos-master-table" style="flex: 1;"></div>
                    </div>
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
                            <select class="form-select form-select-sm mt-1" id="video_product_overview_status" name="video_product_overview_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_unboxing" class="form-label">Shoppable Videos</label>
                            <input type="url" class="form-control" id="video_unboxing" name="video_unboxing" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_unboxing_status" name="video_unboxing_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_how_to" class="form-label">How To</label>
                            <input type="url" class="form-control" id="video_how_to" name="video_how_to" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_how_to_status" name="video_how_to_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_setup" class="form-label">Setup</label>
                            <input type="url" class="form-control" id="video_setup" name="video_setup" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_setup_status" name="video_setup_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_troubleshooting" class="form-label">Troubleshooting</label>
                            <input type="url" class="form-control" id="video_troubleshooting" name="video_troubleshooting" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_troubleshooting_status" name="video_troubleshooting_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_brand_story" class="form-label">Brand Story</label>
                            <input type="url" class="form-control" id="video_brand_story" name="video_brand_story" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_brand_story_status" name="video_brand_story_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="video_product_benefits" class="form-label">Product Benefits</label>
                            <input type="url" class="form-control" id="video_product_benefits" name="video_product_benefits" placeholder="https://">
                            <select class="form-select form-select-sm mt-1" id="video_product_benefits_status" name="video_product_benefits_status">
                                <option value="">-- Status --</option>
                                <option value="N/R">N/R</option>
                                <option value="Done/Uploaded">Done/Uploaded</option>
                                <option value="Assigned">Assigned</option>
                            </select>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        @verbatim
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let tableData = [];
        let videoModal;
        let table; // Tabulator instance
        let editButtonsSetup = false;

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast(`SKU "${text}" copied to clipboard!`, 'success');
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy SKU');
            });
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            // Simple alert for now - can be enhanced with Bootstrap toast
            console.log(`[${type.toUpperCase()}] ${message}`);
        }

        document.addEventListener('DOMContentLoaded', function() {
            videoModal = new bootstrap.Modal(document.getElementById('videoModal'));
            initializeTabulator();
            setupSearchHandlers();
            setupButtonHandlers();
            setupEditButtons();
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

        function setupVideoStatusDropdownHandlers() {
            if (videoStatusHandlersSetup) return;
            videoStatusHandlersSetup = true;
            
            document.getElementById('videos-master-table').addEventListener('change', function(e) {
                if (!e.target.classList.contains('video-status-select')) return;
                const sku = e.target.getAttribute('data-sku');
                const field = e.target.getAttribute('data-field');
                const statusVal = e.target.value;
                const item = tableData.find(d => d.SKU === sku);
                if (!item) return;
                item[field + '_status'] = statusVal;
                const videoData = {
                    sku: sku,
                    video_product_overview: item.video_product_overview || '',
                    video_product_overview_status: item.video_product_overview_status || '',
                    video_unboxing: item.video_unboxing || '',
                    video_unboxing_status: item.video_unboxing_status || '',
                    video_how_to: item.video_how_to || '',
                    video_how_to_status: item.video_how_to_status || '',
                    video_setup: item.video_setup || '',
                    video_setup_status: item.video_setup_status || '',
                    video_troubleshooting: item.video_troubleshooting || '',
                    video_troubleshooting_status: item.video_troubleshooting_status || '',
                    video_brand_story: item.video_brand_story || '',
                    video_brand_story_status: item.video_brand_story_status || '',
                    video_product_benefits: item.video_product_benefits || '',
                    video_product_benefits_status: item.video_product_benefits_status || ''
                };
                fetch('/videos-master/save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(videoData)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message || 'Failed to save status');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error saving status: ' + err.message);
                });
            });
        }

        function initializeTabulator() {
            document.getElementById('rainbow-loader').style.display = 'block';

            // Helper function for video column formatter
            function videoColumnFormatter(cell, fieldName) {
                const item = cell.getData();
                
                if (item[fieldName]) {
                    return `<a href="${escapeHtml(item[fieldName])}" target="_blank" class="video-link-icon" title="Click to watch video">
                        <i class="fas fa-play-circle"></i>
                    </a>`;
                } else {
                    return '<span class="text-muted" style="font-size: 18px;">—</span>';
                }
            }

            table = new Tabulator("#videos-master-table", {
                ajaxURL: "/videos-master-data-view",
                ajaxSorting: false,
                ajaxResponse: function(url, params, response) {
                    console.log("AJAX Response received:", response);
                    console.log("Response type:", typeof response);
                    
                    if (response && response.data && Array.isArray(response.data)) {
                        console.log("Data array length:", response.data.length);
                        tableData = response.data;
                        
                        // Debug: Check specific SKU
                        const testSku = tableData.find(item => item.SKU === 'FR 15190 17 AL');
                        if (testSku) {
                            console.log("Found SKU 'FR 15190 17 AL':", testSku);
                            console.log("Video fields for this SKU:", {
                                video_product_overview: testSku.video_product_overview,
                                video_unboxing: testSku.video_unboxing,
                                video_how_to: testSku.video_how_to,
                                video_setup: testSku.video_setup,
                                video_troubleshooting: testSku.video_troubleshooting,
                                video_brand_story: testSku.video_brand_story,
                                video_product_benefits: testSku.video_product_benefits
                            });
                        } else {
                            console.log("SKU 'FR 15190 17 AL' not found in data");
                        }
                        
                        updateCounts();
                        const loader = document.getElementById('rainbow-loader');
                        if (loader) loader.style.display = 'none';
                        return response.data;
                    }
                    
                    console.error("Invalid response format:", response);
                    const loader = document.getElementById('rainbow-loader');
                    if (loader) loader.style.display = 'none';
                    return [];
                },
                ajaxError: function(error) {
                    console.error('AJAX Error:', error);
                    alert('Failed to load product data. Check console for details.');
                    const loader = document.getElementById('rainbow-loader');
                    if (loader) loader.style.display = 'none';
                },
                layout: "fitData",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: "rows",
                dataLoaded: function(data) {
                    console.log("Data loaded successfully:", data.length, "rows");
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.SKU && data.SKU.toUpperCase().includes('PARENT')) {
                        row.getElement().classList.add('parent-row');
                    }
                },
                langs: {
                    "default": {
                        "pagination": {
                            "page_size": "Show",
                            "counter": {
                                "showing": "Showing",
                                "of": "of",
                                "rows": "rows"
                            }
                        }
                    }
                },
                columns: [
                    {
                        title: "Image",
                        field: "image_path",
                        width: 80,
                        frozen: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '-';
                            return `<img src="${value}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`;
                        }
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        width: 150,
                        frozen: true
                    },
                    {
                        title: "SKU",
                        field: "SKU",
                        width: 200,
                        frozen: true,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            if (!sku) return '-';
                            return `
                                <div style="display: flex; align-items: center; gap: 5px;">
                                    <span>${sku}</span>
                                    <button class="btn btn-sm btn-link p-0 copy-sku-btn" onclick="copyToClipboard('${sku}')" title="Copy SKU">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "INV",
                        field: "shopify_inv",
                        width: 80,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 0 || value === "0") return "0";
                            if (value === null || value === undefined || value === "") return "-";
                            return String(value);
                        }
                    },
                    {
                        title: "Ovl30",
                        field: "ovl30",
                        width: 80,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return (value === null || value === undefined || value === '') ? '0' : String(value);
                        }
                    },
                    {
                        title: "Dil",
                        field: "dil",
                        width: 50,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            let dilText = '0%';
                            let dilColor = '#a00211';
                            
                            if (value !== null && value !== undefined && value !== '') {
                                const dilNum = parseFloat(value);
                                dilText = Math.round(dilNum) + '%';
                                
                                if (dilNum < 16.7) dilColor = '#a00211';
                                else if (dilNum >= 16.7 && dilNum < 25) dilColor = '#ffc107';
                                else if (dilNum >= 25 && dilNum < 50) dilColor = '#28a745';
                                else if (dilNum >= 50) dilColor = '#e83e8c';
                            }
                            
                            return `<span style="color: ${dilColor}; font-weight: bold;">${dilText}</span>`;
                        }
                    },
                    {
                        title: "LQS",
                        field: "lqs",
                        width: 80,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return '-';
                            const score = parseInt(value);
                            let color = '#dc3545';
                            if (score >= 8) color = '#28a745';
                            else if (score >= 6) color = '#ffc107';
                            return `<span class="badge" style="background-color: ${color}; color: ${score >= 6 && score < 8 ? 'black' : 'white'};">${score}</span>`;
                        }
                    },
                    {
                        title: "B/S",
                        field: "buyer_seller",
                        width: 45,
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            let html = '<div style="display: flex; justify-content: center; gap: 5px;">';
                            if (row.buyer_link) {
                                html += `<a href="${row.buyer_link}" target="_blank" class="text-decoration-none fw-semibold" style="color: #2c6ed5;" title="Buyer Link">B</a>`;
                            }
                            if (row.seller_link) {
                                html += `<a href="${row.seller_link}" target="_blank" class="text-decoration-none fw-semibold" style="color: #47ad77;" title="Seller Link">S</a>`;
                            }
                            html += '</div>';
                            return (row.buyer_link || row.seller_link) ? html : '-';
                        }
                    },
                    {
                        title: "PO",
                        field: "video_product_overview",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_product_overview');
                        }
                    },
                    {
                        title: "Shop",
                        field: "video_unboxing",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_unboxing');
                        }
                    },
                    {
                        title: "HowTo",
                        field: "video_how_to",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_how_to');
                        }
                    },
                    {
                        title: "Setup",
                        field: "video_setup",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_setup');
                        }
                    },
                    {
                        title: "TS",
                        field: "video_troubleshooting",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_troubleshooting');
                        }
                    },
                    {
                        title: "BS",
                        field: "video_brand_story",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_brand_story');
                        }
                    },
                    {
                        title: "PB",
                        field: "video_product_benefits",
                        width: 70,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return videoColumnFormatter(cell, 'video_product_benefits');
                        }
                    },
                    {
                        title: "Action",
                        field: "action",
                        width: 100,
                        hozAlign: "center",
                        frozen: true,
                        frozenDirection: "right",
                        formatter: function(cell) {
                            const sku = cell.getData().SKU;
                            return `<button class="action-btn edit-btn" data-sku="${escapeHtml(sku)}"><i class="fas fa-edit"></i> Edit</button>`;
                        }
                    }
                ]
            });
        }


        function setupEditButtons() {
            if (editButtonsSetup) return;
            editButtonsSetup = true;
            
            // Use event delegation for dynamic content
            document.getElementById('videos-master-table').addEventListener('click', function(e) {
                const editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    const sku = editBtn.getAttribute('data-sku');
                    openModal('edit', sku);
                }
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
                        if (input) input.value = item[field] || '';
                        const statusInput = document.getElementById(field + '_status');
                        if (statusInput) statusInput.value = item[field + '_status'] || '';
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
                video_product_overview_status: document.getElementById('video_product_overview_status').value,
                video_unboxing: document.getElementById('video_unboxing').value,
                video_unboxing_status: document.getElementById('video_unboxing_status').value,
                video_how_to: document.getElementById('video_how_to').value,
                video_how_to_status: document.getElementById('video_how_to_status').value,
                video_setup: document.getElementById('video_setup').value,
                video_setup_status: document.getElementById('video_setup_status').value,
                video_troubleshooting: document.getElementById('video_troubleshooting').value,
                video_troubleshooting_status: document.getElementById('video_troubleshooting_status').value,
                video_brand_story: document.getElementById('video_brand_story').value,
                video_brand_story_status: document.getElementById('video_brand_story_status').value,
                video_product_benefits: document.getElementById('video_product_benefits').value,
                video_product_benefits_status: document.getElementById('video_product_benefits_status').value
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
                    table.replaceData();
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
                    'Shopify Inv': item.shopify_inv !== null && item.shopify_inv !== undefined && item.shopify_inv !== '' ? Number(item.shopify_inv) : '',
                    'Product Overview': item.video_product_overview || '',
                    'Product Overview Status': item.video_product_overview_status || '',
                    'Shoppable Videos': item.video_unboxing || '',
                    'Shoppable Videos Status': item.video_unboxing_status || '',
                    'How To': item.video_how_to || '',
                    'How To Status': item.video_how_to_status || '',
                    'Setup': item.video_setup || '',
                    'Setup Status': item.video_setup_status || '',
                    'Troubleshooting': item.video_troubleshooting || '',
                    'Troubleshooting Status': item.video_troubleshooting_status || '',
                    'Brand Story': item.video_brand_story || '',
                    'Brand Story Status': item.video_brand_story_status || '',
                    'Product Benefits': item.video_product_benefits || '',
                    'Product Benefits Status': item.video_product_benefits_status || ''
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
                    video_product_overview_status: row['Product Overview Status'] || '',
                    video_unboxing: row['Shoppable Videos'] || row['Unboxing'] || '',
                    video_unboxing_status: row['Shoppable Videos Status'] || row['Unboxing Status'] || '',
                    video_how_to: row['How To'] || '',
                    video_how_to_status: row['How To Status'] || '',
                    video_setup: row['Setup'] || '',
                    video_setup_status: row['Setup Status'] || '',
                    video_troubleshooting: row['Troubleshooting'] || '',
                    video_troubleshooting_status: row['Troubleshooting Status'] || '',
                    video_brand_story: row['Brand Story'] || '',
                    video_brand_story_status: row['Brand Story Status'] || '',
                    video_product_benefits: row['Product Benefits'] || '',
                    video_product_benefits_status: row['Product Benefits Status'] || ''
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
                    table.replaceData();
                }
            });
        }

        // Apply all filters
        function applyFilters() {
            const generalFilter = document.getElementById('general-search').value.toLowerCase();
            const parentFilter = document.getElementById('parentSearch').value.toLowerCase();
            const skuFilter = document.getElementById('skuSearch').value.toLowerCase();
            const filterVideos = document.getElementById('filterVideos').value;

            const filters = [];

            // General search across all columns
            if (generalFilter) {
                filters.push(function(data) {
                    const searchStr = generalFilter.toLowerCase();
                    return (data.Parent && data.Parent.toLowerCase().includes(searchStr)) ||
                           (data.SKU && data.SKU.toLowerCase().includes(searchStr)) ||
                           (data.shopify_inv && String(data.shopify_inv).toLowerCase().includes(searchStr)) ||
                           (data.video_product_overview && data.video_product_overview.toLowerCase().includes(searchStr)) ||
                           (data.video_unboxing && data.video_unboxing.toLowerCase().includes(searchStr)) ||
                           (data.video_how_to && data.video_how_to.toLowerCase().includes(searchStr)) ||
                           (data.video_setup && data.video_setup.toLowerCase().includes(searchStr)) ||
                           (data.video_troubleshooting && data.video_troubleshooting.toLowerCase().includes(searchStr)) ||
                           (data.video_brand_story && data.video_brand_story.toLowerCase().includes(searchStr)) ||
                           (data.video_product_benefits && data.video_product_benefits.toLowerCase().includes(searchStr));
                });
            }

            // Parent search filter
            if (parentFilter) {
                filters.push({field: "Parent", type: "like", value: parentFilter});
            }

            // SKU search filter
            if (skuFilter) {
                filters.push({field: "SKU", type: "like", value: skuFilter});
            }

            // Missing videos filter
            if (filterVideos === 'missing') {
                filters.push(function(data) {
                    return isMissing(data.video_product_overview) || 
                           isMissing(data.video_unboxing) || 
                           isMissing(data.video_how_to) || 
                           isMissing(data.video_setup) || 
                           isMissing(data.video_troubleshooting) || 
                           isMissing(data.video_brand_story) || 
                           isMissing(data.video_product_benefits);
                });
            }

            table.clearFilter();
            if (filters.length > 0) {
                table.setFilter(filters);
            }
        }

        function setupSearchHandlers() {
            const generalSearch = document.getElementById('general-search');
            const parentSearch = document.getElementById('parentSearch');
            const skuSearch = document.getElementById('skuSearch');
            const filterVideos = document.getElementById('filterVideos');

            generalSearch.addEventListener('input', applyFilters);
            parentSearch.addEventListener('input', applyFilters);
            skuSearch.addEventListener('input', applyFilters);
            filterVideos.addEventListener('change', applyFilters);
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

            // Update placeholders
            document.getElementById('parentSearch').placeholder = `Search Parent... (${parentSet.size})`;
            document.getElementById('skuSearch').placeholder = `Search SKU... (${skuCount})`;
            
            // Update missing counts
            document.getElementById('productOverviewMissingCount').textContent = productOverviewMissingCount;
            document.getElementById('unboxingMissingCount').textContent = unboxingMissingCount;
            document.getElementById('howToMissingCount').textContent = howToMissingCount;
            document.getElementById('setupMissingCount').textContent = setupMissingCount;
            document.getElementById('troubleshootingMissingCount').textContent = troubleshootingMissingCount;
            document.getElementById('brandStoryMissingCount').textContent = brandStoryMissingCount;
            document.getElementById('productBenefitsMissingCount').textContent = productBenefitsMissingCount;
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
