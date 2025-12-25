@extends('layouts.vertical', ['title' => 'Amazon HL - Utilized', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
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

        /* Professional Card Styling */
        .card.shadow-sm {
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card.shadow-sm:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
            transform: translateY(-2px);
        }

        /* Enhanced Form Controls */
        .form-select, .form-control {
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .form-select:hover, .form-control:hover {
            border-color: #cbd5e1;
        }

        .input-group-text {
            border-color: #e2e8f0;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        /* Professional Labels */
        .form-label {
            color: #475569;
            font-size: 0.8125rem;
            letter-spacing: 0.01em;
            margin-bottom: 0.5rem;
        }

        .form-label i {
            color: #64748b;
        }

        /* Enhanced Border */
        .border-bottom {
            border-color: #e2e8f0 !important;
        }

        /* Better Spacing */
        .card-body.p-3 {
            padding: 1.25rem !important;
        }

        .card-body.p-4 {
            padding: 1.5rem !important;
        }

        /* Professional Button */
        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            border: none;
            font-weight: 500;
            letter-spacing: 0.01em;
            transition: all 0.2s ease;
        }

        .btn-info:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
        }

        /* Table Container Enhancement */
        #budget-under-table {
            margin-top: 1.5rem;
        }

        /* Professional Section Spacing */
        .mb-4 {
            margin-bottom: 2rem !important;
        }

        /* Enhanced Card Body */
        .card-body.py-3 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        body {
            zoom: 90%;
        }

        /* Badge Count Item Hover Effects */
        .badge-count-item {
            transition: all 0.2s ease;
        }

        .badge-count-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon HL - Utilized',
        'sub_title' => 'Amazon HL - Utilized',
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
                                    <div class="col-md-3">
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
                                    <div class="col-md-9">
                                        <label class="form-label fw-semibold mb-2 d-block" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-chart-line me-1" style="color: #64748b;"></i>Statistics
                                        </label>
                                        <div class="d-flex gap-3 justify-content-end align-items-center flex-wrap">
                                            <div class="badge-count-item" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">Total Parent</span>
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
                                                <span class="fw-bold" id="seven-ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                            <div class="badge-count-item utilization-card" data-type="7ub-1ub" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 8px 16px; border-radius: 8px; color: white; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1); cursor: pointer; transition: all 0.2s;">
                                                <span style="font-size: 0.75rem; display: block; margin-bottom: 2px;">7UB + 1UB</span>
                                                <span class="fw-bold" id="seven-ub-one-ub-count" style="font-size: 1.1rem;">0</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Search and Filter Controls Row -->
                                <div class="row align-items-end mt-3">
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
                                            <option value="RUNNING">Running</option>
                                            <option value="PAUSED">Paused</option>
                                            <option value="ENDED">Ended</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                            <i class="fa-solid fa-boxes me-1" style="color: #64748b;"></i>Inventory
                                        </label>
                                        <select id="inv-filter" class="form-select form-select-md">
                                            <option value="">All Inventory</option>
                                            <option value="ALL">ALL</option>
                                            <option value="INV_0">0 INV</option>
                                            <option value="OTHERS">OTHERS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
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
            let showZeroInvOnly = false; // Filter for zero/negative inventory only
            let showCampaignOnly = false; // Filter for campaigns only
            let showNraOnly = false; // Filter for NRA only
            let showRaOnly = false; // Filter for RA only
            let totalACOSValue = 0;
            let totalL30Spend = 0;
            let totalL30Sales = 0;
            let totalSkuCountFromBackend = 0; // Store total parent SKU count from backend

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            // Function to update button counts - shows all counts by default
            // Counts are based on filtered data (respects INV, NRA, status, search filters)
            // but shows all utilization types (not filtered by utilization type button)
            let countUpdateTimeout = null;
            function updateButtonCounts() {
                if (typeof table === 'undefined' || !table) {
                    return;
                }

                // Debounce for performance
                if (countUpdateTimeout) {
                    clearTimeout(countUpdateTimeout);
                }
                countUpdateTimeout = setTimeout(function() {
                    // Get all data and apply filters (except utilization type filter)
                    const allData = table.getData('all');
                    
                    // Count for each type (mutually exclusive like controller)
                    let overCount = 0;
                    let underCount = 0;
                    let correctlyCount = 0;
                    let missingCount = 0;
                    let zeroInvCount = 0; // Count zero and negative inventory
                    let totalCampaignCount = 0; // Count total campaigns
                    let nraCount = 0; // Count NRA
                    let raCount = 0; // Count RA
                    let validParentSkuCount = 0; // Count only parent SKUs
                    
                    // Track processed SKUs to avoid counting duplicates
                    const processedSkus = new Set();

                    allData.forEach(function(row) {
                        // Only count parent SKUs (SKU contains "PARENT")
                        const sku = row.sku || '';
                        const isParentSku = sku && sku.toUpperCase().includes('PARENT');
                        
                        if (!isParentSku) {
                            return; // Skip non-parent SKUs
                        }
                        
                        if (!processedSkus.has(sku)) {
                            processedSkus.add(sku);
                            validParentSkuCount++;
                        }
                        
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
                        
                        // Inventory filter (HL: no default filter, show all by default)
                        let invFilterVal = $("#inv-filter").val();
                        let inv = parseFloat(row.INV || 0);
                        
                        // Count zero/negative inventory (INV <= 0)
                        if (inv <= 0) {
                            zeroInvCount++;
                        }
                        
                        if (!invFilterVal || invFilterVal === '') {
                            // Default: show all (no filtering)
                        } else if (invFilterVal === "ALL") {
                            // ALL option shows everything
                        } else if (invFilterVal === "INV_0") {
                            // Show only INV = 0
                            if (inv !== 0) return;
                        } else if (invFilterVal === "OTHERS") {
                            // Show only INV > 0
                            if (inv <= 0) return;
                        }
                        
                        // Count NRA and RA only for parent SKUs and only once per SKU
                        if (!processedSkus.has(sku + '_nra')) {
                            processedSkus.add(sku + '_nra');
                            // Note: Empty/null NRA defaults to "RA" in the display
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
                        
                        // Check if campaign is missing or exists
                        const hasCampaign = row.hasCampaign !== undefined ? row.hasCampaign : (row.campaign_id && row.campaignName);
                        if (!hasCampaign) {
                            missingCount++;
                        } else {
                            totalCampaignCount++;
                        }
                        
                        // Now calculate utilization and count
                        let budget = parseFloat(row.campaignBudgetAmount) || 0;
                        let l7_spend = parseFloat(row.l7_spend || 0);
                        let l1_spend = parseFloat(row.l1_spend || 0);

                        let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                        // Mutually exclusive categorization (same as controller)
                        let categorized = false;

                        // Over-utilized check (priority 1): ub7 > 90 && ub1 > 90
                        if (!categorized && ub7 > 90 && ub1 > 90) {
                            overCount++;
                            categorized = true;
                        }

                        // Under-utilized check (priority 2: only if not over-utilized): ub7 < 70
                        if (!categorized && ub7 < 70 && ub1 < 70) {
                            underCount++;
                            categorized = true;
                        }

                        // Correctly-utilized check (priority 3: only if not already categorized): ub7 >= 70 && ub7 <= 90
                        if (!categorized && ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90) {
                            correctlyCount++;
                        }
                    });

                    // Update missing campaign count
                    const missingCountEl = document.getElementById('missing-campaign-count');
                    if (missingCountEl) {
                        missingCountEl.textContent = missingCount;
                    }
                    
                    // Update total campaign count
                    const totalCampaignCountEl = document.getElementById('total-campaign-count');
                    if (totalCampaignCountEl) {
                        totalCampaignCountEl.textContent = totalCampaignCount;
                    }
                    
                    // Update NRA count
                    const nraCountEl = document.getElementById('nra-count');
                    if (nraCountEl) {
                        nraCountEl.textContent = nraCount;
                    }
                    
                    // Update RA count
                    const raCountEl = document.getElementById('ra-count');
                    if (raCountEl) {
                        raCountEl.textContent = raCount;
                    }
                    
                    // Update zero INV count
                    const zeroInvCountEl = document.getElementById('zero-inv-count');
                    if (zeroInvCountEl) {
                        zeroInvCountEl.textContent = zeroInvCount;
                    }
                    
                    // Update total parent SKU count
                    const totalSkuCountEl = document.getElementById('total-sku-count');
                    if (totalSkuCountEl) {
                        totalSkuCountEl.textContent = totalSkuCountFromBackend || validParentSkuCount;
                    }

                    // Update 7UB and 7UB+1UB counts
                    const sevenUbCount = document.getElementById('seven-ub-count');
                    const sevenUbOneUbCount = document.getElementById('seven-ub-one-ub-count');
                    if (sevenUbCount) sevenUbCount.textContent = overCount;
                    if (sevenUbOneUbCount) sevenUbOneUbCount.textContent = overCount;
                    
                    // Update dropdown option texts with counts
                    // Use totalSkuCountFromBackend to match backend count exactly (parent SKUs only)
                    const utilizationSelect = document.getElementById('utilization-type-select');
                    if (utilizationSelect) {
                        utilizationSelect.options[0].text = `All (${totalSkuCountFromBackend || validParentSkuCount})`;
                        utilizationSelect.options[1].text = `Over Utilized (${overCount})`;
                        utilizationSelect.options[2].text = `Under Utilized (${underCount})`;
                        utilizationSelect.options[3].text = `Correctly Utilized (${correctlyCount})`;
                    }
                }, 150);
            }

            // Total campaign card click handler
            document.getElementById('total-campaign-card').addEventListener('click', function() {
                showCampaignOnly = !showCampaignOnly;
                if (showCampaignOnly) {
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
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
                    // Reset zero INV filter
                    showZeroInvOnly = false;
                    document.getElementById('zero-inv-card').style.boxShadow = '';
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

            // Zero INV card click handler
            document.getElementById('zero-inv-card').addEventListener('click', function() {
                showZeroInvOnly = !showZeroInvOnly;
                if (showZeroInvOnly) {
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
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
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
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
                    // Reset missing filter
                    showMissingOnly = false;
                    document.getElementById('missing-campaign-card').style.boxShadow = '';
                    // Reset campaign filter
                    showCampaignOnly = false;
                    document.getElementById('total-campaign-card').style.boxShadow = '';
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

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/amazon/utilized/hl/ads/data",
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
                columns: [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent",
                        field: "parent",
                        visible: false
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                data-sku="${sku}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Missing",
                        field: "hasCampaign",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            // Check if campaign exists: hasCampaign field or if campaign_id/campaignName exists
                            const hasCampaign = row.hasCampaign !== undefined 
                                ? row.hasCampaign 
                                : (row.campaign_id && row.campaignName);
                            const dotColor = hasCampaign ? 'green' : 'red';
                            const title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                            
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
                        visible: false
                    },
                    {
                        title: "FBA INV",
                        field: "FBA_INV",
                        visible: false,
                        headerSort: false,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return `<div class="text-center">${value || 0}</div>`;
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
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
                        title: "AL 30",
                        field: "A_L30",
                        visible: false
                    },
                    {
                        title: "A DIL %",
                        field: "A DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const al30 = parseFloat(data.A_L30);
                            const inv = parseFloat(data.INV);
                            if (!isNaN(al30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (al30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "NRL",
                        field: "NRL",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue() || "RL"; // Default to RL

                            let bgColor = "";
                            if (value === "NRL") {
                                bgColor = "background-color:#dc3545;color:#000;"; // red
                            } else {
                                bgColor = "background-color:#28a745;color:#000;"; // green (default for RL)
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRL"
                                        style="width: 90px; ${bgColor}">
                                    <option value="RL" ${value === 'RL' ? 'selected' : ''}>RL</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>NRL</option>
                                </select>
                            `;
                        },
                        visible: false,
                        hozAlign: "center"
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = (cell.getValue()?.trim()) || "RA"; // Default to RA

                            let bgColor = "";
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#000;"; // red
                            } else if (value === "RA") {
                                bgColor = "background-color:#28a745;color:#000;"; // green
                            } else if (value === "LATER") {
                                bgColor = "background-color:#ffc107;color:#000;"; // yellow
                            } else {
                                // Default to RA with green color
                                bgColor = "background-color:#28a745;color:#000;"; // green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 100px; ${bgColor}">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "FBA",
                        field: "FBA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "FBA") {
                                bgColor = "background-color:#007bff;color:#fff;"; // blue
                            } else if (value === "FBM") {
                                bgColor = "background-color:#6f42c1;color:#fff;"; // purple
                            } else if (value === "BOTH") {
                                bgColor = "background-color:#90ee90;color:#000;"; // light green
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="FBA"
                                        style="width: 90px; ${bgColor}">
                                    <option value="FBA" ${value === 'FBA' ? 'selected' : ''}>FBA</option>
                                    <option value="FBM" ${value === 'FBM' ? 'selected' : ''}>FBM</option>
                                    <option value="BOTH" ${value === 'BOTH' ? 'selected' : ''}>BOTH</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: (cell) => parseFloat(cell.getValue() || 0)
                    },
                    {
                        title: "ACOS",
                        field: "acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var acosRaw = row.acos; 
                            var acos = parseFloat(acosRaw);
                            if (isNaN(acos)) {
                                acos = 0;
                            }
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                return "100%"; 
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                            } else if (acos > 14) {
                                td.classList.add('red-bg');
                            }
                            return acos.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
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
                        field: "l1_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
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
                        visible: function() {
                            return currentUtilizationType !== 'correctly';
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_cpc = parseFloat(row.l1_cpc) || 0;
                            var l7_cpc = parseFloat(row.l7_cpc) || 0;
                            var ub7 = 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            if (budget > 0) {
                                ub7 = (parseFloat(row.l7_spend) || 0) / (budget * 7) * 100;
                            }
                            
                            var sbid = '';
                            if (currentUtilizationType === 'over') {
                                // Over-utilized: l7_cpc * 0.90 (if l7_cpc === 0, then 0.75)
                                if (l7_cpc === 0) {
                                    sbid = 0.75;
                                } else {
                                    sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                }
                            } else if (currentUtilizationType === 'under') {
                                // Under-utilized: Complex logic based on ub7 and l7_cpc
                                if (ub7 < 70) {
                                    if (ub7 < 10 || l7_cpc === 0) {
                                        sbid = 0.75;
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
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
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
                        title: "Status",
                        field: "campaignStatus",
                        formatter: function(cell) {
                            const value = cell.getValue()?.toUpperCase();
                            let dotColor = '';
                            
                            if (value === 'RUNNING' || value === 'ENABLED') {
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
                        title: "APR BID",
                        field: "apr_bid",
                        hozAlign: "center",
                        visible: function() {
                            return currentUtilizationType !== 'correctly';
                        },
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
                                var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                                var ub7 = 0;
                                if (budget > 0) {
                                    ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                                }
                                
                                var sbid = '';
                                if (currentUtilizationType === 'over') {
                                    if (l7_cpc === 0) {
                                        sbid = 0.75;
                                    } else {
                                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                                    }
                                } else if (currentUtilizationType === 'under') {
                                    if (ub7 < 70) {
                                        if (ub7 < 10 || l7_cpc === 0) {
                                            sbid = 0.75;
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
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    totalACOSValue = parseFloat(response.total_acos) || 0;
                    totalL30Spend = parseFloat(response.total_l30_spend) || 0;
                    totalL30Sales = parseFloat(response.total_l30_sales) || 0;
                    totalSkuCountFromBackend = parseFloat(response.total_sku_count) || 0;
                    return response.data;
                }
            });

            // Utilization type dropdown handler
            document.getElementById('utilization-type-select').addEventListener('change', function() {
                currentUtilizationType = this.value;
                // Reset missing filter when dropdown changes
                showMissingOnly = false;
                document.getElementById('missing-campaign-card').style.boxShadow = '';
                // Reset campaign filter
                showCampaignOnly = false;
                document.getElementById('total-campaign-card').style.boxShadow = '';
                // Reset zero INV filter
                showZeroInvOnly = false;
                document.getElementById('zero-inv-card').style.boxShadow = '';
                // Reset NRA/RA filters
                showNraOnly = false;
                document.getElementById('nra-card').style.boxShadow = '';
                showRaOnly = false;
                document.getElementById('ra-card').style.boxShadow = '';
                    
                if (typeof table !== 'undefined' && table) {
                    table.setFilter(combinedFilter);
                    // Redraw cells to update formatter colors based on new type
                    table.redraw(true);
                    // Update column visibility for SBID and APR BID
                    table.hideColumn('sbid');
                    table.hideColumn('apr_bid');
                    if (currentUtilizationType !== 'correctly' && currentUtilizationType !== 'all') {
                        table.showColumn('sbid');
                        table.showColumn('apr_bid');
                    }
                    // Update all button counts after filter is applied
                    setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                }
            });

            // Combined filter function
            function combinedFilter(data) {
                // Only show parent SKUs (SKU contains "PARENT")
                const sku = data.sku || '';
                const isParentSku = sku && sku.toUpperCase().includes('PARENT');
                if (!isParentSku) {
                    return false; // Hide non-parent SKUs
                }
                
                let acos = parseFloat(data.acos || 0);
                let budget = parseFloat(data.campaignBudgetAmount) || 0;
                let l7_spend = parseFloat(data.l7_spend) || 0;
                let l1_spend = parseFloat(data.l1_spend) || 0;

                let ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                let ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                let rowAcos = parseFloat(acos) || 0;
                if (isNaN(rowAcos) || rowAcos === 0) {
                    rowAcos = 100;
                }

                // Check if campaign is missing
                const hasCampaign = data.hasCampaign !== undefined 
                    ? data.hasCampaign 
                    : (data.campaign_id && data.campaignName);

                // Apply campaign filters
                if (showCampaignOnly) {
                    // Show only rows with campaigns
                    if (!hasCampaign) return false;
                } else if (showMissingOnly) {
                    // Show only rows without campaigns
                    if (hasCampaign) return false;
                }

                // Apply utilization type filter (Amazon rules)
                if (currentUtilizationType === 'all') {
                    // Show all data (no filter on utilization)
                } else if (currentUtilizationType === 'over') {
                    // Over-utilized: ub7 > 90 && ub1 > 90
                    if (!(ub7 > 90 && ub1 > 90)) return false;
                } else if (currentUtilizationType === 'under') {
                    // Under-utilized: ub7 < 70
                    if (!(ub7 < 70 && ub1 < 70)) return false;
                } else if (currentUtilizationType === 'correctly') {
                    // Correctly-utilized: ub7 >= 70 && ub7 <= 90
                    if (!(ub7 >= 70 && ub7 <= 90 && ub1 >= 70 && ub1 <= 90)) return false;
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

                // Apply zero INV filter first (if enabled)
                let inv = parseFloat(data.INV || 0);
                if (showZeroInvOnly) {
                    // Show only zero or negative inventory
                    if (inv > 0) return false;
                } else {
                    // Inventory filter (HL: no default filter, show all by default)
                    let invFilterVal = $("#inv-filter").val();
                    
                    // By default (no filter selected), show all campaigns
                    if (!invFilterVal || invFilterVal === '') {
                        // No filtering - show all
                    } else if (invFilterVal === "ALL") {
                        // ALL option shows everything
                    } else if (invFilterVal === "INV_0") {
                        // Show only INV = 0
                        if (inv !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        // Show only INV > 0 (exclude INV = 0 and negative)
                        if (inv <= 0) return false;
                    }
                }

                // Apply NRA/RA filters first (if enabled)
                // Note: Empty/null NRA defaults to "RA" in the display
                let rowNra = data.NRA ? data.NRA.trim() : "";
                if (showNraOnly) {
                    // Show only NRA (explicitly "NRA" only)
                    if (rowNra !== 'NRA') return false;
                } else if (showRaOnly) {
                    // Show only RA (including empty/null which defaults to "RA")
                    if (rowNra === 'NRA') return false;
                } else {
                    // NRA filter
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        if (nraFilterVal === 'RA') {
                            // For "RA" filter, include empty/null values too
                            if (rowNra === 'NRA') return false;
                        } else {
                            // For "NRA" or "LATER", exact match
                            if (rowNra !== nraFilterVal) return false;
                        }
                    }
                }

                return true;
            }

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

                // Update counts when data is filtered (debounced)
                let filterTimeout = null;
                table.on("dataFiltered", function(filteredRows) {
                    if (filterTimeout) clearTimeout(filterTimeout);
                    filterTimeout = setTimeout(function() {
                        updateButtonCounts();
                    }, 200);
                });

                // Debounced search
                let searchTimeout = null;
                $("#global-search").on("keyup", function() {
                    if (searchTimeout) clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function() {
                        table.setFilter(combinedFilter);
                    }, 300);
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                // Initial update of all button counts after data loads
                setTimeout(function() {
                    updateButtonCounts();
                }, 1000);
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-amazon-nr-nrl-fba', {
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
                    let colsToToggle = ["INV", "L30", "DIL %", "NR", "A_L30", "ADIL %", "NRL", "NRA", "FBA", "FBA_INV"];
                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
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
                        var budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                        var ub7 = 0;
                        if (budget > 0) {
                            ub7 = (parseFloat(rowData.l7_spend) || 0) / (budget * 7) * 100;
                        }
                        
                        var sbid = '';
                        if (currentUtilizationType === 'over') {
                            if (l7_cpc === 0) {
                                sbid = 0.75;
                            } else {
                                sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                            }
                        } else if (currentUtilizationType === 'under') {
                            if (ub7 < 70) {
                                if (ub7 < 10 || l7_cpc === 0) {
                                    sbid = 0.75;
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
            fetch('/amazon/get-utilization-counts?type=HL')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 200) {
                        // 7UB count: sum of all utilization types based on 7UB only
                        const count7ub = (data.over_utilized_7ub || 0) + (data.under_utilized_7ub || 0) + (data.correctly_utilized_7ub || 0);
                        // 7UB + 1UB count: sum of all utilization types based on both 7UB and 1UB
                        const count7ub1ub = (data.over_utilized_7ub_1ub || 0) + (data.under_utilized_7ub_1ub || 0) + (data.correctly_utilized_7ub_1ub || 0);
                        
                        document.getElementById('seven-ub-count').textContent = count7ub || 0;
                        document.getElementById('seven-ub-one-ub-count').textContent = count7ub1ub || 0;
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

            fetch('/amazon/get-utilization-chart-data?type=HL&condition=' + type)
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

