@extends('layouts.vertical', ['title' => 'Temu Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
            position: relative !important;
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 5px;
            font-weight: 700;
            color: #1e293b;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
            min-height: 120px;
            height: auto;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 120px;
            padding: 10px 5px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            line-height: 1.5;
        }

        /* Hide sorting arrows */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-arrow {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter-element {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .status-dot.green {
            background-color: #28a745;
        }

        .status-dot.red {
            background-color: #dc3545;
        }

        .status-dot.yellow {
            background-color: #ffc107;
        }

        #budget-under-table .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: visible;
            border: 1px solid #e5e7eb;
        }
        
        #budget-under-table .tabulator .tabulator-tableHolder {
            overflow-x: auto;
            overflow-y: auto;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 70px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }
        
        #budget-under-table {
            overflow: visible;
        }
        
        #budget-under-table .tabulator-tableHolder {
            overflow-x: auto;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .utilization-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
        }

        body {
            zoom: 90%;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu Utilized',
        'sub_title' => 'Temu Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-4">
                    <div class="mb-4">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Stats Cards Row -->
                                <div class="row g-4 align-items-end mb-3 pb-3 border-bottom">
                                                    <div class="col-md-12">
                                        <label class="form-label fw-semibold mb-2 d-block"
                                            style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 flex-wrap align-items-center">
                                            <div class="badge-count-item"
                                                style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total
                                                    SKU</span>
                                                <span class="fw-bold" id="total-sku-count"
                                                    style="font-size: 1.1rem;">0</span>
                                                    </div>
                                            <div class="badge-count-item total-campaign-card" id="total-campaign-card"
                                                style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Campaign</span>
                                                <span class="fw-bold" id="total-campaign-count"
                                                    style="font-size: 1.1rem;">0</span>
                                                    </div>
                                            <div class="badge-count-item missing-campaign-card" id="missing-campaign-card"
                                                style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Missing</span>
                                                <span class="fw-bold" id="missing-campaign-count"
                                                    style="font-size: 1.1rem;">0</span>
                                                    </div>
                                            <div class="badge-count-item nra-missing-card" id="nra-missing-card"
                                                style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA MISSING</span>
                                                <span class="fw-bold" id="nra-missing-count"
                                                    style="font-size: 1.1rem;">0</span>
                                                </div>
                                            <div class="badge-count-item zero-inv-card" id="zero-inv-card"
                                                style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Zero
                                                    INV</span>
                                                <span class="fw-bold" id="zero-inv-count"
                                                    style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item nra-card" id="nra-card"
                                                style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem;">0</span>
                                                    </div>
                                            <div class="badge-count-item ra-card" id="ra-card"
                                                style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span
                                                    style="font-size: 0.75rem; display: block; margin-bottom: 2px;">RA</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem;">0</span>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row g-2 align-items-end mb-3">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-1"
                                            style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-search me-1" style="color: #64748b;"></i>Search SKU
                                                        </label>
                                        <input type="text" id="global-search" class="form-control form-control-sm"
                                            placeholder="Search..." style="border-color: #e2e8f0;">
                                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-1"
                                            style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>SKU Type
                                        </label>
                                        <select id="sku-type-select" class="form-select form-select-sm">
                                        <option value="all">All</option>
                                            <option value="parent">Parent</option>
                                            <option value="sku" selected>SKU</option>
                                    </select>
                                </div>
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-1"
                                            style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-sm">
                                            <option value="">INV > 0</option>
                                            <option value="ALL">ALL</option>
                                            <option value="INV_0">0 INV</option>
                                    </select>
                                </div>
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-1"
                                            style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRA
                                        </label>
                                        <select id="nra-filter" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="RA">🟢 RA</option>
                                            <option value="NRA">🔴 NRA</option>
                                            <option value="LATER">🟡 LATER</option>
                                    </select>
                                </div>
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold mb-1"
                                            style="color: #475569; font-size: 0.75rem;">
                                            <i class="fa-solid fa-upload me-1" style="color: #64748b;"></i>Upload Campaign
                                            Reports
                                        </label>
                                        <div class="d-flex gap-1 align-items-stretch">
                                            <div style="flex: 1; min-width: 0;">
                                                <input type="file" id="l7-upload-file" accept=".xlsx,.xls,.csv"
                                                    class="form-control form-control-sm"
                                                    style="font-size: 0.7rem; height: 31px;">
                                </div>
                                            <button id="l7-upload-btn" class="btn btn-primary btn-sm" title="L7 Upload"
                                                style="min-width: 50px; padding: 4px 8px; font-size: 0.75rem; height: 31px; white-space: nowrap;">
                                                <i class="fa-solid fa-upload me-1"></i>L7
                                    </button>
                                            <div style="flex: 1; min-width: 0;">
                                                <input type="file" id="l30-upload-file" accept=".xlsx,.xls,.csv"
                                                    class="form-control form-control-sm"
                                                    style="font-size: 0.7rem; height: 31px;">
                                    </div>
                                            <button id="l30-upload-btn" class="btn btn-primary btn-sm" title="L30 Upload"
                                                style="min-width: 55px; padding: 4px 8px; font-size: 0.75rem; height: 31px; white-space: nowrap;">
                                                <i class="fa-solid fa-upload me-1"></i>L30
                                    </button>
                                </div>
                                        <div class="d-flex gap-2" id="upload-status-container" style="display: none;">
                                            <div id="l7-upload-status"
                                                style="font-size: 0.65rem; line-height: 1.2; flex: 1; min-width: 0;"></div>
                                            <div id="l30-upload-status"
                                                style="font-size: 0.65rem; line-height: 1.2; flex: 1; min-width: 0;"></div>
                                </div>
                            </div>
                                    <div class="col-md-1">
                                        <label class="form-label fw-semibold mb-1 d-block"
                                            style="color: transparent; font-size: 0.75rem;">Export</label>
                                        <button id="export-data-btn" class="btn btn-success btn-sm w-100"
                                            style="padding: 4px 8px; font-size: 0.75rem;">
                                            <i class="fa-solid fa-download me-1"></i>Export
                                        </button>
                        </div>
                            </div>
                        </div>
                    </div>
                                            </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                                            </div>
                                        </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentSkuType = 'sku';
            let showNraOnly = false;
            let showRaOnly = false;
            let showZeroInvOnly = false;
            let showCampaignOnly = false;
            let showMissingOnly = false;
            let showNraMissingOnly = false;
            let totalSkuCountFromBackend = 0;
            let zeroInvCountFromBackend = 0;
            let totalCampaignCountFromBackend = 0;

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                const allData = table.getData('all');
                let nraCount = 0;
                let zeroInvCount = 0;
                let validSkuCount = 0;
                let campaignCount = 0;
                let missingCount = 0;
                let nraMissingCount = 0;
                const processedSkusForNra = new Set();
                const processedSkusForCampaign = new Set();
                const processedSkusForMissing = new Set();
                const processedSkusForNraMissing = new Set();

                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const normalizedSku = sku.toUpperCase().trim();
                    const isParentSku = normalizedSku.startsWith('PARENT');
                    const isValidSku = sku && !isParentSku;

                    if (isValidSku) {
                        if (!processedSkusForNra.has(sku)) {
                            processedSkusForNra.add(sku);
                            let rowNra = row.NR ? row.NR.trim() : "";
                            if (rowNra === 'NRA') {
                                nraCount++;
                            }
                        }
                    }

                    let inv = parseFloat(row.INV || 0);
                    if (inv <= 0 && isValidSku) {
                        zeroInvCount++;
                    }

                    if (isValidSku && !processedSkusForNra.has(sku)) {
                        validSkuCount++;
                    }

                    // Campaign and missing counts - BEFORE filters
                    if (isValidSku) {
                        // Check hasCampaign - can be boolean true/false, string "true"/"false", or undefined
                        let hasCampaign = false;
                        if (row.hasCampaign !== undefined && row.hasCampaign !== null) {
                            hasCampaign = row.hasCampaign === true || row.hasCampaign === 'true' || row.hasCampaign === 1 || row.hasCampaign === '1';
                        }
                        const rowNra = row.NR ? row.NR.trim() : "";
                        const inv = parseFloat(row.INV || 0);

                        if (hasCampaign) {
                            // Count campaign only once per SKU
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                campaignCount++;
                            }
                } else {
                            // Count missing only once per SKU
                            // NRA missing (yellow dots) should count if NRA='NRA'
                            if (rowNra === 'NRA') {
                                if (!processedSkusForNraMissing.has(sku)) {
                                    processedSkusForNraMissing.add(sku);
                                    nraMissingCount++;
                                }
                } else {
                                // Count as missing (red dot) if NRA is not 'NRA' AND INV > 0
                                if (!processedSkusForMissing.has(sku)) {
                                    processedSkusForMissing.add(sku);
                                    if (inv > 0) {
                                        missingCount++;
                                    }
                                }
                            }
                        }
                    }
                });

                // Update counts
                const totalSkuCountEl = document.getElementById('total-sku-count');
                if (totalSkuCountEl) {
                    totalSkuCountEl.textContent = totalSkuCountFromBackend > 0 ? totalSkuCountFromBackend :
                        validSkuCount;
                }

                const nraCountEl = document.getElementById('nra-count');
                if (nraCountEl) {
                    nraCountEl.textContent = nraCount;
                }

                const raCountEl = document.getElementById('ra-count');
                if (raCountEl) {
                    var totalCount = totalSkuCountFromBackend > 0 ? totalSkuCountFromBackend : validSkuCount;
                    var calculatedRaCount = totalCount - nraCount;
                    calculatedRaCount = calculatedRaCount >= 0 ? calculatedRaCount : 0;
                    raCountEl.textContent = calculatedRaCount;
                }

                const zeroInvCountEl = document.getElementById('zero-inv-count');
                if (zeroInvCountEl) {
                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal === "INV_0") {
                        // When INV_0 filter is selected, count from active/filtered data to match pagination
                        let activeData = table.getData('active');
                        let filteredZeroInvCount = 0;
                        activeData.forEach(function(row) {
                            let inv = parseFloat(row.INV || 0);
                            if (inv <= 0) {
                                filteredZeroInvCount++;
                            }
                        });
                        zeroInvCountEl.textContent = filteredZeroInvCount;
                    } else {
                        // When INV_0 filter is not selected, use backend count
                        zeroInvCountEl.textContent = zeroInvCountFromBackend > 0 ? zeroInvCountFromBackend :
                            zeroInvCount;
                    }
                }

                // Update campaign counts - use backend count if available (unique goods_id), otherwise use calculated count
                const campaignCountEl = document.getElementById('total-campaign-count');
                if (campaignCountEl) {
                    if (totalCampaignCountFromBackend > 0) {
                        campaignCountEl.textContent = totalCampaignCountFromBackend;
                    } else {
                        campaignCountEl.textContent = campaignCount;
                    }
                }

                const missingCountEl = document.getElementById('missing-campaign-count');
                if (missingCountEl) {
                    missingCountEl.textContent = missingCount;
                }

                const nraMissingCountEl = document.getElementById('nra-missing-count');
                if (nraMissingCountEl) {
                    nraMissingCountEl.textContent = nraMissingCount;
                }
            }

            // Combined filter function
            const combinedFilter = (data) => {
                const sku = (data.sku || '').toUpperCase();
                const isParent = sku.includes('PARENT');
                const inv = parseFloat(data.INV || 0);
                const nra = (data.NR || '').trim();
                const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : false;

                // SKU type filter
                if (currentSkuType === 'parent' && !isParent) return false;
                if (currentSkuType === 'sku' && isParent) return false;

                // Search filter
                const searchTerm = (document.getElementById('global-search').value || '').toUpperCase();
                if (searchTerm && !sku.includes(searchTerm)) return false;

                // Card filters (check first to override dropdown filters)
                if (showZeroInvOnly && inv > 0) return false;

                // Campaign filters
                if (showCampaignOnly && !hasCampaign) return false;
                if (showMissingOnly && (hasCampaign || nra === 'NRA')) return false;
                if (showNraMissingOnly && nra !== 'NRA') return false;

                // INV filter
                const invFilter = document.getElementById('inv-filter').value;
                if (invFilter === 'INV_0') {
                    // Show only 0 INV items
                    if (inv > 0) return false;
                } else if (invFilter === '' || invFilter === null || invFilter === undefined) {
                    // INV > 0 selected (default) - filter out 0 and negative inventory
                    // But don't filter if showZeroInvOnly is true (already handled above)
                    if (inv <= 0 && !showZeroInvOnly) return false;
                } else if (invFilter === 'ALL') {
                    // ALL option: show everything, no filtering
                    // Do nothing - allow all rows through
                }

                // NRA filter
                const nraFilter = document.getElementById('nra-filter').value;
                if (nraFilter && nra !== nraFilter) return false;

                // Card filters
                if (showNraOnly && nra !== 'NRA') return false;
                if (showRaOnly && nra !== 'RA') return false;

                return true;
            };

            // Event listeners
            document.getElementById('sku-type-select').addEventListener('change', function() {
                currentSkuType = this.value;
                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            document.getElementById('global-search').addEventListener('input', function() {
                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            document.getElementById('inv-filter').addEventListener('change', function() {
                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            document.getElementById('nra-filter').addEventListener('change', function() {
                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Export functionality
            document.getElementById('export-data-btn').addEventListener('click', function() {
                if (typeof table !== 'undefined' && table) {
                    table.download("csv", "temu-utilized-data.csv");
                }
            });

            // NRA card click handler
            document.getElementById('nra-card').addEventListener('click', function() {
                showNraOnly = !showNraOnly;
                if (showNraOnly) {
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                    } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // RA card click handler
            document.getElementById('ra-card').addEventListener('click', function() {
                showRaOnly = !showRaOnly;
                if (showRaOnly) {
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(34, 197, 94, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Zero INV card click handler
            document.getElementById('zero-inv-card').addEventListener('click', function() {
                showZeroInvOnly = !showZeroInvOnly;
                if (showZeroInvOnly) {
                    // Change inventory filter to ALL to show zero INV items
                    document.getElementById('inv-filter').value = 'ALL';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.5)';
                } else {
                    this.style.boxShadow = '';
                    // Reset inventory filter back to default when disabling filter
                    document.getElementById('inv-filter').value = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
                showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(6, 182, 212, 0.5)';
                    } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Missing campaign card click handler
            document.getElementById('missing-campaign-card').addEventListener('click', function() {
                showMissingOnly = !showMissingOnly;
                if (showMissingOnly) {
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.5)';
                    } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // NRA missing card click handler
            document.getElementById('nra-missing-card').addEventListener('click', function() {
                showNraMissingOnly = !showNraMissingOnly;
                if (showNraMissingOnly) {
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }

                if (typeof table !== 'undefined' && table) {
                    table.clearFilter();
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // NRA and Status update handler
            $(document).on('change', '.editable-select', function() {
                const sku = $(this).data('sku');
                const field = $(this).data('field');
                const value = $(this).val();

                // Update select color for status field
                if (field === 'status') {
                    const statusColors = {
                        "Active": "#10b981",
                        "Inactive": "#ef4444",
                        "Not Created": "#eab308"
                    };
                    const selectedColor = statusColors[value] || "#6b7280";
                    $(this).css('color', selectedColor);
                }

                // Store old value for revert
                const row = table.getRow(sku);
                let oldValue = null;
                if (row) {
                    const rowData = row.getData();
                    oldValue = rowData[field];
                    rowData[field] = value;
                    row.update(rowData);
                }

                // Save to backend
                $.ajax({
                    url: '{{ route("temu.ads.update") }}',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            updateButtonCounts();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating ' + field + ':', error);
                        // Revert the change on error
                        if (row && oldValue !== null) {
                            const rowData = row.getData();
                            rowData[field] = oldValue;
                            row.update(rowData);
                            // Also revert the select element
                            $(this).val(oldValue);
                            // Revert color for status field
                            if (field === 'status') {
                                const statusColors = {
                                    "Active": "#10b981",
                                    "Inactive": "#ef4444",
                                    "Not Created": "#eab308"
                                };
                                const oldColor = statusColors[oldValue] || "#6b7280";
                                $(this).css('color', oldColor);
                            }
                        }
                    }.bind(this)
                });
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/temu/ads/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",
                virtualDom: true,
                pagination: true,
                paginationMode: "local",
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100, 200, 500],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    var endRow = Math.min(currentRow + pageSize - 1, totalRows);
                    return "Showing " + currentRow + " to " + endRow + " of " + totalRows;
                },
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';
                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                ajaxResponse: function(url, params, response) {
                    if (response && response.data) {
                        // Get total SKU count from backend (excluding PARENT SKUs)
                        totalSkuCountFromBackend = parseInt(response.total_sku_count || 0);
                        // Get zero INV count from backend
                        zeroInvCountFromBackend = parseInt(response.zero_inv_count || 0);
                        // Get total campaign count from backend (unique goods_id)
                        totalCampaignCountFromBackend = parseInt(response.total_campaign_count || 0);
                        // Return the data array for Tabulator
                        return response.data;
                    }
                    return [];
                },
                columns: [                    {
                        title: "SKU",
                        field: "sku",
                        headerSort: false,
                        width: 120
                    },
                    {
                        title: "Missing",
                        field: "hasCampaign",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : false;
                            const nraValue = row.NR ? row.NR.trim() : "";
                            let dotColor, title;

                            if (nraValue === 'NRA') {
                                dotColor = 'yellow';
                                title = 'NRA - Not Required';
                    } else {
                                dotColor = hasCampaign ? 'green' : 'red';
                                title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            }

                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${title}"></span>
                                </div>
                            `;
                        },
                        visible: true,
                        width: 80
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: true,
                        width: 80,
                        hozAlign: "right"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: true,
                        width: 80,
                        hozAlign: "right"
                    },
                    {
                        title: "TEMU L30",
                        field: "temu_l30",
                        visible: true,
                        width: 80,
                        hozAlign: "right"
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30 || 0);
                            const inv = parseFloat(data.INV || 0);
                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const percent = dilDecimal * 100;
                                let textColor = '#000000';

                                if (percent < 16.66) {
                                    textColor = '#dc3545';
                                } else if (percent >= 16.66 && percent < 25) {
                                    textColor = '#b8860b';
                                } else if (percent >= 25 && percent < 50) {
                                    textColor = '#28a745';
                                } else {
                                    textColor = '#e83e8c';
                                }

                                return `<div class="text-center"><span style="color: ${textColor}; font-weight: bold;">${Math.round(percent)}%</span></div>`;
                            }
                            return `<div class="text-center"><span style="color: #dc3545; font-weight: bold;">0%</span></div>`;
                        },
                        visible: true,
                        width: 80
                    },
                    {
                        title: "NRA",
                        field: "NR",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const rowData = row.getData();
                            // Default to RA if no value
                            const defaultValue = "RA";
                            const value = (cell.getValue()?.trim()) || defaultValue;

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NR"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: true,
                        width: 70
                    },
                    {
                        title: "Spend",
                        field: "spend_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                <span>${value.toFixed(2)}</span>
                                <i class="fa-solid fa-info-circle toggle-spend-l7-btn" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Toggle L7 Spend"></i>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('toggle-spend-l7-btn') || e.target
                                .closest('.toggle-spend-l7-btn')) {
                                e.stopPropagation();
                                const isVisible = table.getColumn('spend_l7').isVisible();
                                if (isVisible) {
                                    table.hideColumn('spend_l7');
                                } else {
                                    table.showColumn('spend_l7');
                                }
                            }
                        },
                        visible: true,
                        width: 100
                    },
                    {
                        title: "Spend L7",
                        field: "spend_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        },
                        visible: false,
                        width: 100
                    },
                    {
                        title: "Ad Clicks",
                        field: "clicks_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseInt(cell.getValue() || 0);
                            return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                <span>${value.toLocaleString()}</span>
                                <i class="fa-solid fa-info-circle toggle-clicks-l7-btn" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Toggle L7 Ad Clicks"></i>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('toggle-clicks-l7-btn') || e.target
                                .closest('.toggle-clicks-l7-btn')) {
                                e.stopPropagation();
                                const isVisible = table.getColumn('clicks_l7').isVisible();
                                if (isVisible) {
                                    table.hideColumn('clicks_l7');
                                } else {
                                    table.showColumn('clicks_l7');
                                }
                            }
                        },
                        visible: true,
                        width: 110
                    },
                    {
                        title: "Ad Clicks L7",
                        field: "clicks_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        visible: false,
                        width: 110
                    },
                    {
                        title: "ACOS%",
                        field: "acos_l30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                <span>${Math.round(value)}%</span>
                                <i class="fa-solid fa-info-circle toggle-acos-l7-btn" style="cursor: pointer; font-size: 12px; color: #3b82f6;" title="Toggle L7 ACOS%"></i>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('toggle-acos-l7-btn') || e.target
                                .closest('.toggle-acos-l7-btn')) {
                                e.stopPropagation();
                                const isVisible = table.getColumn('acos_l7').isVisible();
                                if (isVisible) {
                                    table.hideColumn('acos_l7');
                                } else {
                                    table.showColumn('acos_l7');
                                }
                            }
                        },
                        visible: true,
                        width: 100
                    },
                    {
                        title: "ACOS% L7",
                        field: "acos_l7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2) + '%';
                        },
                        visible: false,
                        width: 100
                    },
                    {
                        title: "S ROAS",
                        field: "roas_l30",
                        hozAlign: "right",
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        editable: function(cell) {
                            // Check if icon was clicked
                            return !window.iconClicked;
                        },
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const cellElement = cell.getElement();
                            
                            // Set up event listeners on the icon
                            if (cellElement) {
                                setTimeout(function() {
                                    const icon = cellElement.querySelector('.toggle-roas-l7-btn');
                                    if (icon) {
                                        // Remove old listeners
                                        $(icon).off('mousedown click');
                                        
                                        // Prevent editor on mousedown
                                        $(icon).on('mousedown', function(e) {
                                            window.iconClicked = true;
                                            e.stopPropagation();
                                            e.preventDefault();
                                            e.stopImmediatePropagation();
                                            setTimeout(function() {
                                                window.iconClicked = false;
                    }, 100);
                                            return false;
                                        });
                                        
                                        // Toggle column on click
                                        $(icon).on('click', function(e) {
                                            e.stopPropagation();
                        e.preventDefault();
                                            e.stopImmediatePropagation();
                                            const isVisible = table.getColumn('roas_l7').isVisible();
                                            if (isVisible) {
                                                table.hideColumn('roas_l7');
                } else {
                                                table.showColumn('roas_l7');
                                            }
                                            return false;
                                        });
                                    }
                                }, 0);
                            }
                            
                            return `<div style="display: flex; align-items: center; justify-content: flex-end; gap: 5px;">
                                <span>${value.toFixed(2)}</span>
                                <i class="fa-solid fa-info-circle toggle-roas-l7-btn" data-field="roas_l7" style="cursor: pointer; font-size: 12px; color: #3b82f6; pointer-events: auto; z-index: 10; position: relative;" title="Toggle L7 S ROAS"></i>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            // Check if the click is on the info icon
                            if (e.target.classList.contains('toggle-roas-l7-btn') || 
                                e.target.classList.contains('fa-info-circle') ||
                                e.target.closest('.toggle-roas-l7-btn')) {
                    e.stopPropagation();
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                const isVisible = table.getColumn('roas_l7').isVisible();
                                if (isVisible) {
                                    table.hideColumn('roas_l7');
                    } else {
                                    table.showColumn('roas_l7');
                                }
                                return false;
                            }
                        },
                        visible: true,
                        width: 100
                    },
                    {
                        title: "S ROAS L7",
                        field: "roas_l7",
                        hozAlign: "right",
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(2);
                        },
                        visible: false,
                        width: 100
                    },
                    {
                        title: "Status",
                        field: "status",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const rowData = row.getData();
                            // Default to "Not Created" if no value
                            const defaultValue = "Not Created";
                            const value = (cell.getValue()?.trim()) || defaultValue;
                            
                            // Set color based on selected value
                            const statusColors = {
                                "Active": "#10b981",
                                "Inactive": "#ef4444",
                                "Not Created": "#eab308"
                            };
                            const selectedColor = statusColors[value] || "#6b7280";

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="status"
                                        style="width: 120px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: 0.875rem; color: ${selectedColor}; font-weight: 500;">
                                    <option value="Active" ${value === 'Active' ? 'selected' : ''} style="color: #10b981; font-weight: 500;">Active</option>
                                    <option value="Inactive" ${value === 'Inactive' ? 'selected' : ''} style="color: #ef4444; font-weight: 500;">Inactive</option>
                                    <option value="Not Created" ${value === 'Not Created' ? 'selected' : ''} style="color: #eab308; font-weight: 500;">Not Created</option>
                                </select>
                            `;
                        },
                        visible: true,
                        width: 130
                    }
                ],
                initialSort: [{
                    column: "sku",
                    dir: "asc"
                }]
            });

            // Update counts and apply default filter after table loads
            table.on("dataLoaded", function() {
                // Apply default filter (hide parent SKUs by default)
                table.setFilter(combinedFilter);
                updateButtonCounts();
            });

            // Update counts when data is loaded/processed
            table.on("dataLoaded", function() {
                updateButtonCounts();
            });

            table.on("dataProcessed", function() {
                updateButtonCounts();
            });

            // Initialize iconClicked flag
            window.iconClicked = false;

            // ROAS L30, L7 and Status update handler
            table.on("cellEdited", function(cell) {
                const field = cell.getField();
                if (field === 'roas_l30' || field === 'roas_l7' || field === 'status') {
                    const row = cell.getRow();
                    const rowData = row.getData();
                    const sku = rowData.sku;
                    let value = cell.getValue();

                    // Parse numeric value for ROAS fields
                    if (field === 'roas_l30' || field === 'roas_l7') {
                        value = parseFloat(value || 0);
                    }

                    // Save to backend
                    $.ajax({
                        url: '{{ route("temu.ads.update") }}',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: value
                        }),
                        success: function(response) {
                            if (response.success) {
                                // Value already updated in cell, just refresh display
                                cell.setValue(value);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error updating ' + field + ':', error);
                            // Revert the change on error
                            const oldValue = field === 'status' ? (rowData[field] || 'Not Created') : parseFloat(rowData[field] || 0);
                            cell.setValue(oldValue);
                            alert('Error updating ' + field + ': ' + (xhr.responseJSON?.message || error));
                        }
                    });
                }
            });

            // L7 Upload handler
            document.getElementById('l7-upload-btn').addEventListener('click', function() {
                const fileInput = document.getElementById('l7-upload-file');
                const file = fileInput.files[0];
                const statusDiv = document.getElementById('l7-upload-status');
                const statusContainer = document.getElementById('upload-status-container');

                if (!file) {
                    statusDiv.innerHTML = '<span style="color: red;">Please select a file</span>';
                    statusContainer.style.display = 'flex';
                        return;
                    }

                const formData = new FormData();
                formData.append('file', file);
                formData.append('report_range', 'L7');

                statusDiv.innerHTML = '<span style="color: blue;">Uploading...</span>';
                statusContainer.style.display = 'flex';
                this.disabled = true;

                $.ajax({
                    url: '/temu/ads/upload-campaign-report',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.innerHTML = '<span style="color: green;">' + response
                                .message + '</span>';
                            fileInput.value = '';
                            statusContainer.style.display = 'flex';
                            // Reload table data
                            if (typeof table !== 'undefined' && table) {
                                table.replaceData();
                            }
                            // Hide status after 5 seconds
                            setTimeout(function() {
                                statusDiv.innerHTML = '';
                                if (document.getElementById('l30-upload-status').innerHTML === '') {
                                    statusContainer.style.display = 'none';
                                    statusContainer.style.marginTop = '0';
                                }
                            }, 5000);
                    } else {
                            statusDiv.innerHTML = '<span style="color: red;">' + (response
                                .message || 'Upload failed') + '</span>';
                            statusContainer.style.display = 'flex';
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || 'Upload failed';
                        statusDiv.innerHTML = '<span style="color: red;">' + errorMsg +
                            '</span>';
                        statusContainer.style.display = 'flex';
                    },
                    complete: function() {
                        document.getElementById('l7-upload-btn').disabled = false;
                    }
                });
            });

            // L30 Upload handler
            document.getElementById('l30-upload-btn').addEventListener('click', function() {
                const fileInput = document.getElementById('l30-upload-file');
                const file = fileInput.files[0];
                const statusDiv = document.getElementById('l30-upload-status');
                const statusContainer = document.getElementById('upload-status-container');

                if (!file) {
                    statusDiv.innerHTML = '<span style="color: red;">Please select a file</span>';
                    statusContainer.style.display = 'flex';
                        return;
                    }

                const formData = new FormData();
                formData.append('file', file);
                formData.append('report_range', 'L30');

                statusDiv.innerHTML = '<span style="color: blue;">Uploading...</span>';
                statusContainer.style.display = 'flex';
                this.disabled = true;

                $.ajax({
                    url: '/temu/ads/upload-campaign-report',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.innerHTML = '<span style="color: green;">' + response
                                .message + '</span>';
                            fileInput.value = '';
                            statusContainer.style.display = 'flex';
                            // Reload table data
                            if (typeof table !== 'undefined' && table) {
                                table.replaceData();
                            }
                            // Hide status after 5 seconds
                            setTimeout(function() {
                                statusDiv.innerHTML = '';
                                if (document.getElementById('l7-upload-status').innerHTML === '') {
                                    statusContainer.style.display = 'none';
                                    statusContainer.style.marginTop = '0';
                                }
                            }, 5000);
                                                } else {
                            statusDiv.innerHTML = '<span style="color: red;">' + (response
                                .message || 'Upload failed') + '</span>';
                            statusContainer.style.display = 'flex';
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || 'Upload failed';
                        statusDiv.innerHTML = '<span style="color: red;">' + errorMsg +
                            '</span>';
                        statusContainer.style.display = 'flex';
                    },
                    complete: function() {
                        document.getElementById('l30-upload-btn').disabled = false;
                    }
                });
            });
        });
    </script>
@endsection
