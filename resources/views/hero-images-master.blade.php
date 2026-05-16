@extends('layouts.vertical', ['title' => 'Hero Image Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
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

        .audit-dot {
            width: 10px;
            height: 10px;
            background-color: #ffc107;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
        }

        .edit-dot {
            width: 10px;
            height: 10px;
            background-color: #2c6ed5;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            border: none;
            padding: 0;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .edit-dot:hover {
            transform: scale(1.3);
            box-shadow: 0 0 4px rgba(44, 110, 213, 0.6);
        }

        .upload-dot {
            width: 10px;
            height: 10px;
            background-color: #28a745;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            border: none;
            padding: 0;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .upload-dot:hover {
            transform: scale(1.3);
            box-shadow: 0 0 4px rgba(40, 167, 69, 0.6);
        }

        .ai-score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 24px;
            padding: 0 6px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
            cursor: pointer;
            border: none;
            line-height: 1;
        }
        .ai-score-badge.s-pass { background-color: #28a745; }
        .ai-score-badge.s-warn { background-color: #ffc107; color: #212529; }
        .ai-score-badge.s-fail { background-color: #dc3545; }
        .ai-score-badge:hover { filter: brightness(1.08); }

        /* Subtle ring accent so users can spot "Ads CTR" (paid, blue) and
           "Org CTR" (organic, purple) at a glance even though the fill color
           is shared with the AI badge. */
        .ai-score-badge.real-ctr-ads { box-shadow: inset 0 0 0 2px #0d6efd; }
        .ai-score-badge.real-ctr-org { box-shadow: inset 0 0 0 2px #6f42c1; }

        #heroAnalysisBody .check-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            background: #f9fafb;
            height: 100%;
        }
        #heroAnalysisBody .check-card .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
            font-weight: 600;
        }
        #heroAnalysisBody .check-card .value {
            font-size: 13px;
            color: #111827;
            margin-top: 2px;
        }
        #heroAnalysisBody .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        #heroAnalysisBody .pill-pass { background: #d1fae5; color: #065f46; }
        #heroAnalysisBody .pill-fail { background: #fee2e2; color: #991b1b; }
        #heroAnalysisBody .score-tile {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
            background: #fff;
        }
        #heroAnalysisBody .score-tile .num {
            font-size: 22px;
            font-weight: 700;
        }
        #heroAnalysisBody .score-tile .lbl {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .status-toggle-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .status-toggle-btn.red {
            background-color: #dc3545;
        }

        .status-toggle-btn.green {
            background-color: #28a745;
        }

        .status-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .status-toggle-btn:active {
            transform: scale(0.95);
        }

        .status-toggle-btn.loading {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .status-toggle-btn.loading::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Hero Images Masters',
        'sub_title' => 'Hero Images Master Data',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Hero Images Master</h4>
                
                <!-- Control Bar -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- LQS Average Badge -->
                    <span class="badge bg-primary p-2" style="font-weight: bold; font-size: 14px;">
                        LQS AVG: <span id="lqsAvg">-</span>
                    </span>

                    <!-- LQS Play Controls -->
                    <div class="btn-group align-items-center" role="group" style="gap: 4px;">
                        <button type="button" id="lqs-play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Previous (Lower LQS)" disabled>
                            <i class="fas fa-step-backward" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-auto" class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Play LQS (Lowest to Highest)">
                            <i class="fas fa-play" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-pause" class="btn btn-sm btn-danger rounded-circle shadow-sm" style="display: none; width: 32px; height: 32px; padding: 0;" title="Pause">
                            <i class="fas fa-pause" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Next (Higher LQS)" disabled>
                            <i class="fas fa-step-forward" style="font-size: 12px;"></i>
                        </button>
                    </div>

                    <span id="lqs-play-status" class="badge bg-info" style="display: none; font-size: 12px;">
                        Playing: <span id="current-lqs-value">-</span>
                    </span>

                    <!-- Column Visibility -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <!-- Action Buttons -->
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export
                    </button>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div id="aplus-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Search Bar -->
                    <div class="p-2 bg-light border-bottom">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" id="general-search" class="form-control form-control-sm" placeholder="Search all columns...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="lqs-search" class="form-control form-control-sm" placeholder="Search LQS...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table -->
                    <div id="hero-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Competitors (LMP) Modal -->
    <div class="modal fade" id="competitorsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-search me-2"></i>Competitors for SKU: <span id="competitorsSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fas fa-plus-circle me-2"></i>Add New Competitor</strong>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm" class="row g-3">
                                <input type="hidden" id="compSku">
                                <div class="col-md-3">
                                    <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="compAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="compPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="compLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><strong>Marketplace</strong></label>
                                    <select class="form-select" id="compMarketplace">
                                        <option value="Amazon" selected>Amazon</option>
                                        <option value="US">US</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Add Competitor
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-2"></i>Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Competitors List -->
                    <div id="competitorsList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DB Link Modal -->
    <div class="modal fade" id="dbLinkModal" tabindex="-1" aria-labelledby="dbLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="dbLinkModalLabel">
                        <i class="fas fa-link me-2"></i><span id="dbModalTitle">Add DB Link</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="dbLinkForm">
                        <input type="hidden" id="dbLinkSku" name="sku">
                        <div class="mb-3">
                            <label for="dbLinkInput" class="form-label fw-bold">
                                <i class="fas fa-database text-primary me-1"></i>DB Link
                            </label>
                            <input type="url" class="form-control" id="dbLinkInput" name="db_link" placeholder="https://example.com/db-link" required>
                            <div class="form-text">Enter the full URL for the DB link (shared with A+ Images Master)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDBLinkBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero AI Analysis Modal -->
    <div class="modal fade" id="heroAnalysisModal" tabindex="-1" aria-labelledby="heroAnalysisModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="heroAnalysisModalLabel">
                        <i class="fas fa-robot me-2"></i>AI Hero Image Analysis — <span id="heroAnalysisSkuLabel"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="heroAnalysisBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        <p class="mt-2 text-muted">Loading analysis…</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto" id="heroAnalysisMeta"></small>
                    <button type="button" class="btn btn-outline-primary" id="heroAnalysisRerunBtn">
                        <i class="fas fa-sync-alt me-1"></i>Re-run analysis
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hero Image Upload Modal -->
    <div class="modal fade" id="heroImageUploadModal" tabindex="-1" aria-labelledby="heroImageUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="heroImageUploadModalLabel">
                        <i class="fas fa-upload me-2"></i>Upload Hero Image
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="heroImageUploadForm">
                        <input type="hidden" id="heroImageUploadSku" name="sku">
                        <div class="mb-3">
                            <label for="heroImageFileInput" class="form-label fw-bold">
                                <i class="fas fa-image text-primary me-1"></i>Select Hero Image
                            </label>
                            <input type="file" class="form-control" id="heroImageFileInput" name="image_file" accept="image/*" required>
                            <div class="form-text">Accepted formats: JPG, PNG, GIF, BMP, WEBP, SVG (max 10MB)</div>
                        </div>
                        <div id="heroImagePreviewContainer" style="display: none;">
                            <label class="form-label fw-bold">Preview:</label>
                            <div class="text-center">
                                <img id="heroImagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveHeroImageBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "hero_tabulator_column_visibility";
    let table = null;
    let tableData = [];
    let lqsPlayInterval = null;
    let currentLqsIndex = 0;
    let sortedLqsData = [];

    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // Copy to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(`SKU "${text}" copied to clipboard!`, 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
            showToast('Failed to copy SKU', 'error');
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        // Initialize Tabulator
        console.log("Initializing Tabulator...");
        table = new Tabulator("#hero-table", {
            ajaxURL: "/hero-images-master-data-view",
            ajaxSorting: false,
            layout: "fitData",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                console.log("Response type:", typeof response);
                
                if (response && response.data && Array.isArray(response.data)) {
                    console.log("Data array length:", response.data.length);
                    tableData = response.data;
                    return response.data;
                }
                
                console.error("Invalid response format:", response);
                return [];
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                console.log("Data loaded successfully:", data.length, "rows");
                updateSummary();
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
            initialSort: [{
                column: "lqs",
                dir: "asc"
            }],
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
                    title: "Comp",
                    field: "comp",
                    width: 45,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().SKU;
                        return `<button class="btn btn-sm btn-info" onclick="viewCompetitors('${sku}')" title="View Competitors"><i class="fas fa-search"></i></button>`;
                    }
                },
                {
                    title: "Hero",
                    field: "hero_image",
                    width: 100,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const heroImage = cell.getValue();
                        const sku = cell.getRow().getData().SKU || '';
                        const escapedSku = String(sku).replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        if (heroImage && String(heroImage).trim()) {
                            const src = String(heroImage).startsWith('http')
                                ? heroImage
                                : '/storage/' + String(heroImage).replace(/^\/+/, '');
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <img src="${src}"
                                         style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;"
                                         onclick="window.open('${src}', '_blank')"
                                         title="Click to view full size">
                                    <span class="edit-dot" onclick="openHeroImageUploadModal('${escapedSku}')" title="Replace Hero Image"></span>
                                </div>
                            `;
                        }
                        return `<span class="upload-dot" onclick="openHeroImageUploadModal('${escapedSku}')" title="Upload Hero Image"></span>`;
                    }
                },
                {
                    title: "DB",
                    field: "db",
                    width: 80,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue() || cell.getRow().getData()['DB'] || '';
                        const sku = cell.getRow().getData().SKU || '';
                        const escapedSku = String(sku).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const cleanUrl = value ? String(value).trim() : '';

                        if (cleanUrl && cleanUrl.match(/^https?:\/\//i)) {
                            return `
                                <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <a href="${cleanUrl}" target="_blank" class="text-decoration-none" style="color: #2c6ed5;" title="Open DB Link">
                                        <i class="fas fa-link"></i>
                                    </a>
                                    <button class="btn btn-sm btn-link p-0" onclick="openDBModal('${escapedSku}', '${cleanUrl.replace(/'/g, "\\'")}')" title="Edit DB Link" style="color: #6c757d;">
                                        <i class="fas fa-edit" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            `;
                        }
                        return `<button class="btn btn-sm btn-link p-0" onclick="openDBModal('${escapedSku}', '')" title="Add DB Link" style="color: #28a745;">
                            <i class="fas fa-plus"></i>
                        </button>`;
                    }
                },
                {
                    title: "AI CTR",
                    field: "hero_ctr",
                    width: 75,
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const sa = extractCtrScore(aRow.getData());
                        const sb = extractCtrScore(bRow.getData());
                        return (sa === null ? -1 : sa) - (sb === null ? -1 : sb);
                    },
                    formatter: function(cell) {
                        const data = cell.getRow().getData();
                        const sku = data.SKU || '';
                        const escapedSku = String(sku).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const score = extractCtrScore(data);

                        if (score === null) {
                            if (!data.hero_image) {
                                return `<span class="text-muted" title="Upload a hero image first" style="font-size: 11px;">—</span>`;
                            }
                            if (!data.hero_analysis) {
                                return `<span class="text-muted" title="Run AI analysis to get a CTR score" style="font-size: 11px;">—</span>`;
                            }
                            return `<span class="text-muted" style="font-size: 11px;">—</span>`;
                        }

                        let cls = 's-warn';
                        if (score >= 8) cls = 's-pass';
                        else if (score < 6) cls = 's-fail';

                        return `<button class="ai-score-badge ${cls}" onclick="viewHeroAnalysis('${escapedSku}')" title="AI-predicted CTR potential — click for full analysis">${score}/10</button>`;
                    }
                },
                {
                    title: "Ads CTR",
                    field: "ads_ctr",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        return renderRealCtrBadge(cell.getRow().getData(), 'ads');
                    }
                },
                {
                    title: "Org CTR",
                    field: "organic_ctr",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        return renderRealCtrBadge(cell.getRow().getData(), 'organic');
                    }
                },
                {
                    title: "AI",
                    field: "hero_analysis",
                    width: 90,
                    hozAlign: "center",
                    sorter: function(a, b) {
                        const sa = (a && a.overall_score) ? parseFloat(a.overall_score) : -1;
                        const sb = (b && b.overall_score) ? parseFloat(b.overall_score) : -1;
                        return sa - sb;
                    },
                    formatter: function(cell) {
                        const data = cell.getRow().getData();
                        const sku = data.SKU || '';
                        const escapedSku = String(sku).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const heroImage = data.hero_image;
                        const analysis = cell.getValue();

                        if (!heroImage) {
                            return `<span class="text-muted" title="Upload a hero image first" style="font-size: 11px;">—</span>`;
                        }

                        if (analysis && typeof analysis === 'object') {
                            const score = (analysis.overall_score !== undefined && analysis.overall_score !== null)
                                ? Number(analysis.overall_score) : null;
                            const passFail = String(analysis.pass_fail || '').toUpperCase();
                            let cls = 's-warn';
                            if (passFail === 'PASS' || (score !== null && score >= 8)) cls = 's-pass';
                            else if (passFail === 'FAIL' || (score !== null && score < 6)) cls = 's-fail';
                            const label = score !== null ? `${score}/10` : (passFail || 'AI');
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <button class="ai-score-badge ${cls}" onclick="viewHeroAnalysis('${escapedSku}')" title="View AI analysis">${label}</button>
                                    <button class="btn btn-sm btn-link p-0" onclick="analyzeHero('${escapedSku}', true)" title="Re-run analysis" style="color: #6c757d;">
                                        <i class="fas fa-sync-alt" style="font-size: 11px;"></i>
                                    </button>
                                </div>
                            `;
                        }

                        return `<button class="btn btn-sm btn-outline-primary" onclick="analyzeHero('${escapedSku}', false)" title="Analyze with AI" style="font-size: 11px; padding: 2px 8px;">
                            <i class="fas fa-robot"></i> AI
                        </button>`;
                    }
                },
                {
                    title: "Push",
                    field: "hero_pushed_at",
                    width: 90,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const data = cell.getRow().getData();
                        const sku = data.SKU || '';
                        const escapedSku = String(sku).replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        if (!data.hero_image) {
                            return `<span class="text-muted" title="Upload a hero image first" style="font-size: 11px;">—</span>`;
                        }

                        const aiPass = (data.hero_analysis && String(data.hero_analysis.pass_fail || '').toUpperCase() === 'PASS');
                        const statusGreen = (data.status_toggle === 'green');
                        const approved = aiPass || statusGreen;

                        const pushedAt = data.hero_pushed_at;
                        const pushStatus = data.hero_push_status;
                        const site = data.hero_pushed_to || 'amazon';

                        // Already successfully pushed → show success state + re-push action
                        if (pushedAt && pushStatus === 'success') {
                            const when = new Date(pushedAt).toLocaleString();
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <button class="btn btn-sm btn-success" style="font-size: 11px; padding: 2px 8px;"
                                            onclick="pushHero('${escapedSku}', '${site}', false)"
                                            title="Last pushed to ${escapeHtml(site)} on ${escapeHtml(when)} — click to push again">
                                        <i class="fas fa-check"></i> Pushed
                                    </button>
                                </div>
                            `;
                        }

                        // Last attempt failed → red retry button
                        if (pushStatus === 'failed') {
                            return `<button class="btn btn-sm btn-danger" style="font-size: 11px; padding: 2px 8px;"
                                            onclick="pushHero('${escapedSku}', 'amazon', false)"
                                            title="Last push failed — click to retry">
                                        <i class="fas fa-exclamation-triangle"></i> Retry
                                    </button>`;
                        }

                        // Never pushed: button color reflects approval state
                        const cls = approved ? 'btn-primary' : 'btn-outline-secondary';
                        const titleAttr = approved
                            ? 'Push hero image to Amazon as the main product image'
                            : 'Not approved (no PASS / status not green) — click to push anyway';
                        return `<button class="btn btn-sm ${cls}" style="font-size: 11px; padding: 2px 8px;"
                                        onclick="pushHero('${escapedSku}', 'amazon', ${approved ? 'false' : 'true'})"
                                        title="${titleAttr}">
                                    <i class="fab fa-amazon"></i> Push
                                </button>`;
                    }
                }
            ]
        });

        // ---- Search ----
        // Single combined filter:
        //   - General box: OR across many fields (any field contains the text)
        //   - SKU box:     AND, must contain in SKU
        //   - LQS box:     AND, LQS as string contains the text
        // Typing in any box re-evaluates all three.
        function applyHeroSearch() {
            const general = ($('#general-search').val() || '').trim().toLowerCase();
            const skuTerm = ($('#sku-search').val() || '').trim().toLowerCase();
            const lqsTerm = ($('#lqs-search').val() || '').trim().toLowerCase();

            // Nothing entered? Clear all filters.
            if (!general && !skuTerm && !lqsTerm) {
                table.clearFilter(true);
                return;
            }

            const generalFields = [
                'SKU', 'Parent', 'status', 'db', 'DB', 'hero_image',
                'buyer_link', 'seller_link', 'shopify_inv', 'lqs'
            ];

            table.setFilter(function(rowData) {
                if (skuTerm) {
                    const sku = String(rowData.SKU || '').toLowerCase();
                    if (sku.indexOf(skuTerm) === -1) return false;
                }
                if (lqsTerm) {
                    const lqs = String(rowData.lqs || '').toLowerCase();
                    if (lqs.indexOf(lqsTerm) === -1) return false;
                }
                if (general) {
                    let hit = false;
                    for (let i = 0; i < generalFields.length; i++) {
                        const v = rowData[generalFields[i]];
                        if (v == null) continue;
                        if (String(v).toLowerCase().indexOf(general) !== -1) {
                            hit = true;
                            break;
                        }
                    }
                    // Also peek inside hero_analysis (verdict + pass/fail) so users
                    // can search "fail" or part of the AI verdict text.
                    if (!hit && rowData.hero_analysis && typeof rowData.hero_analysis === 'object') {
                        const verdict = String(rowData.hero_analysis.final_verdict || '').toLowerCase();
                        const pf = String(rowData.hero_analysis.pass_fail || '').toLowerCase();
                        if (verdict.indexOf(general) !== -1 || pf.indexOf(general) !== -1) {
                            hit = true;
                        }
                    }
                    if (!hit) return false;
                }
                return true;
            });
        }

        $('#general-search, #sku-search, #lqs-search').on('input', applyHeroSearch);

        // Update summary
        function updateSummary() {
            const data = table.getData("active");
            
            // Calculate LQS average
            const lqsValues = data.filter(item => item.lqs).map(item => parseFloat(item.lqs));
            const lqsAvg = lqsValues.length > 0 ? (lqsValues.reduce((a, b) => a + b, 0) / lqsValues.length).toFixed(1) : '-';

            $('#lqsAvg').text(lqsAvg);
        }

        // Column visibility
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');
            
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (!def.field) return;

                const li = document.createElement("li");
                const label = document.createElement("label");
                label.style.display = "block";
                label.style.padding = "5px 10px";
                label.style.cursor = "pointer";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.value = def.field;
                checkbox.checked = savedVisibility[def.field] !== false;
                checkbox.style.marginRight = "8px";

                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(def.title));
                li.appendChild(label);
                menu.appendChild(li);
            });
        }

        function saveColumnVisibility() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(visibility));
        }

        function applyColumnVisibility() {
            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field && savedVisibility[def.field] === false) {
                    col.hide();
                }
            });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibility();
            buildColumnDropdown();
        });

        table.on('dataLoaded', updateSummary);
        table.on('dataProcessed', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                saveColumnVisibility();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibility();
        });

        // Export
        $('#export-btn').on('click', function() {
            table.download("xlsx", "aplus_master_data.xlsx", {sheetName: "A+ Masters"});
        });

        // LQS Play Controls
        $('#lqs-play-auto').on('click', function() {
            sortedLqsData = tableData.filter(item => item.lqs).sort((a, b) => parseFloat(a.lqs) - parseFloat(b.lqs));
            if (sortedLqsData.length === 0) {
                showToast('No LQS data available', 'error');
                return;
            }
            
            currentLqsIndex = 0;
            startLqsPlay();
        });

        $('#lqs-play-pause').on('click', stopLqsPlay);
        
        $('#lqs-play-forward').on('click', function() {
            if (sortedLqsData.length === 0) return;
            currentLqsIndex = Math.min(currentLqsIndex + 1, sortedLqsData.length - 1);
            highlightLqsRow();
        });
        
        $('#lqs-play-backward').on('click', function() {
            if (sortedLqsData.length === 0) return;
            currentLqsIndex = Math.max(currentLqsIndex - 1, 0);
            highlightLqsRow();
        });

        function startLqsPlay() {
            $('#lqs-play-auto').hide();
            $('#lqs-play-pause').show();
            $('#lqs-play-status').show();
            $('#lqs-play-forward').prop('disabled', false);
            $('#lqs-play-backward').prop('disabled', false);
            
            lqsPlayInterval = setInterval(() => {
                if (currentLqsIndex >= sortedLqsData.length - 1) {
                    stopLqsPlay();
                    return;
                }
                currentLqsIndex++;
                highlightLqsRow();
            }, 3000);
            
            highlightLqsRow();
        }

        function stopLqsPlay() {
            if (lqsPlayInterval) {
                clearInterval(lqsPlayInterval);
                lqsPlayInterval = null;
            }
            $('#lqs-play-auto').show();
            $('#lqs-play-pause').hide();
            $('#lqs-play-status').hide();
        }

        function highlightLqsRow() {
            if (!sortedLqsData[currentLqsIndex]) return;
            const currentItem = sortedLqsData[currentLqsIndex];
            $('#current-lqs-value').text(currentItem.lqs);
            table.setFilter("SKU", "=", currentItem.SKU);
            table.scrollToRow(currentItem.SKU, "center", true);
        }

    });

    // View Competitors function
    async function viewCompetitors(sku) {
        const competitorsList = $('#competitorsList');
        
        // Set SKU in modal and form
        $('#competitorsSku').text(sku);
        $('#compSku').val(sku);
        
        // Show loading
        competitorsList.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading competitors...</p>
            </div>
        `);
        
        $('#competitorsModal').modal('show');
        
        try {
            console.log('Fetching competitors for SKU:', sku);
            const response = await fetch(`/amazon/competitors?sku=${encodeURIComponent(sku)}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });
            
            console.log('Response status:', response.status);
            const data = await response.json();
            console.log('Response data:', data);
            
            if (!response.ok) {
                throw new Error(data.message || data.error || 'Failed to load competitors');
            }
            
            if (data.success && data.competitors && data.competitors.length > 0) {
                renderCompetitorsList(data.competitors, data.lowest_price);
            } else {
                competitorsList.html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No competitors found yet. Add your first competitor above!
                    </div>
                `);
            }
        } catch (error) {
            console.error('Error loading competitors:', error);
            competitorsList.html(`
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>Error: ${error.message || 'Failed to load competitors'}. Please try again.
                </div>
            `);
        }
    }

    // Render Competitors List
    function renderCompetitorsList(competitors, lowestPrice) {
        function escapeHtml(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        if (!competitors || competitors.length === 0) {
            $('#competitorsList').html(`
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No competitors found for this SKU
                </div>
            `);
            return;
        }
        
        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
        html += `
            <thead class="table-light">
                <tr>
                    <th style="width: 30px;">#</th>
                    <th style="width: 60px;">Image</th>
                    <th style="width: 100px;">ASIN</th>
                    <th style="width: 250px;">Product Title</th>
                    <th>Seller</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 90px;">Revenue<br><small>(30d)</small></th>
                    <th style="width: 70px;">Units<br><small>(30d)</small></th>
                    <th style="width: 100px;">Buy Box</th>
                    <th style="width: 60px;">Type</th>
                    <th style="width: 70px;">Rating</th>
                    <th style="width: 70px;">Reviews</th>
                    <th style="width: 60px;">Link</th>
                    <th style="width: 80px;">Actions</th>
                </tr>
            </thead>
            <tbody>
        `;
        
        competitors.forEach((item, index) => {
            const isLowest = (parseFloat(item.price) === parseFloat(lowestPrice));
            const rowClass = isLowest ? 'table-success' : '';
            const priceFormatted = '$' + parseFloat(item.price).toFixed(2);
            const priceBadge = isLowest ? 
                `<span class="badge bg-success">${priceFormatted} <i class="fas fa-trophy"></i></span>` : 
                `<strong>${priceFormatted}</strong>`;
            
            const productLink = item.link || item.product_link || '#';
            const productTitle = item.title || item.product_title || 'N/A';
            const sellerName = item.seller_name || '—';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl ? `<img src="${imageUrl}" style="width: 50px; height: 50px; object-fit: contain;" />` : '<span style="color: #999;">—</span>';
            
            const revenue = item.monthly_revenue ? `<span style="color: #28a745; font-weight: 600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>` : '<span style="color: #999;">—</span>';
            const units = item.monthly_units_sold ? `<span style="color: #007bff; font-weight: 600;">${parseInt(item.monthly_units_sold)}</span>` : '<span style="color: #999;">—</span>';
            const buyBox = item.buy_box_owner ? `<span style="font-size: 11px;">${item.buy_box_owner}</span>` : '<span style="color: #999;">—</span>';
            const sellerType = item.seller_type ? `<span class="badge bg-${item.seller_type === 'FBA' ? 'warning' : 'secondary'}">${item.seller_type}</span>` : '<span style="color: #999;">—</span>';
            
            const rating = item.rating ? `<span style="color: #ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fas fa-star"></i></span>` : '<span style="color: #999;">—</span>';
            const reviews = item.reviews ? `<span style="font-weight: 600;">${parseInt(item.reviews).toLocaleString()}</span>` : '<span style="color: #999;">—</span>';
            
            html += `
                <tr class="${rowClass}">
                    <td style="text-align: center;">${index + 1}</td>
                    <td style="text-align: center;">${imageHtml}</td>
                    <td><strong>${escapeHtml(item.asin || 'N/A')}</strong></td>
                    <td style="font-size: 12px;">${escapeHtml(productTitle)}</td>
                    <td style="font-size: 11px;">${escapeHtml(sellerName)}</td>
                    <td style="text-align: right;">${priceBadge}</td>
                    <td style="text-align: right;">${revenue}</td>
                    <td style="text-align: center;">${units}</td>
                    <td>${buyBox}</td>
                    <td style="text-align: center;">${sellerType}</td>
                    <td style="text-align: center;">${rating}</td>
                    <td style="text-align: center;">${reviews}</td>
                    <td style="text-align: center;">
                        <a href="${escapeHtml(productLink)}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                    <td style="text-align: center;">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCompetitor('${escapeHtml(item.id || '')}', '${escapeHtml(item.sku || '')}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        $('#competitorsList').html(html);
    }

    // Add Competitor Form Submit
    $('#addCompetitorForm').on('submit', async function(e) {
        e.preventDefault();
        
        const sku = $('#compSku').val();
        const asin = $('#compAsin').val().trim();
        const price = parseFloat($('#compPrice').val());
        const link = $('#compLink').val().trim();
        const marketplace = $('#compMarketplace').val();
        
        if (!asin) {
            showToast('ASIN is required', 'error');
            return;
        }
        
        if (!price || price <= 0) {
            showToast('Valid price is required', 'error');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
        
        try {
            const response = await fetch('/amazon/lmp/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    sku: sku,
                    asin: asin,
                    price: price,
                    product_link: link || null,
                    marketplace: marketplace
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Failed to add competitor');
            }
            
            showToast('Competitor added successfully', 'success');
            
            // Reset form
            this.reset();
            $('#compSku').val(sku);
            
            // Reload competitors list
            viewCompetitors(sku);
            
        } catch (error) {
            console.error('Error adding competitor:', error);
            showToast(error.message || 'Failed to add competitor', 'error');
        } finally {
            submitBtn.prop('disabled', false).html(originalHtml);
        }
    });

    // Delete Competitor
    async function deleteCompetitor(competitorId, sku) {
        if (!confirm('Are you sure you want to delete this competitor?')) {
            return;
        }
        
        try {
            const response = await fetch('/amazon/lmp/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    id: competitorId
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Failed to delete competitor');
            }
            
            showToast('Competitor deleted successfully', 'success');
            
            // Reload competitors list
            viewCompetitors(sku);
            
        } catch (error) {
            console.error('Error deleting competitor:', error);
            showToast(error.message || 'Failed to delete competitor', 'error');
        }
    }

    // ---- DB Link (shared with A+ Images Master via product_masters.Values.db) ----
    function openDBModal(sku, currentValue) {
        $('#dbLinkSku').val(sku);
        $('#dbLinkInput').val(currentValue || '');
        $('#dbModalTitle').text(currentValue ? 'Edit DB Link' : 'Add DB Link');
        $('#dbLinkModal').modal('show');
    }

    $(document).on('click', '#saveDBLinkBtn', async function() {
        const sku = $('#dbLinkSku').val();
        const dbLink = ($('#dbLinkInput').val() || '').trim();

        if (!dbLink) {
            showToast('Please enter a DB link', 'error');
            return;
        }

        const saveBtn = $(this);
        const originalHtml = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

        try {
            const response = await fetch('/hero-images-master/update-db-link', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ sku: sku, db: dbLink })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast('DB link updated successfully', 'success');
                $('#dbLinkModal').modal('hide');
                if (table) {
                    table.setData('/hero-images-master-data-view');
                }
            } else {
                showToast(result.message || 'Failed to update DB link', 'error');
            }
        } catch (error) {
            console.error('Error saving DB link:', error);
            showToast(error.message || 'Failed to update DB link', 'error');
        } finally {
            saveBtn.prop('disabled', false).html(originalHtml);
        }
    });

    // ---- Hero Image Upload ----
    function openHeroImageUploadModal(sku) {
        $('#heroImageUploadSku').val(sku);
        $('#heroImageFileInput').val('');
        $('#heroImagePreviewContainer').hide();
        $('#heroImagePreview').attr('src', '');
        $('#heroImageUploadModal').modal('show');
    }

    $(document).on('change', '#heroImageFileInput', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            $('#heroImagePreview').attr('src', ev.target.result);
            $('#heroImagePreviewContainer').show();
        };
        reader.readAsDataURL(file);
    });

    // ---- Hero AI Analysis ----
    let currentHeroAnalysisSku = null;

    function escapeHtml(text) {
        if (text == null) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    // Pull CTR score out of the hero_analysis blob, tolerating both numeric
    // and "8/10" string formats that the model occasionally returns.
    function extractCtrScore(rowData) {
        if (!rowData) return null;
        const a = rowData.hero_analysis;
        if (!a || typeof a !== 'object') return null;
        const raw = a.ctr_score;
        if (raw === null || raw === undefined || raw === '') return null;
        const num = parseFloat(String(raw).split('/')[0]);
        return isNaN(num) ? null : num;
    }

    // Render a colored CTR percent badge for the "Ads CTR" / "Org CTR" columns.
    // `source` is 'ads' or 'organic'. Color thresholds reflect typical e-commerce
    // benchmarks (≥1% green, ≥0.4% yellow, <0.4% red) and can be tuned later.
    function renderRealCtrBadge(data, source) {
        const ctr = data ? data[source + '_ctr'] : null;
        const impressions = data ? data[source + '_impressions'] : null;
        const clicks = data ? data[source + '_clicks'] : null;
        const periodEnd = data ? data[source + '_period_end'] : null;

        if (ctr === null || ctr === undefined || ctr === '') {
            const hint = source === 'ads'
                ? 'No paid CTR available — ensure app:amazon-sp-campaign-reports has run and this SKU has an ENABLED Sponsored Products campaign with its SKU in the campaign name'
                : 'No organic CTR yet — run: php artisan amazon:fetch-ctr-organic';
            return `<span class="text-muted" title="${hint}" style="font-size: 11px;">—</span>`;
        }

        const n = parseFloat(ctr);
        let cls = source === 'ads' ? 'real-ctr-ads' : 'real-ctr-org';
        if (n >= 1) cls += ' s-pass';
        else if (n >= 0.4) cls += ' s-warn';
        else cls += ' s-fail';

        const tooltipParts = [];
        if (impressions !== null && impressions !== undefined) tooltipParts.push(Number(impressions).toLocaleString() + ' impressions');
        if (clicks !== null && clicks !== undefined) tooltipParts.push(Number(clicks).toLocaleString() + ' clicks');
        if (periodEnd) tooltipParts.push('as of ' + periodEnd);
        const tooltip = (source === 'ads' ? 'Sponsored Products CTR' : 'Organic search CTR') +
            (tooltipParts.length ? ' — ' + tooltipParts.join(' · ') : '');

        return `<span class="ai-score-badge ${cls}" title="${tooltip}">${n.toFixed(2)}%</span>`;
    }

    function renderHeroAnalysis(analysis, meta) {
        const body = document.getElementById('heroAnalysisBody');
        if (!body) return;

        if (!analysis || typeof analysis !== 'object') {
            body.innerHTML = `<div class="alert alert-warning mb-0">No analysis available.</div>`;
            return;
        }

        const passFail = String(analysis.pass_fail || '').toUpperCase();
        const compliance = String(analysis.marketplace_compliance || '').toUpperCase();
        const overall = (analysis.overall_score !== undefined && analysis.overall_score !== null)
            ? Number(analysis.overall_score) : '—';
        const ctr = (analysis.ctr_score !== undefined && analysis.ctr_score !== null)
            ? Number(analysis.ctr_score) : '—';
        const conv = (analysis.conversion_score !== undefined && analysis.conversion_score !== null)
            ? Number(analysis.conversion_score) : '—';

        function pill(value, type) {
            const v = String(value || '—').toUpperCase();
            const cls = (type === 'pass' || v === 'PASS') ? 'pill pill-pass'
                       : (type === 'fail' || v === 'FAIL') ? 'pill pill-fail'
                       : 'pill';
            return `<span class="${cls}">${escapeHtml(v)}</span>`;
        }

        function listBlock(title, items, color) {
            const arr = Array.isArray(items) ? items : (items ? [items] : []);
            if (!arr.length) return '';
            const lis = arr.map(i => `<li>${escapeHtml(i)}</li>`).join('');
            return `
                <div class="card mb-3 border-${color}">
                    <div class="card-header bg-${color} text-white py-2"><strong>${escapeHtml(title)}</strong></div>
                    <div class="card-body py-2"><ul class="mb-0 ps-3">${lis}</ul></div>
                </div>
            `;
        }

        const checks = analysis.detailed_checks || {};
        const checkKeys = ['background','sharpness','cropping','lighting','mobile_visibility','professionalism'];
        const checksHtml = checkKeys.map(k => {
            if (!checks[k]) return '';
            return `
                <div class="col-md-4 mb-3">
                    <div class="check-card">
                        <div class="label">${escapeHtml(k.replace(/_/g, ' '))}</div>
                        <div class="value">${escapeHtml(checks[k])}</div>
                    </div>
                </div>
            `;
        }).join('');

        const verdict = analysis.final_verdict
            ? `<div class="alert alert-info"><strong>Verdict:</strong> ${escapeHtml(analysis.final_verdict)}</div>`
            : '';

        body.innerHTML = `
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="score-tile"><div class="num">${escapeHtml(overall)}</div><div class="lbl">Overall / 10</div></div></div>
                <div class="col-md-3"><div class="score-tile"><div class="num">${escapeHtml(ctr)}</div><div class="lbl">CTR / 10</div></div></div>
                <div class="col-md-3"><div class="score-tile"><div class="num">${escapeHtml(conv)}</div><div class="lbl">Conversion / 10</div></div></div>
                <div class="col-md-3">
                    <div class="score-tile">
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                            ${pill(passFail || '—')}
                            <small class="text-muted">Marketplace: ${pill(compliance || '—')}</small>
                        </div>
                    </div>
                </div>
            </div>

            ${verdict}

            ${listBlock('Critical Issues', analysis.critical_issues, 'danger')}
            ${listBlock('Improvements', analysis.improvements, 'warning')}
            ${listBlock('Missing Angles / Hidden Parts', analysis.missing_angles, 'secondary')}
            ${listBlock('Possible AI / Fake Indicators', analysis.fake_or_ai_flags, 'dark')}

            <h6 class="mt-3 mb-2">Detailed Checks</h6>
            <div class="row">${checksHtml || '<div class="col-12 text-muted">No detailed checks returned.</div>'}</div>
        `;

        const metaEl = document.getElementById('heroAnalysisMeta');
        if (metaEl) {
            const parts = [];
            if (meta && meta.analyzed_at) parts.push('Analyzed: ' + new Date(meta.analyzed_at).toLocaleString());
            if (meta && meta.model) parts.push('Model: ' + meta.model);
            metaEl.textContent = parts.join(' · ');
        }
    }

    async function analyzeHero(sku, force) {
        currentHeroAnalysisSku = sku;
        $('#heroAnalysisSkuLabel').text(sku);
        $('#heroAnalysisMeta').text('');
        $('#heroAnalysisBody').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-3 text-muted">Analyzing hero image with AI… this can take 10–30 seconds.</p>
            </div>
        `);
        const modal = new bootstrap.Modal(document.getElementById('heroAnalysisModal'));
        modal.show();

        try {
            const response = await fetch('/hero-images-master/analyze', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ sku: sku, force: !!force })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                renderHeroAnalysis(result.analysis, {
                    analyzed_at: result.analyzed_at,
                    model: result.model
                });
                showToast('Hero image analyzed', 'success');
                if (table) table.setData('/hero-images-master-data-view');
            } else {
                $('#heroAnalysisBody').html(`
                    <div class="alert alert-danger mb-0">
                        <strong>Analysis failed:</strong> ${escapeHtml(result.message || 'Unknown error')}
                    </div>
                `);
                showToast(result.message || 'Failed to analyze image', 'error');
            }
        } catch (error) {
            console.error('Hero analyze error:', error);
            $('#heroAnalysisBody').html(`
                <div class="alert alert-danger mb-0">
                    <strong>Network error:</strong> ${escapeHtml(error.message || 'Request failed')}
                </div>
            `);
            showToast(error.message || 'Failed to analyze image', 'error');
        }
    }

    function viewHeroAnalysis(sku) {
        currentHeroAnalysisSku = sku;
        $('#heroAnalysisSkuLabel').text(sku);

        const row = (tableData || []).find(r => r && r.SKU === sku);
        if (!row || !row.hero_analysis) {
            // Nothing cached — kick off a fresh analysis
            analyzeHero(sku, false);
            return;
        }

        renderHeroAnalysis(row.hero_analysis, {
            analyzed_at: row.hero_analysis_at,
            model: row.hero_analysis_model
        });

        const modal = new bootstrap.Modal(document.getElementById('heroAnalysisModal'));
        modal.show();
    }

    $(document).on('click', '#heroAnalysisRerunBtn', function() {
        if (currentHeroAnalysisSku) {
            analyzeHero(currentHeroAnalysisSku, true);
        }
    });

    // ---- Push Hero Image to Marketplace ----
    async function pushHero(sku, site, force) {
        site = site || 'amazon';
        const siteLabel = site.charAt(0).toUpperCase() + site.slice(1);

        let confirmMsg = `Push the hero image for "${sku}" to ${siteLabel} as the MAIN product image?\n\nThis will replace the current main image on the live listing.`;
        if (force) {
            confirmMsg += `\n\nNOTE: This SKU is not marked approved (no AI PASS and status is not green). Push anyway?`;
        }
        if (!confirm(confirmMsg)) return;

        const cellsForSku = (table && table.getRows() || [])
            .filter(r => (r.getData() || {}).SKU === sku)
            .map(r => r.getCell('hero_pushed_at'))
            .filter(Boolean);

        const setBtnSpinner = (busy) => {
            cellsForSku.forEach(cell => {
                const el = cell.getElement().querySelector('button');
                if (!el) return;
                if (busy) {
                    el.dataset.origHtml = el.innerHTML;
                    el.disabled = true;
                    el.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                } else if (el.dataset.origHtml) {
                    el.innerHTML = el.dataset.origHtml;
                    el.disabled = false;
                }
            });
        };

        setBtnSpinner(true);

        try {
            const response = await fetch('/hero-images-master/push', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ sku: sku, site: site, force: !!force })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast(`Hero pushed to ${siteLabel}: ${result.message || 'Success'}`, 'success');
            } else if (result && result.requires_force) {
                // Approval gate hit — let the user push anyway
                if (confirm((result.message || 'Not approved') + '\n\nPush anyway?')) {
                    setBtnSpinner(false);
                    return pushHero(sku, site, true);
                }
                showToast('Push cancelled', 'info');
            } else {
                showToast(result.message || `Failed to push to ${siteLabel}`, 'error');
            }
        } catch (error) {
            console.error('Push hero error:', error);
            showToast(error.message || `Failed to push to ${siteLabel}`, 'error');
        } finally {
            setBtnSpinner(false);
            if (table) {
                table.setData('/hero-images-master-data-view');
            }
        }
    }

    $(document).on('click', '#saveHeroImageBtn', async function() {
        const fileInput = $('#heroImageFileInput')[0];
        const file = fileInput && fileInput.files ? fileInput.files[0] : null;
        const sku = $('#heroImageUploadSku').val();

        if (!sku) {
            showToast('SKU is missing', 'error');
            return;
        }

        if (!file) {
            showToast('Please select an image file', 'error');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            showToast('Image must be 10MB or smaller (selected: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB)', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('sku', sku);
        formData.append('image_file', file);

        const saveBtn = $(this);
        const originalHtml = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Uploading...');

        try {
            const response = await fetch('/hero-images-master/upload-hero-image', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: formData
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast('Hero image uploaded successfully!', 'success');
                $('#heroImageUploadModal').modal('hide');
                if (table) {
                    table.setData('/hero-images-master-data-view');
                }
            } else {
                showToast(result.message || result.error || 'Failed to upload image', 'error');
            }
        } catch (error) {
            console.error('Error uploading hero image:', error);
            showToast(error.message || 'Failed to upload image', 'error');
        } finally {
            saveBtn.prop('disabled', false).html(originalHtml);
        }
    });

</script>
@endsection
