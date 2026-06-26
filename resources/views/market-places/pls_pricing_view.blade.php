@extends('layouts.vertical', ['title' => 'PLS - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }
        
        .copy-sku-btn:hover {
            color: #0d6efd !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'PLS - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <div class="d-flex flex-column gap-1" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    {{-- Sold dropdown (mirrors Amazon tabulator + every other /pricing page).
                         Backed by `pls_l30` (PLS L30 sold qty). --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: auto;"
                            title="Filter by PLS L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>

                    <button id="pls-price-mode-btn" type="button" class="btn btn-sm btn-secondary">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         PLS server-side SGPFT / SROI formula (PlsController::savePlsSprice lines 865-871) treats
                         take-home as 100% — i.e. `(sprice − lp − ship) / sprice`, no margin factor — so the
                         back-solve omits the margin too. Formula: sprice = LP × (1 + ROI%/100) + Ship --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="pls-target-roi-controls"
                        title="Target ROI% — sets S PRC = LP × (1 + Target ROI%/100) + Ship on every selected row (back-solves so SROI column equals the target)">
                        <label for="pls-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="pls-target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S PRC'">
                        <button id="pls-apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = LP × (1 + Target ROI%/100) + Ship for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (1 − GPFT%/100). Target GPFT% must be < 100. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="pls-target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / (1 − Target GPFT%/100) on every selected row">
                        <label for="pls-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="pls-target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than 100% (PLS take-home).">
                        <button id="pls-apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP + Ship) / (1 − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>

                    <button id="pls-sugg-amz-prc-btn" type="button" class="btn btn-sm btn-warning">
                        <i class="fab fa-amazon"></i> Sugg Amz Prc
                    </button>

                    <button id="pls-clear-sprice-btn" class="btn btn-sm btn-danger" style="display: none;">
                        <i class="fas fa-eraser"></i> Clear SPRICE
                    </button>

                    <!-- Play / Pause parent navigation -->
                    <div class="btn-group align-items-center ms-2" role="group" aria-label="Parent navigation">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation and show all">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary</h6>
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge bg-info fs-6 p-2 flex-fill text-center" id="total-l30-badge">PLS L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2 flex-fill text-center" id="avg-price-badge">Price: $0</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="avg-gpft-badge">GPFT%: 0%</span>
                        <span class="badge fs-6 p-2 flex-fill text-center" id="avg-roi-badge" style="background-color: purple; color: white;">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="missing-price-badge" style="cursor: pointer;">Missing: 0</span>
                        <span class="badge bg-danger fs-6 p-2 flex-fill text-center" id="not-mapped-badge" style="cursor: pointer;">N MP: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when Price % mode is active and SKUs are selected) -->
                <div id="pls-discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="pls-selected-skus-count" class="fw-bold"></span>
                        <span class="d-flex align-items-center gap-2">
                            <select id="pls-discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold">Value:</label>
                        <input type="number" id="pls-discount-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="pls-apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="pls-clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="pls-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                    </div>
                    <div id="pls-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="plsEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="plsEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="plsSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="plsBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="plsSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "pls_tabulator_column_visibility";
    const PLS_PERCENTAGE = {{ $plsPercentage ?? 100 }} / 100; // Dynamic from database
    let table = null;

    // Play / Pause parent navigation state
    let plsUniqueParents = [];
    let isPlsPlayActive = false;
    let currentPlsParentIndex = -1;

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
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

    $(document).ready(function() {

        // ---- Edit Links (Buyer / Seller) ----
        let plsEditLinksRow = null;
        window.openPlsEditLinksModal = function(row) {
            plsEditLinksRow = row;
            const d = row.getData();
            $('#plsEditLinksSku').text(d.sku || '');
            $('#plsSellerLinkInput').val(d.seller_link || '');
            $('#plsBuyerLinkInput').val(d.buyer_link || '');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('plsEditLinksModal')).show();
        };
        $('#plsSaveLinksBtn').on('click', function() {
            if (!plsEditLinksRow) return;
            const sku = plsEditLinksRow.getData().sku;
            const sellerLink = $('#plsSellerLinkInput').val().trim();
            const buyerLink = $('#plsBuyerLinkInput').val().trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: '/pls/save-links',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sku: sku,
                    seller_link: sellerLink,
                    buyer_link: buyerLink
                },
                success: function(res) {
                    if (res && res.success) {
                        plsEditLinksRow.update({
                            seller_link: res.seller_link || '',
                            buyer_link: res.buyer_link || ''
                        }).then(function() {
                            plsEditLinksRow.reformat();
                        }).catch(function() {
                            plsEditLinksRow.reformat();
                        });
                        showToast('Links saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('plsEditLinksModal')).hide();
                    } else {
                        showToast((res && res.message) || 'Failed to save links', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $('#inventory-filter').on('change', function () { applyFilters(); });
        $('#gpft-filter').on('change', function () { applyFilters(); });
        $('#roi-filter').on('change', function () { applyFilters(); });
        $('#dil-filter').on('change', function () { applyFilters(); });
        $('#sold-filter').on('change', function () { applyFilters(); });

        $('#sku-search').on('keyup', function () { applyFilters(); });
        $('#parent-search').on('keyup', function () { applyFilters(); });

        function applyFilters() {
            table.clearFilter();

            // When Play navigation is active, only show rows matching the current parent
            if (isPlsPlayActive && plsUniqueParents.length > 0 && currentPlsParentIndex >= 0) {
                const currentKey = plsUniqueParents[currentPlsParentIndex];
                if (currentKey) {
                    table.addFilter(function(data) {
                        const p = normalizePlsParentKey(data.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                }
                updateSummary();
                return;
            }
            
            const invFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const dilFilter = $('#dil-filter').val();
            const soldFilter = $('#sold-filter').val();
            const skuSearch = ($('#sku-search').val() || '').toString().trim().toLowerCase();
            const parentSearch = ($('#parent-search').val() || '').toString().trim().toLowerCase();
            
            // Use a single combined filter function
            table.addFilter(function(data) {
                // SKU search
                if (skuSearch && !((data.sku || '').toString().toLowerCase().includes(skuSearch))) return false;
                // Parent search
                if (parentSearch && !((data.parent || '').toString().toLowerCase().includes(parentSearch))) return false;
                // Inventory filter
                if (invFilter === 'zero' && parseInt(data.inventory) !== 0) return false;
                if (invFilter === 'more' && parseInt(data.inventory) <= 0) return false;
                
                // GPFT filter
                if (gpftFilter !== 'all') {
                    const gpft = parseFloat(data.gpft_pct) || 0;
                    if (gpftFilter === 'negative' && gpft >= 0) return false;
                    if (gpftFilter === '0-10' && (gpft < 0 || gpft >= 10)) return false;
                    if (gpftFilter === '10-20' && (gpft < 10 || gpft >= 20)) return false;
                    if (gpftFilter === '20-30' && (gpft < 20 || gpft >= 30)) return false;
                    if (gpftFilter === '30-40' && (gpft < 30 || gpft >= 40)) return false;
                    if (gpftFilter === '40-50' && (gpft < 40 || gpft >= 50)) return false;
                    if (gpftFilter === '50plus' && gpft < 50) return false;
                }
                
                // ROI filter
                if (roiFilter !== 'all') {
                    const roi = parseFloat(data.roi_pct) || 0;
                    if (roiFilter === 'lt40' && roi >= 40) return false;
                    if (roiFilter === '40-75' && (roi < 40 || roi >= 75)) return false;
                    if (roiFilter === '75-125' && (roi < 75 || roi >= 125)) return false;
                    if (roiFilter === 'gt125' && roi < 125) return false;
                }
                
                // DIL filter
                if (dilFilter !== 'all') {
                    const dil = parseFloat(data.dil_pct) || 0;
                    if (dilFilter === 'red' && dil >= 16.7) return false;
                    if (dilFilter === 'yellow' && (dil < 16.7 || dil >= 25)) return false;
                    if (dilFilter === 'green' && (dil < 25 || dil >= 50)) return false;
                    if (dilFilter === 'pink' && dil < 50) return false;
                }

                // Sold filter (PLS L30 sold qty — `pls_l30` field). Mirrors the Amazon
                // tabulator dropdown styling.
                if (soldFilter && soldFilter !== 'all') {
                    const soldQty = parseInt(data.pls_l30) || 0;
                    if (soldFilter === 'sold' && !(soldQty > 0))   return false;
                    if (soldFilter === 'zero' && !(soldQty === 0)) return false;
                }

                return true;
            });
            
            updateSummary();
        }

        // ========== Play / Pause parent navigation ==========
        function normalizePlsParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildPlsUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizePlsParentKey(r.parent);
                if (p && !seen[p]) {
                    seen[p] = true;
                    list.push(p);
                }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updatePlsPlayButtonStates() {
            $('#play-backward').prop('disabled', !isPlsPlayActive || currentPlsParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlsPlayActive || currentPlsParentIndex >= plsUniqueParents.length - 1);
        }
        function startPlsPlay() {
            plsUniqueParents = buildPlsUniqueParents();
            if (plsUniqueParents.length === 0) {
                showToast('No parent groups found in data', 'info');
                return;
            }
            isPlsPlayActive = true;
            currentPlsParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updatePlsPlayButtonStates();
        }
        function stopPlsPlay() {
            isPlsPlayActive = false;
            currentPlsParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyFilters();
            updatePlsPlayButtonStates();
        }
        function nextPlsParent() {
            if (!isPlsPlayActive) return;
            if (currentPlsParentIndex >= plsUniqueParents.length - 1) return;
            currentPlsParentIndex++;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updatePlsPlayButtonStates();
        }
        function previousPlsParent() {
            if (!isPlsPlayActive) return;
            if (currentPlsParentIndex <= 0) return;
            currentPlsParentIndex--;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updatePlsPlayButtonStates();
        }
        $('#play-auto').on('click', startPlsPlay);
        $('#play-pause').on('click', stopPlsPlay);
        $('#play-forward').on('click', nextPlsParent);
        $('#play-backward').on('click', previousPlsParent);

        // Initialize Tabulator
        table = new Tabulator('#pls-table', {
            ajaxURL: '/pls-pricing-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            initialSort: [
                {column: "sku", dir: "asc"}
            ],
            columnCalcs: 'both',
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            rowFormatter: function(row) {
                if (row.getData().parent && row.getData().parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                }
            },
            columns: [
                {
                    title: "Select",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    titleFormatter: function(column) {
                        return `<div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                            <span>Select</span>
                            <input type="checkbox" id="pls-select-all-checkbox" style="cursor:pointer;" title="Select All">
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        if (!sku) return '';
                        const isChecked = plsSelectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="pls-sku-select-checkbox" data-sku="${sku}" ${isChecked} style="cursor:pointer;">`;
                    },
                    width: 60
                },
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 70
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 200,
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Case-insensitive alphabetical sorting
                        const aVal = (a || '').toString().toUpperCase();
                        const bVal = (b || '').toString().toUpperCase();
                        return aVal.localeCompare(bVal);
                    },
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        let html = `<span>${sku}</span>`;
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        return html;
                    }
                },
                {
                    title: "Links",
                    field: "links_column",
                    width: 55,
                    frozen: true,
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "Double-click to add / edit links",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const buyerLink = d.buyer_link || '';
                        const sellerLink = d.seller_link || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                        if (sellerLink) {
                            html += '<a href="' + sellerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                        }
                        if (buyerLink) {
                            html += '<a href="' + buyerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        openPlsEditLinksModal(cell.getRow());
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "l30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "Dil",
                    field: "dil_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.inventory) || 0;
                        const OVL30 = parseFloat(rowData.l30) || 0;
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';
                        
                        if (dil < 16.66) color = '#a00211';
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "PLS INV",
                    field: "pls_inventory",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #28a745;">${v}</span>`;
                    }
                },
                {
                    title: "PLS L30",
                    field: "pls_l30",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #17a2b8;">${v}</span>`;
                    }
                },
                {
                    title: "Prc",
                    field: "price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        return `<span style="font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "A Prc",
                    field: "amazon_price",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "Amazon price",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        if (value === 0) {
                            return `<span style="color: #adb5bd;">-</span>`;
                        }
                        return `<span style="font-weight: 600; color: #ff9900;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "MC L30",
                    field: "l60",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    width: 150,
                    visible: false,
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Case-insensitive alphabetical sorting
                        const aVal = (a || '').toString().toUpperCase();
                        const bVal = (b || '').toString().toUpperCase();
                        return aVal.localeCompare(bVal);
                    }
                },
                {
                    title: "PLS L60",
                    field: "pls_l60",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const v = parseInt(cell.getValue() || 0);
                        return `<span style="font-weight:600; color: #6f42c1;">${v}</span>`;
                    }
                },
                {
                    title: "Missing",
                    field: "missing",
                    hozAlign: "center",
                    sorter: "string",
                    width: 80,
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold;" title="Not found in pls_products or INV>0 but no price">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    sorter: "string",
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const missing = rowData['missing'];

                        // Only show MAP if SKU exists in PLS (not missing)
                        if (missing === 'M') {
                            return ''; // Don't show MAP for missing items
                        }
                        
                        const plsInventory = parseFloat(rowData['pls_inventory']) || 0;
                        const inv = parseFloat(rowData['inventory']) || 0;
                        
                        if (inv > 0 && plsInventory === 0) {
                            if (inv <= 3) {
                                return '<span style="color: #28a745; font-weight: bold;" title="Within tolerance (≤3)">MP</span>';
                            }
                            return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${inv})</span>`;
                        }
                        
                        if (inv > 0 && plsInventory > 0) {
                            if (inv === plsInventory || Math.abs(inv - plsInventory) <= 3) {
                                return '<span style="color: #28a745; font-weight: bold;" title="Within ≤3: counts as MP">MP</span>';
                            } else {
                                const diff = inv - plsInventory;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                        }
                        
                        return '';
                    }
                },
                {
                    title: "GPFT%",
                    field: "gpft_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "PFT%",
                    field: "gpft_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "ROI%",
                    field: "roi_pct",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const hasCustomSprice = rowData.has_custom_sprice;
                        const currentPrice = parseFloat(rowData.price) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value) return '';
                        
                        // Show SPRICE value
                        const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                        
                        // If using default price (not custom), show in blue
                        if (hasCustomSprice === false) {
                            return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                        }
                        
                        return formattedValue;
                    },
                    width: 80
                },
                {
                    title: "SGPFT%",
                    field: "sgpft",
                    hozAlign: "center",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent < 30) color = '#28a745';
                        else color = '#20c997';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "SROI%",
                    field: "sroi",
                    hozAlign: "center",
                    sorter: "number",
                    visible: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "P STS",
                    field: "pls_status",
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "PLS push status: ✓✓ pushed, ✗ failed, — not pushed",
                    formatter: function(cell) {
                        const st = cell.getValue();
                        if (st === 'pushed' || st === 'applied') {
                            return '<span style="color:#28a745;" title="PLS: price pushed"><i class="fa-solid fa-check-double"></i></span>';
                        }
                        if (st === 'error') {
                            return '<span style="color:#dc3545;" title="PLS: push failed"><i class="fa-solid fa-xmark"></i></span>';
                        }
                        if (st === 'processing') {
                            return '<span style="color:#ffc107;" title="PLS: processing..."><i class="fas fa-spinner fa-spin"></i></span>';
                        }
                        return '<span style="color:#adb5bd;" title="PLS: not pushed">—</span>';
                    },
                    width: 50
                },
                {
                    title: "Push",
                    field: "_push",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku || '';
                        const spriceRaw = rowData.sprice;
                        const sprice = spriceRaw ? parseFloat(spriceRaw) : 0;
                        const plsStatus = rowData.pls_status || null;
                        
                        if (!sku || !sprice || sprice <= 0) {
                            return '<span style="color: #adb5bd;">N/A</span>';
                        }
                        
                        // Determine PLS button icon and color
                        let plsIcon = '<i class="fas fa-check"></i>';
                        let plsColor = '#28a745'; // Green
                        let plsTitle = 'Push to PLS';
                        
                        if (plsStatus === 'pushed' || plsStatus === 'applied') {
                            plsIcon = '<i class="fa-solid fa-check-double"></i>';
                            plsColor = '#28a745'; // Green when pushed
                            plsTitle = 'Price pushed to PLS';
                        } else if (plsStatus === 'error') {
                            plsIcon = '<i class="fa-solid fa-x"></i>';
                            plsColor = '#dc3545'; // Red
                            plsTitle = 'Error pushing to PLS';
                        } else if (plsStatus === 'processing') {
                            plsIcon = '<i class="fas fa-spinner fa-spin"></i>';
                            plsColor = '#ffc107'; // Yellow
                            plsTitle = 'Pushing to PLS...';
                        }
                        
                        return `<button type="button" class="btn btn-sm push-price-btn btn-circle" 
                                       data-sku="${sku}" 
                                       data-price="${spriceRaw}" 
                                       data-status="${plsStatus || ''}" 
                                       title="${plsTitle}" 
                                       style="border: none; background: none; color: ${plsColor}; padding: 0; cursor: pointer; font-size: 1.3em;">
                                    ${plsIcon}
                                </button>`;
                    },
                    cellClick: function(e, cell) {
                        // Handle button click
                        const $target = $(e.target);
                        
                        if ($target.hasClass('push-price-btn') || $target.closest('.push-price-btn').length) {
                            e.stopPropagation();
                            const $btn = $target.hasClass('push-price-btn') ? $target : $target.closest('.push-price-btn');
                            
                            // Read price from fresh row data
                            const rowData = cell.getRow().getData();
                            const sku = rowData.sku;
                            const price = parseFloat(rowData.sprice) || 0;
                            
                            if (!sku || !price || price <= 0 || isNaN(price)) {
                                showToast('Invalid SKU or price', 'error');
                                return;
                            }
                            
                            // Disable button and show loading state
                            $btn.prop('disabled', true);
                            $btn.html('<i class="fas fa-clock fa-spin" style="color: #ffc107;"></i>');
                            
                            // Update row status to processing
                            const row = cell.getRow();
                            const updatedData = row.getData();
                            updatedData.pls_status = 'processing';
                            row.update(updatedData);
                            
                            // Push to PLS
                            $.ajax({
                                url: '/push-pls-price',
                                method: 'POST',
                                timeout: 120000,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                data: {
                                    sku: sku,
                                    price: price
                                },
                                success: function(response) {
                                    // Check for errors in response
                                    if (response.errors && response.errors.length > 0) {
                                        const errorMsg = response.errors[0].message || 'Unknown error';
                                        const row = cell.getRow();
                                        const rowData = row.getData();
                                        rowData.pls_status = 'error';
                                        row.update(rowData);
                                        
                                        $btn.prop('disabled', false);
                                        $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                        showToast(`PLS push failed: ${errorMsg}`, 'error');
                                        return;
                                    }
                                    
                                    // Success - update row data with pushed status
                                    const row = cell.getRow();
                                    const rowData = row.getData();
                                    const plsPush = response.pls_push || {};
                                    rowData.pls_status = (plsPush.ok) ? 'pushed' : 'error';
                                    row.update(rowData);
                                    
                                    $btn.prop('disabled', false);
                                    if (plsPush.ok) {
                                        $btn.html('<i class="fas fa-check-double" style="color: #28a745;"></i>');
                                        const msg = plsPush.message || 'Price pushed to PLS successfully';
                                        showToast(`${msg} for SKU: ${sku}`, 'success');
                                    } else {
                                        $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                        const msg = plsPush.message || 'PLS push failed';
                                        showToast(msg, 'error');
                                    }
                                    
                                    // Update summary
                                    updateSummary();
                                },
                                error: function(xhr) {
                                    const row = cell.getRow();
                                    const rowData = row.getData();
                                    rowData.pls_status = 'error';
                                    row.update(rowData);
                                    
                                    $btn.prop('disabled', false);
                                    $btn.html('<i class="fas fa-times" style="color: #dc3545;"></i>');
                                    
                                    const errorMsg = xhr.responseJSON?.message || xhr.responseJSON?.errors?.[0]?.message || 'Unknown error';
                                    showToast(`PLS push failed: ${errorMsg}`, 'error');
                                    
                                    // Update summary
                                    updateSummary();
                                }
                            });
                        }
                    },
                    width: 60
                },
            ]
        });

        // Copy SKU functionality
        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(function() {
                showToast(`SKU "${sku}" copied to clipboard`, 'success');
            }).catch(function() {
                showToast('Failed to copy SKU', 'error');
            });
        });

        function updateSummary() {
            const data = table.getData('active');
            let totalProducts = data.length;
            let totalInventory = 0;
            let totalPlsL30 = 0;
            let totalPrice = 0;
            let priceCount = 0;
            let missingPrice = 0;
            let notMappedCount = 0;
            let totalSales = 0;
            let totalProfit = 0;
            let totalCogs = 0;

            data.forEach(row => {
                const inv = parseInt(row.inventory) || 0;
                const l30 = parseInt(row.l30) || 0;
                const plsL30 = parseInt(row.pls_l30) || 0;
                const price = parseFloat(row.price) || 0;
                const gpft = parseFloat(row.gpft_pct) || 0;
                const roi = parseFloat(row.roi_pct) || 0;
                const plsInv = parseInt(row.pls_inventory) || 0;
                const missing = row.missing || '';
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;

                totalInventory += inv;
                totalPlsL30 += plsL30;

                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                } else if (inv > 0) {
                    missingPrice++;
                }

                // Calculate weighted GPFT and ROI (by sales volume, using dynamic marketplace percentage)
                if (plsL30 > 0 && price > 0) {
                    const sales = price * plsL30;
                    const profit = ((price * PLS_PERCENTAGE) - lp - ship) * plsL30;
                    const cogs = lp * plsL30;
                    
                    totalSales += sales;
                    totalProfit += profit;
                    totalCogs += cogs;
                }
                
                // Count N MP (Not Mapped) - same logic as MAP column formatter
                if (missing !== 'M') {
                    if (inv > 0 && plsInv === 0 && inv > 3) {
                        notMappedCount++;
                    } else if (inv > 0 && plsInv > 0) {
                        if (inv !== plsInv && Math.abs(inv - plsInv) > 3) {
                            notMappedCount++;
                        }
                    }
                }
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgGpft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const avgRoi = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

            $('#total-l30-badge').text(`PLS L30: ${totalPlsL30.toLocaleString()}`);
            $('#avg-price-badge').text(`Price: $${avgPrice.toFixed(2)}`);
            $('#avg-gpft-badge').text(`GPFT%: ${avgGpft.toFixed(0)}%`);
            $('#avg-roi-badge').text(`ROI%: ${avgRoi.toFixed(0)}%`);
            $('#missing-price-badge').text(`Missing: ${missingPrice}`);
            $('#not-mapped-badge').text(`N MP: ${notMappedCount}`);
        }

        table.on('dataLoaded', function () { setTimeout(updateSummary, 100); });
        table.on('dataFiltered', function () { setTimeout(updateSummary, 100); });
        table.on('renderComplete', function () { setTimeout(updateSummary, 100); });

        // Cell edited event for SPRICE
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const data = row.getData();
            
            if (field === 'sprice') {
                const sku = data.sku;
                const value = parseFloat(cell.getValue());
                
                if (!sku || !value || value <= 0) {
                    showToast('error', 'Invalid SPRICE value');
                    return;
                }
                
                // Save SPRICE to server
                $.ajax({
                    url: '/save-pls-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: value
                    },
                    success: function(response) {
                        showToast('success', 'SPRICE updated successfully');
                        // Update SGPFT% and SROI% from response
                        const updates = { 'sprice': response.data || value };
                        if (response.sgpft_percent !== undefined) {
                            updates['sgpft'] = response.sgpft_percent;
                        }
                        if (response.sroi_percent !== undefined) {
                            updates['sroi'] = response.sroi_percent;
                        }
                        if (response.has_custom_sprice !== undefined) {
                            updates['has_custom_sprice'] = response.has_custom_sprice;
                        }
                        row.update(updates);
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to update SPRICE');
                        console.error('SPRICE save error:', xhr);
                    }
                });
            }
        });

        // Toast notification function
        // Badge click filters
        $('#missing-price-badge').on('click', function() {
            table.clearFilter();
            table.addFilter(function(data) {
                return parseFloat(data.price) === 0 && parseInt(data.inventory) > 0;
            });
            updateSummary();
        });
        
        $('#not-mapped-badge').on('click', function() {
            table.clearFilter();
            table.addFilter(function(data) {
                const inv = parseInt(data.inventory) || 0;
                const plsInv = parseInt(data.pls_inventory) || 0;
                const missing = data.missing || '';
                
                // Show N MP rows - same logic as MAP column
                if (missing === 'M') return false;
                
                if (inv > 0 && plsInv === 0 && inv > 3) return true;
                if (inv > 0 && plsInv > 0 && inv !== plsInv && Math.abs(inv - plsInv) > 3) return true;
                
                return false;
            });
            updateSummary();
        });

        // Column dropdown
        function buildColumnDropdown() {
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        table.on('tableBuilt', buildColumnDropdown);

        document.getElementById('column-dropdown-menu').addEventListener('change', function (e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) e.target.checked ? col.show() : col.hide();
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function () {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
        });

        // Export CSV
        document.getElementById('export-btn').addEventListener('click', function () {
            const visibleCols = table.getColumns().filter(c => c.isVisible());
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField()).map(h => h.replace(/<[^>]*>/g, ''));
            const rows = table.getData('active').map(row =>
                visibleCols.map(col => {
                    let v = row[col.getField()];
                    if (v === null || v === undefined) return '';
                    if (typeof v === 'number') return parseFloat(v.toFixed(2));
                    if (typeof v === 'string' && (v.includes(',') || v.includes('"')))
                        return '"' + v.replace(/"/g, '""') + '"';
                    return v;
                })
            );
            const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = 'pls_pricing_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });

        // ─── Price % (Increase / Decrease / Same Price) ───────────────────────

        let plsDecreaseModeActive = false;
        let plsIncreaseModeActive = false;
        let plsSamePriceModeActive = false;
        let plsSelectedSkus = new Set();

        function roundToRetailPrice(price) {
            if (price < 20.99) return +price.toFixed(2);
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }

        function roundToRetailPrice49(price) {
            if (price < 20.99) return +price.toFixed(2);
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        function plsUpdateSelectedCount() {
            const count = plsSelectedSkus.size;
            $('#pls-selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#pls-discount-input-container').toggle(
                count > 0 || plsDecreaseModeActive || plsIncreaseModeActive || plsSamePriceModeActive
            );
            // Show/hide the standalone Clear SPRICE btn in toolbar
            $('#pls-clear-sprice-btn').toggle(count > 0);
        }

        function plsUpdateSelectAllCheckbox() {
            if (!table) return;
            const activeData = table.getData('active').filter(r => !r.parent || !String(r.parent).toUpperCase().startsWith('PARENT'));
            if (!activeData.length) { $('#pls-select-all-checkbox').prop('checked', false); return; }
            const allSelected = activeData.every(r => r.sku && plsSelectedSkus.has(r.sku));
            $('#pls-select-all-checkbox').prop('checked', allSelected);
        }

        function syncPlsPriceModeUi() {
            if (!table || !table.getColumn) return;
            const $btn = $('#pls-price-mode-btn');
            const selectColumn = table.getColumn('_select');

            if (plsDecreaseModeActive) {
                $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectColumn) selectColumn.show();
                return;
            }
            if (plsIncreaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectColumn) selectColumn.show();
                return;
            }
            if (plsSamePriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                if (selectColumn) selectColumn.show();
                return;
            }
            // All modes off
            $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price %');
            if (selectColumn) selectColumn.hide();
            plsSelectedSkus.clear();
            $('.pls-sku-select-checkbox').prop('checked', false);
            $('#pls-select-all-checkbox').prop('checked', false);
            $('#pls-discount-input-container').hide();
            plsUpdateSelectedCount();
        }

        // Toggle through modes: off → Decrease → Increase → Same Price → off
        $('#pls-price-mode-btn').on('click', function() {
            if (!plsDecreaseModeActive && !plsIncreaseModeActive && !plsSamePriceModeActive) {
                plsDecreaseModeActive = true;
            } else if (plsDecreaseModeActive) {
                plsDecreaseModeActive = false;
                plsIncreaseModeActive = true;
            } else if (plsIncreaseModeActive) {
                plsIncreaseModeActive = false;
                plsSamePriceModeActive = true;
            } else {
                plsSamePriceModeActive = false;
            }
            syncPlsPriceModeUi();
        });

        // Discount type dropdown change
        $('#pls-discount-type-select').on('change', function() {
            const type = $(this).val();
            const $input = $('#pls-discount-input');
            if (type === 'percentage') {
                $input.attr('placeholder', 'Enter %').attr('max', '100');
            } else {
                $input.attr('placeholder', 'Enter value ($)').removeAttr('max');
            }
        });

        // Select-all checkbox
        $(document).on('change', '#pls-select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const activeData = table ? table.getData('active') : [];
            activeData.forEach(function(row) {
                if (row.parent && String(row.parent).toUpperCase().startsWith('PARENT')) return;
                if (row.sku) {
                    if (isChecked) plsSelectedSkus.add(row.sku);
                    else plsSelectedSkus.delete(row.sku);
                }
            });
            $('.pls-sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', plsSelectedSkus.has(sku));
            });
            plsUpdateSelectedCount();
        });

        // Individual checkbox
        $(document).on('change', '.pls-sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) plsSelectedSkus.add(sku);
            else plsSelectedSkus.delete(sku);
            plsUpdateSelectedCount();
            plsUpdateSelectAllCheckbox();
        });

        // saveSpriceWithRetry for PLS
        function plsSaveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise(function(resolve, reject) {
                if (row) row.update({ sprice: sprice });

                $.ajax({
                    url: '/save-pls-sprice',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: sku, sprice: sprice },
                    success: function(response) {
                        // Re-find row in case table redrew
                        let targetRow = row;
                        if (table && table.getRows) {
                            table.getRows().forEach(function(r) {
                                if (r.getData().sku === sku) targetRow = r;
                            });
                        }
                        if (targetRow) {
                            targetRow.update({
                                sprice: parseFloat(sprice),
                                sgpft: response.sgpft_percent != null ? response.sgpft_percent : 0,
                                sroi:  response.sroi_percent  != null ? response.sroi_percent  : 0,
                                has_custom_sprice: true
                            });
                            targetRow.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        if (retryCount < 1) {
                            setTimeout(function() {
                                plsSaveSpriceWithRetry(sku, sprice, row, retryCount + 1).then(resolve).catch(reject);
                            }, 2000);
                        } else {
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

        // Apply discount/increase/same-price to selected SKUs
        function plsApplyDiscount() {
            if (!plsDecreaseModeActive && !plsIncreaseModeActive && !plsSamePriceModeActive) {
                showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                return;
            }
            if (plsSelectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const rawInput   = $('#pls-discount-input').val();
            const inputValue = parseFloat(String(rawInput || '').replace(',', '.'));
            const discountType = $('#pls-discount-type-select').val();

            if (!plsSamePriceModeActive) {
                if (rawInput === '' || rawInput == null) {
                    showToast('Please enter a value (% or $)', 'error');
                    return;
                }
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('Please enter a valid positive number', 'error');
                    return;
                }
                if (discountType === 'percentage' && inputValue > 100) {
                    showToast('Percentage cannot exceed 100', 'error');
                    return;
                }
            }

            const allData = table.getData('all');
            let updatedCount = 0, errorCount = 0;
            const totalSkus = plsSelectedSkus.size;
            const appliedAsSamePrice = plsSamePriceModeActive;

            allData.forEach(function(row) {
                if (row.parent && String(row.parent).toUpperCase().startsWith('PARENT')) return;
                const sku = row.sku;
                if (!plsSelectedSkus.has(sku)) return;

                const originalPrice = parseFloat(row.price) || 0;
                if (originalPrice <= 0) return;

                let newPriceNum;
                if (plsSamePriceModeActive) {
                    let newSPrice = roundToRetailPrice(originalPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                } else {
                    let newSPrice;
                    if (discountType === 'percentage') {
                        const decimal = inputValue / 100;
                        newSPrice = plsIncreaseModeActive
                            ? originalPrice * (1 + decimal)
                            : originalPrice * (1 - decimal);
                    } else {
                        newSPrice = plsIncreaseModeActive
                            ? originalPrice + inputValue
                            : Math.max(0.01, originalPrice - inputValue);
                    }
                    newSPrice = Math.max(0.01, newSPrice);
                    newSPrice = roundToRetailPrice(newSPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                }

                const originalSprice = parseFloat(row.sprice) || 0;
                const tableRow = table.getRows().find(function(r) { return r.getData().sku === sku; });

                if (tableRow) tableRow.update({ sprice: newPriceNum });

                plsSaveSpriceWithRetry(sku, newPriceNum, tableRow)
                    .then(function() {
                        updatedCount++;
                        if (updatedCount + errorCount === totalSkus) {
                            showToast(
                                appliedAsSamePrice
                                    ? `SPRICE set to price for ${updatedCount} SKU(s)`
                                    : `Applied to ${updatedCount} SKU(s)`,
                                'success'
                            );
                        }
                    })
                    .catch(function() {
                        errorCount++;
                        if (tableRow) tableRow.update({ sprice: originalSprice });
                        if (updatedCount + errorCount === totalSkus) {
                            showToast(`Applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                        }
                    });
            });
        }

        $('#pls-apply-discount-btn').on('click', function() { plsApplyDiscount(); });
        $('#pls-discount-input').on('keypress', function(e) {
            if (e.which === 13) plsApplyDiscount();
        });

        /*
         * Target ROI% / Target GPFT% bulk apply (PLS, no margin factor)
         * -------------------------------------------------------------
         * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
         * target. PLS's server-side SGPFT / SROI formulas (PlsController::savePlsSprice
         * lines 865-871) and the matching client-side computations (plsApplyDiscount
         * lines 1640-1641 of the original file) treat take-home as 100% — they're:
         *     SGPFT% = ((sprice − lp − ship) / sprice) * 100
         *     SROI%  = ((sprice − lp − ship) / lp)     * 100
         *   → sprice = lp * (1 + ROI%/100)  + ship
         *   → sprice = (lp + ship) / (1 − GPFT%/100)   (target < 100 required)
         * Each save goes through the existing plsSaveSpriceWithRetry() Promise pipeline
         * so the row gets the server-returned sgpft / sroi values automatically.
         * Plain 2-decimal rounding — no .99 / .49 retail snapping — because snapping
         * would shift the achieved SROI / SGPFT off the user-typed target.
         */
        function plsApplyTargetBackSolve(computeFn, labelPrefix) {
            if (plsSelectedSkus.size === 0) {
                showToast('Please select at least one SKU first (turn on Price % to reveal checkboxes)', 'error');
                return;
            }

            const allData = table.getData('all');
            const tasks   = [];
            let skippedNoLp = 0;
            let skippedHigh = 0;

            allData.forEach(function (row) {
                if (row.parent && String(row.parent).toUpperCase().startsWith('PARENT')) return;
                const sku = row.sku;
                if (!sku || !plsSelectedSkus.has(sku)) return;

                const lp = parseFloat(row.lp) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(row.ship) || 0;

                const computed = computeFn(lp, ship);
                if (computed == null) { skippedHigh++; return; }
                const newSprice = +computed.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const tableRow = table.getRows().find(function (r) { return r.getData().sku === sku; });
                if (!tableRow) return;
                const originalSprice = parseFloat(row.sprice) || 0;
                tableRow.update({ sprice: newSprice });

                tasks.push({ sku: sku, newSprice: newSprice, tableRow: tableRow, originalSprice: originalSprice });
            });

            if (tasks.length === 0) {
                if (skippedHigh > 0) {
                    showToast(labelPrefix + ' too high — must be less than 100% (PLS take-home).', 'error');
                } else {
                    showToast('No selected rows have a usable LP > 0', 'warning');
                }
                return;
            }

            let okCount  = 0;
            let errCount = 0;
            const total  = tasks.length;

            tasks.forEach(function (t) {
                plsSaveSpriceWithRetry(t.sku, t.newSprice, t.tableRow)
                    .then(function () {
                        okCount++;
                        if (okCount + errCount === total) {
                            let note = '';
                            if (skippedNoLp > 0) note += ' (' + skippedNoLp + ' skipped — no LP)';
                            if (skippedHigh > 0) note += ' (' + skippedHigh + ' skipped — target ≥ 100%)';
                            if (errCount === 0) {
                                showToast(labelPrefix + ' applied to ' + okCount + ' SKU(s)' + note, 'success');
                            } else {
                                showToast(labelPrefix + ' applied to ' + okCount + ' SKU(s), ' + errCount + ' failed' + note, 'error');
                            }
                        }
                    })
                    .catch(function () {
                        errCount++;
                        if (t.tableRow) t.tableRow.update({ sprice: t.originalSprice });
                        if (okCount + errCount === total) {
                            let note = '';
                            if (skippedNoLp > 0) note += ' (' + skippedNoLp + ' skipped — no LP)';
                            if (skippedHigh > 0) note += ' (' + skippedHigh + ' skipped — target ≥ 100%)';
                            showToast(labelPrefix + ' applied to ' + okCount + ' SKU(s), ' + errCount + ' failed' + note, 'error');
                        }
                    });
            });
        }

        $('#pls-apply-target-roi-btn').on('click', function () {
            const rawInput = $('#pls-target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) { showToast('Please enter a Target ROI%', 'error'); return; }
            if (!isFinite(targetRoiPct))             { showToast('Target ROI% must be a number', 'error'); return; }

            const roiMultiplier = 1 + (targetRoiPct / 100);
            plsApplyTargetBackSolve(function (lp, ship) {
                return (lp * roiMultiplier) + ship;
            }, 'Target ROI ' + targetRoiPct + '%');
        });

        $('#pls-apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#pls-target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) { showToast('Please enter a Target GPFT%', 'error'); return; }
            if (!isFinite(targetGpftPct))            { showToast('Target GPFT% must be a number', 'error'); return; }

            const targetFraction = targetGpftPct / 100;
            plsApplyTargetBackSolve(function (lp, ship) {
                const denom = 1 - targetFraction;
                if (denom <= 0) return null; // signals "target ≥ 100%" skip
                return (lp + ship) / denom;
            }, 'Target GPFT ' + targetGpftPct + '%');
        });

        $('#pls-target-roi-input').on('keypress', function (e) {
            if (e.which === 13) $('#pls-apply-target-roi-btn').click();
        });
        $('#pls-target-gpft-input').on('keypress', function (e) {
            if (e.which === 13) $('#pls-apply-target-gpft-btn').click();
        });

        // Clear SPRICE for selected SKUs
        function plsClearSpriceForSelected() {
            if (plsSelectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }
            if (!confirm(`Clear SPRICE for ${plsSelectedSkus.size} selected SKU(s)?`)) return;

            const updates = [];
            table.getRows().forEach(function(row) {
                const rowData = row.getData();
                const sku = rowData.sku;
                if (!sku || !plsSelectedSkus.has(sku)) return;
                if (rowData.parent && String(rowData.parent).toUpperCase().startsWith('PARENT')) return;
                row.update({ sprice: 0, sgpft: 0, sroi: 0, has_custom_sprice: false });
                updates.push({ sku: sku });
            });

            if (updates.length === 0) {
                showToast('No SPRICE values to clear', 'error');
                return;
            }

            $.ajax({
                url: '/pls-clear-sprice',
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                data: JSON.stringify({ updates: updates }),
                success: function(response) {
                    showToast(response.message || `SPRICE cleared for ${updates.length} SKU(s)`, 'success');
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to clear SPRICE';
                    showToast(msg, 'error');
                }
            });
        }

        $('#pls-clear-sprice-selected-btn').on('click', function() { plsClearSpriceForSelected(); });
        $('#pls-clear-sprice-btn').on('click', function() { plsClearSpriceForSelected(); });

        // Suggest Amazon Price - copy Amazon price into SPRICE for selected SKUs (like Macy's)
        function plsApplySuggestAmazonPrice() {
            if (plsSelectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            const eligibleRows = allData.filter(function(row) {
                if (row.parent && String(row.parent).toUpperCase().startsWith('PARENT')) return false;
                if (!plsSelectedSkus.has(row.sku)) return false;
                return (parseFloat(row.amazon_price) || 0) > 0;
            });

            const totalSkus = eligibleRows.length;
            const noAmazonPriceCount = plsSelectedSkus.size - totalSkus;

            if (totalSkus === 0) {
                showToast('No selected SKUs have an Amazon price', 'error');
                return;
            }

            // Apply optimistically and recalc SGPFT% / SROI% locally (server is source of truth on save)
            let updatedCount = 0;
            eligibleRows.forEach(function(row) {
                const sku = row.sku;
                const newPriceNum = parseFloat((parseFloat(row.amazon_price) || 0).toFixed(2));
                const lp = parseFloat(row.lp) || 0;
                const ship = parseFloat(row.ship) || 0;
                const sgpft = newPriceNum > 0 ? Math.round(((newPriceNum - lp - ship) / newPriceNum) * 100 * 100) / 100 : 0;
                const sroi = lp > 0 ? Math.round(((newPriceNum - lp - ship) / lp) * 100 * 100) / 100 : 0;

                const tableRow = table.getRows().find(function(r) { return r.getData().sku === sku; });
                if (tableRow) {
                    tableRow.update({ sprice: newPriceNum, sgpft: sgpft, sroi: sroi, has_custom_sprice: true });
                    tableRow.reformat();
                }

                // Persist in background; failures are logged, not shown as a danger toast
                plsSaveSpriceWithRetry(sku, newPriceNum, tableRow).catch(function(err) {
                    console.error('Failed to save SPRICE for', sku, err);
                });

                updatedCount++;
            });

            let message = `SPRICE set to Amazon price for ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} had no Amazon price)`;
            }
            showToast(message, updatedCount > 0 ? 'success' : 'info');
        }

        $('#pls-sugg-amz-prc-btn').on('click', function() { plsApplySuggestAmazonPrice(); });

    });
</script>
@endsection
