@extends('layouts.vertical', ['title' => 'Usage Image Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        'page_title' => 'Usage Images Masters',
        'sub_title' => 'Usage Images Master Data',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Usage Images Master</h4>
                
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
                    <div id="usage-table" style="flex: 1;"></div>
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
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "usage_tabulator_column_visibility";
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
        table = new Tabulator("#usage-table", {
            ajaxURL: "/usage-images-master-data-view",
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
                }
            ]
        });

        // General Search
        $('#general-search').on('keyup', function() {
            table.setFilter([
                {field: "SKU", type: "like", value: this.value},
                {field: "Parent", type: "like", value: this.value},
                {field: "status", type: "like", value: this.value}
            ]);
        });

        // SKU Search
        $('#sku-search').on('keyup', function() {
            table.setFilter("SKU", "like", this.value);
        });

        // LQS Search
        $('#lqs-search').on('keyup', function() {
            table.setFilter("lqs", "like", this.value);
        });

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

</script>
@endsection
