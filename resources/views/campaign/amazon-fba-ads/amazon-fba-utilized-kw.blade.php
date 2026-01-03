@extends('layouts.vertical', ['title' => 'Amazon FBA KW - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        #budget-under-table .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
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
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {
            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
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

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
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

        .status-dot.gray {
            background-color: #6c757d;
        }

        .utilization-type-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .utilization-type-btn.active {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        .utilization-type-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
        'page_title' => 'Amazon FBA KW - Utilized',
        'sub_title' => 'Amazon FBA KW - Utilized',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" style="border: 1px solid rgba(0, 0, 0, 0.05);">
                <div class="card-body py-4">
                    <div class="mb-4">
                        <!-- Filters and Stats Section -->
                        <div class="card border-0 shadow-sm mb-4" style="border: 1px solid rgba(0, 0, 0, 0.05) !important;">
                            <div class="card-body p-4">
                                <!-- Type Filter and Count Cards Row -->
                                <div class="row g-4 align-items-end mb-3 pb-3 border-bottom">
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>Utilization Type
                                        </label>
                                        <select id="utilization-type-select" class="form-select form-select-md">
                                            <option value="all" selected>All</option>
                                            <option value="over">Over Utilized</option>
                                            <option value="under">Under Utilized</option>
                                            <option value="correctly">Correctly Utilized</option>
                                        </select>
                                </div>
                                    <div class="col-md-2"></div>
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold mb-2 d-block" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 flex-wrap align-items-center">
                                            <div class="badge-count-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total SKU</span>
                                                <span class="fw-bold" id="total-sku-count" style="font-size: 1.1rem;">0</span>
                            </div>
                                            <div class="badge-count-item total-campaign-card" id="total-campaign-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Campaign</span>
                                                <span class="fw-bold" id="total-campaign-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item missing-campaign-card" id="missing-campaign-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Missing</span>
                                                <span class="fw-bold" id="missing-campaign-count" style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item nra-missing-card" id="nra-missing-card" style="background: linear-gradient(135deg, #ffc107 0%, #ffb300 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA MISSING</span>
                                                <span class="fw-bold" id="nra-missing-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item zero-inv-card" id="zero-inv-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Zero INV</span>
                                                <span class="fw-bold" id="zero-inv-count" style="font-size: 1.1rem;">0</span>
                                        </div>
                                            <div class="badge-count-item nra-card" id="nra-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">NRA</span>
                                                <span class="fw-bold" id="nra-count" style="font-size: 1.1rem;">0</span>
                                    </div>
                                            <div class="badge-count-item ra-card" id="ra-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">RA</span>
                                                <span class="fw-bold" id="ra-count" style="font-size: 1.1rem;">0</span>
                                </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub" style="background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB</span>
                                                <span class="fw-bold" id="7ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub-1ub" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB + 1UB</span>
                                                <span class="fw-bold" id="7ub-1ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                        </div>
                            </div>
                        </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row align-items-end">
                            <div class="col-md-4">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-search me-1" style="color: #64748b;"></i>Search Campaign
                                        </label>
                                <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0" style="border-color: #e2e8f0;">
                                                <i class="fa-solid fa-search" style="color: #94a3b8;"></i>
                                    </span>
                                            <input type="text" id="global-search" class="form-control form-control-md border-start-0" 
                                                   placeholder="Search by campaign name or SKU..."
                                                   style="border-color: #e2e8f0;">
                                </div>
                            </div>
                            <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-toggle-on me-1" style="color: #64748b;"></i>Status
                                        </label>
                                <select id="status-filter" class="form-select form-select-md">
                                    <option value="">All Status</option>
                                            <option value="ENABLED">Enabled</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="ENDED">Ended</option>
                                </select>
                            </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                <select id="inv-filter" class="form-select form-select-md">
                                    <option value="">All Inventory</option>
                                            <option value="ALL" selected>ALL</option>
                                    <option value="INV_0">0 INV</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                            </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-tags me-1" style="color: #64748b;"></i>NRA
                                        </label>
                                <select id="nra-filter" class="form-select form-select-md">
                                    <option value="">All NRA</option>
                                    <option value="NRA">NRA</option>
                                    <option value="RA">RA</option>
                                    <option value="LATER">LATER</option>
                                </select>
                            </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-filter me-1" style="color: #64748b;"></i>ACOS Filter
                                        </label>
                                        <select id="sbgt-filter" class="form-select form-select-md">
                                            <option value="">All ACOS</option>
                                            <option value="3">ACOS &lt; 10%</option>
                                            <option value="2">ACOS 10-19%</option>
                                            <option value="1">ACOS ≥ 20%</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex gap-2">
                                        <div class="w-50">
                                            <button id="apr-all-sbid-btn" class="btn btn-info btn-sm w-100 d-none">
                                                <i class="fa-solid fa-check-double me-1"></i>
                                                APR ALL SBID
                                            </button>
                                        </div>
                                        <div class="w-50">
                                            <button id="apr-all-sbgt-btn" class="btn btn-warning btn-sm w-100 d-none">
                                                <i class="fa-solid fa-check-double me-1"></i>
                                                APR ALL SBGT
                                            </button>
                                        </div>
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

    <!-- Chart Modal -->
    <div class="modal fade" id="utilizationChartModal" tabindex="-1" aria-labelledby="utilizationChartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="utilizationChartModalLabel">
                        <i class="fa-solid fa-chart-line me-2"></i>
                        <span id="chart-title">Utilization Trend</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <canvas id="utilizationChart" height="80"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let currentUtilizationType = 'all'; // Default to all
            let showMissingOnly = false; // Filter for missing campaigns only
            let showNraMissingOnly = false; // Filter for NRA missing (yellow dots) only
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update all statistics counts from table data
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }
                
                // Get all data and apply filters (except utilization type filter)
                const allData = table.getData('all');
                
                // Use Map to count unique campaigns by campaign_id (matches command logic)
                const campaignMap = new Map();
                let overCount = 0;
                let underCount = 0;
                let correctlyCount = 0;
                let missingCount = 0;
                let nraMissingCount = 0; // Count NRA missing (yellow dots)
                let zeroInvCount = 0; // Count zero and negative inventory
                let totalCampaignCount = 0; // Count total campaigns
                let nraCount = 0; // Count NRA
                let raCount = 0; // Count RA
                let validSkuCount = 0; // Count only valid SKUs (not parent, not empty)
                let totalAllSkuCount = 0; // Count ALL FBA SKUs (no filters applied)
                
                // Track processed SKUs to avoid counting duplicates
                const processedSkusForNra = new Set();
                const processedSkusForCampaign = new Set();
                const processedSkusForMissing = new Set();
                const processedSkusForNraMissing = new Set();
                const processedSkusForZeroInv = new Set();
                const processedSkusForValidCount = new Set();
                const processedSkusForTotalCount = new Set(); // For total SKU count (all FBA SKUs)
                
                // First pass: Count ALL FBA SKUs (no filters)
                allData.forEach(function(row) {
                    const sku = row.sku || '';
                    const isValidSku = sku && !sku.toUpperCase().includes('PARENT');
                    
                    if (isValidSku && !processedSkusForTotalCount.has(sku)) {
                        processedSkusForTotalCount.add(sku);
                        totalAllSkuCount++;
                    }
                });
                
                // Second pass: Apply filters for other counts
                allData.forEach(function(row) {
                    // Count valid SKUs (exclude parent SKUs and empty SKUs)
                    const sku = row.sku || '';
                    const isValidSku = sku && !sku.toUpperCase().includes('PARENT');
                    
                    let inv = parseFloat(row.INV || 0);
                    
                    // Apply all filters except utilization type filter
                    // Global search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(row.campaignName?.toLowerCase().includes(searchVal)) && !(row.sku?.toLowerCase().includes(searchVal))) {
                        return;
                    }
                    
                    // Status filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && row.campaignStatus !== statusVal) {
                        return;
                    }
                    
                    // Count zero/negative inventory (INV <= 0) AFTER search and status filters
                    if (inv <= 0 && isValidSku && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }
                    
                    // Inventory filter - Default to ALL (show all SKUs)
                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal || invFilterVal === '' || invFilterVal === "ALL") {
                        // ALL option shows everything (default)
                    } else if (invFilterVal === "INV_0") {
                        // Show only INV = 0
                        if (inv !== 0) return;
                    } else if (invFilterVal === "OTHERS") {
                        // Show only INV > 0
                    if (inv <= 0) return;
                    }
                    
                    // Count NRA and RA only for valid SKUs and only once per SKU (after filters)
                    if (isValidSku && !processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (rowNra === 'NRA') {
                            nraCount++;
                        } else {
                            // If NRA is empty, null, or "RA", it shows as "RA" by default
                            raCount++;
                        }
                    }
                    
                    // NRA filter
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowNra = row.NRA ? row.NRA.trim() : "";
                        if (nraFilterVal === 'RA') {
                            // For "RA" filter, include empty/null values too
                    if (rowNra === 'NRA') return;
                        } else {
                            // For "NRA" or "LATER", exact match
                            if (rowNra !== nraFilterVal) return;
                        }
                    }
                    
                    // Check if campaign is missing or exists - only count unique valid SKUs (after filters)
                    if (isValidSku) {
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                        
                        if (hasCampaign) {
                            // Count campaign only once per SKU
                            if (!processedSkusForCampaign.has(sku)) {
                                processedSkusForCampaign.add(sku);
                                totalCampaignCount++;
                            }
                        } else {
                            // Count missing only once per SKU
                            if (!processedSkusForMissing.has(sku)) {
                                processedSkusForMissing.add(sku);
                                // Check if this is a red dot (missing AND not yellow)
                                let rowNrlForMissing = row.NRL ? row.NRL.trim() : "";
                                let rowNraForMissing = row.NRA ? row.NRA.trim() : "";
                                // Only count as missing (red dot) if neither NRL='NRL' nor NRA='NRA'
                                if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') {
                                    missingCount++;
                                } else {
                                    // Count NRA missing (yellow dots) separately
                                    if (!processedSkusForNraMissing.has(sku)) {
                                        processedSkusForNraMissing.add(sku);
                                        nraMissingCount++;
                                    }
                                }
                            }
                        }
                        
                        // Count valid SKUs that pass ALL filters (after inventory and NRA filters) - only once per SKU
                        if (!processedSkusForValidCount.has(sku)) {
                            processedSkusForValidCount.add(sku);
                            validSkuCount++;
                        }
                    }
                    
                    // Now calculate utilization and count for ALL SKUs (including those without campaigns)
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                    // Count by SKU (not just campaigns) for utilization type
                    // Use SKU as key to count unique SKUs - include all valid SKUs
                    let skuKey = sku || '';
                    if (isValidSku && skuKey && !campaignMap.has(skuKey)) {
                        campaignMap.set(skuKey, {
                            ub7: ub7,
                            ub1: ub1
                        });
                    }
                });
                
                // Now count unique SKUs by utilization type
                campaignMap.forEach(function(campaignData) {
                    let ub7 = campaignData.ub7;
                    let ub1 = campaignData.ub1;
                    
                    // 7UB + 1UB condition categorization (matches command)
                    if (ub7 > 90 && ub1 > 90) {
                        overCount++;
                    } else if (ub7 < 70 && ub1 < 70) {
                        underCount++;
                    } else if (ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90) {
                        correctlyCount++;
                    }
                });

                // Update all statistics counts
                // Total SKU count is set from backend response (total_fba_sku_count)
                // Don't override it here as it should show all FBA SKUs from database
                
                const missingCountEl = document.getElementById('missing-campaign-count');
                if (missingCountEl) {
                    missingCountEl.textContent = missingCount;
                }
                
                const nraMissingCountEl = document.getElementById('nra-missing-count');
                if (nraMissingCountEl) {
                    nraMissingCountEl.textContent = nraMissingCount;
                }
                
                const totalCampaignCountEl = document.getElementById('total-campaign-count');
                if (totalCampaignCountEl) {
                    totalCampaignCountEl.textContent = totalCampaignCount;
                }
                
                const nraCountEl = document.getElementById('nra-count');
                if (nraCountEl) {
                    nraCountEl.textContent = nraCount;
                }
                
                const raCountEl = document.getElementById('ra-count');
                if (raCountEl) {
                    raCountEl.textContent = raCount;
                }
                
                const zeroInvCountEl = document.getElementById('zero-inv-count');
                if (zeroInvCountEl) {
                    zeroInvCountEl.textContent = zeroInvCount;
                }
                
                // Update dropdown option texts with counts
                const utilizationSelect = document.getElementById('utilization-type-select');
                if (utilizationSelect) {
                    // Get total SKU count from backend (stored in DOM element)
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    const totalSkuCount = totalSkuCountEl ? parseInt(totalSkuCountEl.textContent) || 0 : 0;
                    // Use total SKU count for "All" option, not just utilization counts
                    utilizationSelect.options[0].text = `All (${totalSkuCount})`;
                    utilizationSelect.options[1].text = `Over Utilized (${overCount})`;
                    utilizationSelect.options[2].text = `Under Utilized (${underCount})`;
                    utilizationSelect.options[3].text = `Correctly Utilized (${correctlyCount})`;
                }
            }

            // Utilization type dropdown handler
            document.getElementById('utilization-type-select').addEventListener('change', function() {
                currentUtilizationType = this.value;
                // Reset all card filters when dropdown changes
                showMissingOnly = false;
                document.getElementById('missing-campaign-card').style.boxShadow = '';
                showNraMissingOnly = false;
                document.getElementById('nra-missing-card').style.boxShadow = '';
                showCampaignOnly = false;
                document.getElementById('total-campaign-card').style.boxShadow = '';
                showZeroInvOnly = false;
                document.getElementById('zero-inv-card').style.boxShadow = '';
                showNraOnly = false;
                document.getElementById('nra-card').style.boxShadow = '';
                showRaOnly = false;
                document.getElementById('ra-card').style.boxShadow = '';
                    
                    if (typeof table !== 'undefined' && table) {
                    // Always hide APR BID column
                        table.hideColumn('apr_bid');
                    // Update column visibility for SBID only
                    if (currentUtilizationType === 'correctly' || currentUtilizationType === 'all') {
                        table.hideColumn('sbid');
                    } else {
                            table.showColumn('sbid');
                        }
                    // Set filter and redraw to update formatter colors and column visibility
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                        // Update all button counts after filter is applied
                        setTimeout(function() {
                            updateButtonCounts();
                        }, 200);
                    }
            });

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/fba/utilized/kw/ads/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["sku"] || '';
                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                ajaxResponse: function(url, params, response) {
                    // Store total FBA SKU count from backend
                    if (response && response.total_fba_sku_count !== undefined) {
                        const totalSkuCountEl = document.getElementById('total-sku-count');
                        if (totalSkuCountEl) {
                            totalSkuCountEl.textContent = response.total_fba_sku_count;
                        }
                    }
                    // Return data array for Tabulator
                    return response.data || response;
                },
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "SKU",
                        field: "sku"
                    },
                    {
                        title: "Missing",
                        field: "hasCampaign",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            // Check if campaign exists: hasCampaign field or if campaign_id/campaignName exists
                            const hasCampaign = row.hasCampaign !== undefined 
                                ? row.hasCampaign 
                                : (row.campaign_id && row.campaignName);
                            
                            // Check if NRL is "NRL" (red dot) OR NRA is "NRA" - if so, show yellow dot
                            const nrlValue = row.NRL ? row.NRL.trim() : "";
                            const nraValue = row.NRA ? row.NRA.trim() : "";
                            let dotColor, title;
                            
                            if (nrlValue === 'NRL' || nraValue === 'NRA') {
                                dotColor = 'yellow';
                                title = 'NRL or NRA - Not Required';
                            } else {
                                dotColor = hasCampaign ? 'green' : 'red';
                                title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            }
                            
                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${title}"></span>
                                </div>
                            `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        sorter: "number",
                        formatter: function(cell) {
                            let inv = cell.getValue();
                            return `
                                <span>${inv}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-inv="${inv}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        },
                        visible: true
                    },
                    {
                        title: "L30 FBA",
                        field: "L30",
                        sorter: "number",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        sorter: "number",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);
                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        visible: false,
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const rowData = row.getData();
                            // If NRL is 'NRL' (red dot), default to NRA, otherwise default to RA
                            const nrlValue = rowData.NRL || "REQ";
                            const defaultValue = (nrlValue === 'NRL') ? "NRA" : "RA";
                            const value = (cell.getValue()?.trim()) || defaultValue;

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                </select>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "SBGT",
                        field: "sbgt",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            // Get row data
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            
                            // Calculate SBGT for row A (FBA rules: acos < 10% -> 3, acos < 20% -> 2, acos >= 20% -> 1)
                            var aAcos = Math.floor(parseFloat(aData.acos_L30 || aData.acos || 0));
                            var aSbgt;
                            if (aAcos < 10) {
                                aSbgt = 3;
                            } else if (aAcos < 20) {
                                aSbgt = 2;
                            } else {
                                aSbgt = 1;
                            }
                            
                            // Calculate SBGT for row B
                            var bAcos = Math.floor(parseFloat(bData.acos_L30 || bData.acos || 0));
                            var bSbgt;
                            if (bAcos < 10) {
                                bSbgt = 3;
                            } else if (bAcos < 20) {
                                bSbgt = 2;
                            } else {
                                bSbgt = 1;
                            }
                            
                            // Compare values
                            return aSbgt - bSbgt;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acos = Math.floor(parseFloat(row.acos_L30 || row.acos || 0));
                            var sbgt;
                            if (acos < 10) {
                                sbgt = 3;
                            } else if (acos < 20) {
                                sbgt = 2;
                            } else {
                                sbgt = 1;
                            }

                            return sbgt;
                        },
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            // Use acos_L30 from backend if available, otherwise fallback to acos
                            var acosRaw = row.acos_L30 || row.acos; 
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            var clicks = parseInt(row.l30_clicks || 0).toLocaleString();
                            var spend = parseFloat(row.l30_spend || 0).toFixed(0);
                            var adSold = parseInt(row.l30_purchases || 0).toLocaleString();
                            var tooltipText = "Clicks: " + clicks + "\nSpend: " + spend + "\nAd Sold: " + adSold;
                            
                            var acosDisplay;
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                acosDisplay = "0%";
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                                acosDisplay = acos.toFixed(0) + "%";
                            }
                            return `<div class="text-center">${acosDisplay}<i class="fa fa-info-circle ms-1 info-icon-toggle" style="cursor: pointer; color: #0d6efd;" title="${tooltipText}"></i></div>`;
                        }
                    },
                    {
                        title: "Clicks L30",
                        field: "l30_clicks",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Spend L30",
                        field: "l30_spend",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(0);
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "Ad Sold L30",
                        field: "l30_purchases",
                        hozAlign: "right",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
                        },
                        sorter: "number",
                        width: 90
                    },
                    {
                        title: "7 UB%",
                        field: "ub7",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ub7 = parseFloat(row.ub7) || 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            
                            // Color logic based on UB7 only (Amazon rules)
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "1 UB%",
                        field: "ub1",
                        hozAlign: "right",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ub1 = parseFloat(row.ub1) || 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            return l7_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            return l1_cpc.toFixed(2);
                        }
                    },
                    {
                        title: "SBID",
                        field: "sbid",
                        hozAlign: "center",
                        sorter: "number",
                        visible: function() {
                            return currentUtilizationType !== 'correctly' && currentUtilizationType !== 'all';
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = parseFloat(row.ub7) || 0;
                            
                            var sbid = '';
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: l1_cpc * 0.90 (if l7_cpc === 0, then 0.50)
                                if (l7_cpc === 0) {
                                    sbid = 0.50;
                                } else {
                                    sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                }
                            } else if (currentUtilizationType === 'under') {
                                // Under-utilized: Complex logic based on ub7 and l7_cpc
                                if (ub7 < 70) {
                                    if (ub7 < 10 || l7_cpc === 0) {
                                        sbid = 0.50;
                                    } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                        sbid = (l7_cpc + 0.20).toFixed(2);
                                    } else {
                                        sbid = (Math.floor((l7_cpc * 1.10) * 100) / 100).toFixed(2);
                                    }
                                } else {
                                    sbid = '';
                                }
                            } else {
                                // Correctly-utilized: Usually no SBID (empty)
                                sbid = '';
                            }
                            return sbid === '' ? '' : (typeof sbid === 'string' ? sbid : sbid.toFixed(2));
                        }
                    },
                    {
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-row-btn">APR BID</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-row-btn")) {
                                var rowData = cell.getRow().getData();
                                var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                                var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                                var ub7 = parseFloat(rowData.ub7) || 0;
                                
                                var sbid = '';
                                if (currentUtilizationType === 'over') {
                                    if (l7_cpc === 0) {
                                        sbid = 0.50;
                                    } else {
                                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 < 70) {
                                        if (ub7 < 10 || l7_cpc === 0) {
                                            sbid = 0.50;
                                        } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                            sbid = parseFloat((l7_cpc + 0.20).toFixed(2));
                                        } else {
                                            sbid = Math.floor((l7_cpc * 1.10) * 100) / 100;
                                        }
                                    } else {
                                        sbid = '';
                                    }
                                } else {
                                    sbid = '';
                                }
                                if (sbid !== '') {
                                    updateBid(sbid, rowData.campaign_id);
                                }
                            }
                        }
                    },
                    {
                        title: "APR BGT",
                        field: "apr_bgt",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            return `
                                <div style="align-items:center; gap:5px;">
                                    <button class="btn btn-primary update-bgt-row-btn">APR BGT</button>
                                </div>
                            `;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains("update-bgt-row-btn")) {
                                var rowData = cell.getRow().getData();
                                var acos = parseFloat(rowData.acos_L30 || rowData.acos || 0);

                                if (acos > 0) {
                                    var sbgtInput = cell.getRow().getElement().querySelector('.sbgt-input');
                                    var sbgtValue = sbgtInput ? parseFloat(sbgtInput.value) || 0 : 0;
                                    updateBgt(sbgtValue, rowData.campaign_id);
                                } else {
                                    console.log("Skipped because ACOS = 0 for campaign:", rowData.campaign_id);
                                }
                            }
                        }
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        formatter: function(cell) {
                            const value = cell.getValue()?.toUpperCase();
                            let dotColor = '';
                            
                            if (value === 'ENABLED') {
                                dotColor = 'green';
                            } else if (value === 'PAUSED') {
                                dotColor = 'red';
                            } else {
                                dotColor = 'gray';
                            }
                            
                            return `
                                <div style="display: flex; align-items: center; justify-content: center;">
                                    <span class="status-dot ${dotColor}" title="${value || ''}"></span>
                                </div>
                            `;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";

                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }

                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${percent}%
                                </span>
                            `;
                        }
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    }
                ],
            });

            // Update counts when data is loaded
            table.on("dataLoaded", function(data) {
                setTimeout(function() {
                    updateButtonCounts();
                }, 500);
            });

            // Combined filter function
            function combinedFilter(data) {
                const sku = data.sku || '';
                const isValidSku = sku && !sku.toUpperCase().includes('PARENT');
                
                // Apply card-based filters first
                if (showCampaignOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id && data.campaignName);
                    if (!hasCampaign) return false;
                }
                
                if (showMissingOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id && data.campaignName);
                    if (hasCampaign) return false;
                    let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                    let rowNraForMissing = data.NRA ? data.NRA.trim() : "";
                    // Only show missing (red dot) if neither NRL='NRL' nor NRA='NRA'
                    if (rowNrlForMissing === 'NRL' || rowNraForMissing === 'NRA') return false;
                }
                
                if (showNraMissingOnly) {
                    const hasCampaign = data.hasCampaign !== undefined ? data.hasCampaign : (data.campaign_id && data.campaignName);
                    if (hasCampaign) return false;
                    let rowNrlForMissing = data.NRL ? data.NRL.trim() : "";
                    let rowNraForMissing = data.NRA ? data.NRA.trim() : "";
                    // Only show NRA missing (yellow dot) if NRL='NRL' OR NRA='NRA'
                    if (rowNrlForMissing !== 'NRL' && rowNraForMissing !== 'NRA') return false;
                }
                
                if (showZeroInvOnly) {
                    let inv = parseFloat(data.INV || 0);
                    if (inv > 0) return false;
                }
                
                if (showNraOnly) {
                    let rowNra = data.NRA ? data.NRA.trim() : "";
                    if (rowNra !== 'NRA') return false;
                }
                
                if (showRaOnly) {
                    let rowNra = data.NRA ? data.NRA.trim() : "";
                    if (rowNra === 'NRA') return false;
                }
                
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                // Apply utilization type filter (same as Amazon utilized pages)
                if (currentUtilizationType === 'over') {
                    // Over-utilized: ub7 > 90 && ub1 > 90
                    if (!(ub7 > 90 && ub1 > 90)) return false;
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: ub7 < 70 && ub1 < 70
                    if (!(ub7 < 70 && ub1 < 70)) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Correctly-utilized: ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90
                    if (!(ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90)) return false;
                } else if (currentUtilizationType === 'all') {
                    // Show all - no filter applied
                }

                // Global search filter
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal)) && !(data.sku?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // Status filter
                let statusVal = $("#status-filter").val();
                if (statusVal && data.campaignStatus !== statusVal) {
                    return false;
                }

                // Inventory filter - Default to ALL (show all SKUs)
                let invFilterVal = $("#inv-filter").val();
                let inv = parseFloat(data.INV || 0);
                
                // By default (no filter selected or ALL), show everything
                if (!invFilterVal || invFilterVal === '' || invFilterVal === "ALL") {
                    // ALL option shows everything (including INV = 0 and negative), so no filtering needed
                } else if (invFilterVal === "INV_0") {
                    // Show only INV = 0
                    if (inv !== 0) return false;
                } else if (invFilterVal === "OTHERS") {
                    // Show only INV > 0 (exclude INV = 0 and negative)
                    if (inv <= 0) return false;
                }

                // NRA filter
                let nraFilterVal = $("#nra-filter").val();
                if (nraFilterVal) {
                    let rowVal = data.NRA ? data.NRA.trim() : "";
                    if (nraFilterVal === 'RA') {
                        // For "RA" filter, include empty/null values too
                        if (rowVal === 'NRA') return false;
                    } else {
                        // For "NRA" or "LATER", exact match
                    if (rowVal !== nraFilterVal) return false;
                    }
                }

                // SBGT filter (FBA rules: acos < 10 = 3, acos < 20 = 2, else = 1)
                let sbgtFilterVal = $("#sbgt-filter").val();
                if (sbgtFilterVal) {
                    let acos = parseFloat(data.acos_L30 || data.acos || 0);
                    let rowSbgt;
                    if (acos < 10) {
                        rowSbgt = 3;
                    } else if (acos < 20) {
                        rowSbgt = 2;
                    } else {
                        rowSbgt = 1;
                    }
                    if (parseInt(sbgtFilterVal) !== rowSbgt) return false;
                }

                return true;
            }

            // Update counts when data is filtered (set up before tableBuilt)
            let filterTimeout = null;
            table.on("dataFiltered", function(filteredRows) {
                if (filterTimeout) clearTimeout(filterTimeout);
                filterTimeout = setTimeout(function() {
                    updateButtonCounts();
                }, 200);
            });

            table.on("tableBuilt", function() {
                table.setFilter(combinedFilter);
                
                // Set initial column visibility based on current utilization type
                if (currentUtilizationType === 'correctly' || currentUtilizationType === 'all') {
                    table.hideColumn('sbid');
                    table.hideColumn('apr_bid');
                } else {
                    table.showColumn('sbid');
                    table.showColumn('apr_bid');
                }

                // Debounced search
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nra-filter, #sbgt-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                // Initial update of all button counts after data loads
                setTimeout(function() {
                    updateButtonCounts();
                }, 1000);
            });

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
                showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
                    // Reset dropdown to "All" when showing campaigns only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(6, 182, 212, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Missing campaign card click handler
            document.getElementById('missing-campaign-card').addEventListener('click', function() {
                showMissingOnly = !showMissingOnly;
                if (showMissingOnly) {
                    // Reset dropdown to "All" when showing missing only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(220, 53, 69, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // NRA missing card click handler
            document.getElementById('nra-missing-card').addEventListener('click', function() {
                showNraMissingOnly = !showNraMissingOnly;
                if (showNraMissingOnly) {
                    // Reset dropdown to "All" when showing NRA missing only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(255, 193, 7, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // Zero INV card click handler
            document.getElementById('zero-inv-card').addEventListener('click', function() {
                showZeroInvOnly = !showZeroInvOnly;
                if (showZeroInvOnly) {
                    // Reset dropdown to "All" when showing zero INV only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset NRA/RA filters
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // NRA card click handler
            document.getElementById('nra-card').addEventListener('click', function() {
                showNraOnly = !showNraOnly;
                if (showNraOnly) {
                    // Reset dropdown to "All" when showing NRA only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset RA filter
                    showRaOnly = false;
                    document.getElementById('ra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            // RA card click handler
            document.getElementById('ra-card').addEventListener('click', function() {
                showRaOnly = !showRaOnly;
                if (showRaOnly) {
                    // Reset dropdown to "All" when showing RA only
                    document.getElementById('utilization-type-select').value = 'all';
                    currentUtilizationType = 'all';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset NRA missing filter
                    showNraMissingOnly = false;
                    document.getElementById('nra-missing-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
                    // Reset NRA filter
                    showNraOnly = false;
                    document.getElementById('nra-card').style.boxShadow = '';
                    this.style.boxShadow = '0 4px 12px rgba(34, 197, 94, 0.5)';
                } else {
                    this.style.boxShadow = '';
                }
                
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    table.redraw(true);
                    updateButtonCounts();
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                    document.getElementById("apr-all-sbgt-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                    document.getElementById("apr-all-sbgt-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    // Update color immediately for NRA field
                    if (field === 'NRA') {
                        if (value === 'NRA') {
                            e.target.style.backgroundColor = '#dc3545'; // red
                            e.target.style.color = '#000';
                        } else if (value === 'RA') {
                            e.target.style.backgroundColor = '#28a745'; // green
                            e.target.style.color = '#000';
                        } else if (value === 'LATER') {
                            e.target.style.backgroundColor = '#ffc107'; // yellow
                            e.target.style.color = '#000';
                        }
                    }

                    fetch('/update-amazon-nr-nrl-fba-data', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            field: field,
                            value: value
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log(data);
                    })
                    .catch(err => console.error(err));
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    // Toggle L30 FBA, DIL %, NRA, Clicks L30, Spend L30, and Ad Sold L30 columns when info button is clicked
                    let colsToToggle = ["L30", "DIL %", "NRA", "Clicks L30", "Spend L30", "Ad Sold L30"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                
                // Handle ACOS info icon click to toggle Clicks L30, Spend L30, and Ad Sold L30
                if (e.target.classList.contains('info-icon-toggle')) {
                    e.stopPropagation();
                    var clicksCol = table.getColumn('l30_clicks');
                    var spendCol = table.getColumn('l30_spend');
                    var adSoldCol = table.getColumn('l30_purchases');
                    
                    // Toggle visibility
                    if (clicksCol && clicksCol.isVisible()) {
                        table.hideColumn('l30_clicks');
                        table.hideColumn('l30_spend');
                        table.hideColumn('l30_purchases');
                    } else {
                        table.showColumn('l30_clicks');
                        table.showColumn('l30_spend');
                        table.showColumn('l30_purchases');
                    }
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();
                var campaignIds = [];
                var bids = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();
                        var l1_cpc = parseFloat(rowData.l1_cpc) || 0;
                        var l7_cpc = parseFloat(rowData.l7_cpc) || 0;
                        var ub7 = parseFloat(rowData.ub7) || 0;
                        
                        var sbid = '';
                        if (currentUtilizationType === 'over') {
                            if (l7_cpc === 0) {
                                sbid = 0.50;
                            } else {
                                sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                            }
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 < 70) {
                                if (ub7 < 10 || l7_cpc === 0) {
                                    sbid = 0.50;
                                } else if (l7_cpc > 0 && l7_cpc < 0.30) {
                                    sbid = parseFloat((l7_cpc + 0.20).toFixed(2));
                                } else {
                                    sbid = Math.floor((l7_cpc * 1.10) * 100) / 100;
                                }
                            } else {
                                sbid = '';
                            }
                        } else {
                            sbid = '';
                        }

                        if (sbid !== '') {
                            campaignIds.push(rowData.campaign_id);
                            bids.push(sbid);
                        }
                    }
                });

                fetch('/update-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bids: bids
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        let errorMsg = data.message || "Something went wrong";
                        if (errorMsg.includes("Premium Ads")) {
                            alert("Error: " + errorMsg);
                        } else {
                            alert("Something went wrong: " + errorMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error updating bids");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            // Batch APR SBGT handler
            document.getElementById("apr-all-sbgt-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();
                var campaignIds = [];
                var bgts = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();
                        var acos = parseFloat(rowData.acos_L30 || rowData.acos || 0);

                        if (acos > 0) {
                            var sbgtInput = rowEl.querySelector('.sbgt-input');
                            var sbgtValue = sbgtInput ? parseFloat(sbgtInput.value) || 0 : 0;

                            campaignIds.push(rowData.campaign_id);
                            bgts.push(sbgtValue);
                        }
                    }
                });

                fetch('/update-amazon-campaign-bgt-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: campaignIds,
                        bgts: bgts
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Campaign budget updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Request failed: " + err.message);
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            });

            function updateBid(aprBid, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-keywords-bid-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bids: [aprBid]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Keywords updated successfully!");
                    } else {
                        let errorMsg = data.message || "Something went wrong";
                        if (errorMsg.includes("Premium Ads")) {
                            alert("Error: " + errorMsg);
                        } else {
                            alert("Something went wrong: " + errorMsg);
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Error updating bid");
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            function updateBgt(sbgtValue, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                fetch('/update-amazon-campaign-bgt-price', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        campaign_ids: [campaignId],
                        bgts: [sbgtValue]
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        alert("Campaign budget updated successfully!");
                    } else {
                        alert("Something went wrong: " + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Request failed: " + err.message);
                })
                .finally(() => {
                    overlay.style.display = "none";
                });
            }

            // Load counts
            loadUtilizationCounts();

            // Add click handlers to utilization cards
            document.querySelectorAll('.utilization-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    showUtilizationChart(type);
                });
            });
        });

        let utilizationChartInstance = null;

        function loadUtilizationCounts() {
            fetch('/amazon/fba/get-utilization-counts?type=KW')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        // 7UB count: sum of all utilization types based on 7UB only
                        const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0) + (data.correctly_utilized_7ub || 0);
                        // 7UB + 1UB count: sum of all utilization types based on both 7UB and 1UB
                        const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0) + (data.correctly_utilized_7ub_1ub || 0);
                        
                        document.getElementById('7ub-count').textContent = count7ub || 0;
                        document.getElementById('7ub-1ub-count').textContent = count7ub1ub || 0;
                        
                        // Update dropdown option texts with counts
                        const utilizationSelect = document.getElementById('utilization-type-select');
                        if (utilizationSelect) {
                            const total = (data.over_utilized || 0) + (data.under_utilized || 0) + (data.correctly_utilized || 0);
                            utilizationSelect.options[0].text = `All (${total})`;
                            utilizationSelect.options[1].text = `Over Utilized (${data.over_utilized || 0})`;
                            utilizationSelect.options[2].text = `Under Utilized (${data.under_utilized || 0})`;
                            utilizationSelect.options[3].text = `Correctly Utilized (${data.correctly_utilized || 0})`;
                        }
                    }
                })
                .catch(err => console.error('Error loading counts:', err));
        }

        function showUtilizationChart(type) {
            const chartTitle = document.getElementById('chart-title');
            const modal = new bootstrap.Modal(document.getElementById('utilizationChartModal'));
            
            const titles = {
                '7ub': '7UB Utilization Trend (Last 30 Days)',
                '7ub-1ub': '7UB + 1UB Utilization Trend (Last 30 Days)'
            };
            chartTitle.textContent = titles[type] || 'Utilization Trend';

            modal.show();

            fetch('/amazon/fba/get-utilization-chart-data?type=KW&condition=' + type)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200 && data.data && data.data.length > 0) {
                        const chartData = data.data;
                        const dates = chartData.map(d => d.date);
                        
                        const ctx = document.getElementById('utilizationChart').getContext('2d');
                        
                        if (utilizationChartInstance) {
                            utilizationChartInstance.destroy();
                        }

                        // Show all 3 lines (over, under, correctly) based on condition type
                        const datasets = [];
                        
                        if (type === '7ub') {
                            datasets.push({
                                label: 'Over Utilized',
                                data: chartData.map(d => d.over_utilized_7ub || 0),
                                borderColor: '#ff01d0',
                                backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Under Utilized',
                                data: chartData.map(d => d.under_utilized_7ub || 0),
                                borderColor: '#ff2727',
                                backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                        } else if (type === '7ub-1ub') {
                            datasets.push({
                                label: 'Over Utilized',
                                data: chartData.map(d => d.over_utilized_7ub_1ub || 0),
                                borderColor: '#ff01d0',
                                backgroundColor: 'rgba(255, 1, 208, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Under Utilized',
                                data: chartData.map(d => d.under_utilized_7ub_1ub || 0),
                                borderColor: '#ff2727',
                                backgroundColor: 'rgba(255, 39, 39, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                            datasets.push({
                                label: 'Correctly Utilized',
                                data: chartData.map(d => d.correctly_utilized_7ub_1ub || 0),
                                borderColor: '#28a745',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 2
                            });
                        }

                        utilizationChartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        enabled: true,
                                        mode: 'index',
                                        intersect: false,
                                        callbacks: {
                                            title: function(context) {
                                                return 'Date: ' + context[0].label;
                                            },
                                            label: function(context) {
                                                return context.dataset.label + ': ' + context.parsed.y;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(err => console.error('Error loading chart:', err));
        }
    </script>
@endsection
