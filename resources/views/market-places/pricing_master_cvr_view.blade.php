@extends('layouts.vertical', ['title' => 'Master Analytics ', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Hide sort icons – sorting still works on header click */
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers */
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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* OV L30 Modal – light bg with dark text */
        #ovl30DetailsModal .modal-header,
        #ovl30DetailsModal .table thead {
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        #ovl30DetailsModal .modal-vertical-header th {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            vertical-align: middle;
            font-size: 11px;
            font-weight: 600;
            padding: 5px;
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        /* Exception for M column - keep it horizontal */
        #ovl30DetailsModal .modal-vertical-header th:nth-child(1) {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            min-height: 80px;
        }

        /* ========== STATUS INDICATORS ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ff9c00;
        }

        .status-circle.blue {
            background-color: #3591dc;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }
        
        /* Totals row – light background, dark text */
        #ovl30DetailsModal .modal-totals-row {
            background-color: #f1f5f9 !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            border-top: 2px solid #cbd5e1 !important;
        }
        #ovl30DetailsModal .modal-totals-row th {
            font-weight: 600 !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        #ovl30DetailsModal .modal-body {
            background-color: #fff !important;
            color: #0f172a !important;
            overflow: visible;
        }
        #ovl30DetailsModal .ovl30-table-wrap {
            max-height: min(70vh, 520px);
            overflow-y: auto;
            overflow-x: auto;
        }
        #ovl30DetailsModal .table tbody {
            background-color: #fff !important;
            color: #0f172a !important;
        }
        #ovl30DetailsModal .table td {
            color: #334155 !important;
        }
        /* Sticky table header + totals row in OV L30 Details modal */
        #ovl30DetailsModal .table thead .modal-vertical-header th {
            position: sticky;
            top: 0;
            z-index: 11;
            background-color: #e2e8f0 !important;
            box-shadow: 0 1px 0 0 #cbd5e1;
        }
        #ovl30DetailsModal .table thead .modal-totals-row th {
            position: sticky;
            top: 80px;
            z-index: 10;
            background-color: #f1f5f9 !important;
            box-shadow: 0 1px 0 0 #e2e8f0;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th:nth-child(1) {
            min-height: 80px;
        }
        /* Sortable column headers – cursor and sort icon */
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable {
            cursor: pointer;
            user-select: none;
        }
        #ovl30DetailsModal .table thead .modal-vertical-header th.ovl30-sortable:hover {
            background-color: #cbd5e1 !important;
        }

        /* Missing L modal – stacks above the detail modal */
        #missingLModal {
            z-index: 1060;
        }
        .missing-l-dot:hover {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.35);
            transform: scale(1.3);
            transition: transform 0.12s ease, box-shadow 0.12s ease;
        }
        #ovl30DetailsModal .ovl30-sort-icon {
            font-size: 10px;
            margin-left: 2px;
            opacity: 0.7;
        }
        #ovl30DetailsModal .ovl30-sort-icon.active {
            opacity: 1;
        }

        /* ========== DROPDOWN STYLING ========== */
        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }

        .dropdown-item:hover {
            color: #1e2125;
            background-color: #e9ecef;
        }

        /* Parent row styling - Light blue background like Amazon */
        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        /* Push price button styling */
        .push-price-btn {
            font-size: 14px;
            padding: 4px 10px;
            white-space: nowrap;
            min-width: 36px;
        }
        
        .push-price-btn i {
            font-size: 12px;
        }
        
        .push-price-btn:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Pushed By column styling */
        .pushed-by-info {
            font-size: 11px;
        }
        
        .pushed-by-info strong {
            display: block;
            color: #28a745;
        }
        
        .pushed-by-info .text-muted {
            font-size: 10px;
        }

        /* Modal width - 95% of screen */
        .modal-xxl {
            max-width: 90% !important;
        }

        /* Parent SKU dot - P column */
        .parent-sku-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #17a2b8;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .parent-sku-dot:hover {
            background-color: #0d6efd;
        }
        .parent-sku-dot.no-parent {
            background-color: #dee2e6;
            cursor: default;
        }

        /* SKU column — larger on hover for readability */
        .pricing-master-sku-text {
            display: inline-block;
            transform-origin: left center;
            transition: transform 0.18s ease;
            cursor: default;
        }
        .pricing-master-sku-text:hover {
            transform: scale(1.35);
            position: relative;
            z-index: 5;
        }
        .tabulator .tabulator-cell.pricing-master-sku-col {
            overflow: visible;
        }

        /* Row selection checkboxes */
        .row-select-cb, .select-all-cb {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        /* Summary header bar */
        .summary-header-bar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-size: 14px;
        }
        .summary-item {
            color: #495057;
        }
        .summary-item strong {
            color: #212529;
            margin-right: 4px;
        }
        .summary-chart-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #212529;
            background-color: #fff;
            border: 1px solid #dee2e6;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.15s, border-color 0.15s, box-shadow 0.15s;
        }
        .summary-chart-badge:hover {
            background-color: #f8f9fa;
            border-color: #ff9c00;
            box-shadow: 0 1px 4px rgba(255, 156, 0, 0.2);
        }
        .summary-chart-badge .summary-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .summary-chart-badge .summary-badge-value {
            font-weight: 700;
        }
        .summary-badge-only {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #212529;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }
        .summary-badge-only .summary-badge-value {
            font-weight: 700;
        }

        /* Sprice modal: top-center position and draggable header */
        #spriceDetailsModal.modal {
            align-items: flex-start;
            padding-top: 1.5rem;
        }
        #spriceDetailsModal .sprice-modal-dialog {
            margin-top: 0;
        }
        #spriceDetailsModal .modal-header.sprice-modal-drag-header {
            cursor: move;
            user-select: none;
        }

    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Master Analytics',
        'sub_title' => 'Master Analytics Data with Editable SPRICE',
    ])
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- Remark History Modal -->
    <div class="modal fade" id="remarkHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #6c757d;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history me-2"></i> 
                        Remark History - <span id="historySkuName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead style="background-color: #6c757d; color: white;">
                                <tr>
                                    <th style="width: 50%;">Remark</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="remarkHistoryTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No remarks yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sprice Details Modal (top-center, draggable by header) -->
    <div class="modal fade" id="spriceDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog sprice-modal-dialog">
            <div class="modal-content">
                <div class="modal-header sprice-modal-drag-header" style="background-color: #0d6efd;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-dollar-sign me-2"></i>
                        Sprice – <span id="spriceModalSkuName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%;">Amz SPRICE</th>
                                <td class="text-end">
                                    <span class="text-muted me-1">$</span>
                                    <input type="number" class="form-control form-control-sm d-inline-block sprice-modal-sprice-input" id="spriceModalAmzSpriceInput" value="" step="0.01" min="0" placeholder="0.00" style="width: 90px; text-align: right;">
                                </td>
                            </tr>
                            <tr>
                                <th>Amz SGPFT%</th>
                                <td class="text-end" id="spriceModalSgpft">-</td>
                            </tr>
                            <tr>
                                <th>Amz SPFT%</th>
                                <td class="text-end" id="spriceModalSpft">-</td>
                            </tr>
                            <tr>
                                <th>Amz SROI%</th>
                                <td class="text-end" id="spriceModalSroi">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- OV L30 Details Modal -->
    <div class="modal fade" id="ovl30DetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xxl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #e2e8f0; color: #0f172a;">
                    <div class="modal-title d-flex align-items-center justify-content-between w-100" style="font-size: 2em; color: #0f172a;">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-mouse-pointer me-2"></i> 
                            <span id="modalSkuName" style="font-weight: bold;">SKU</span>
                        </div>
                        <div class="d-flex align-items-center gap-5">
                            <span>
                                <strong>Total INV:</strong> <span id="modal-header-inv">0</span>
                            </span>
                            <span>
                                <strong>L30 Sold:</strong> <span id="modal-header-l30">0</span>
                            </span>
                            <span>
                                <strong>Dil %:</strong> <span id="modal-header-dil">0%</span>
                            </span>
                            <span id="modal-header-lmp-link" style="cursor: pointer; text-decoration: underline;" title="Click to view LMP competitors">
                                <i class="fas fa-search me-2"></i><strong>LMP</strong>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background-color: #fff;">
                    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                        <label class="text-nowrap mb-0" style="color: #0f172a; font-weight: 600;">Sort by:</label>
                        <select id="ovl30ModalSortBy" class="form-select form-select-sm" style="width: auto; min-width: 180px;">
                            <option value="l30_desc">L30 (High → Low)</option>
                            <option value="l30_asc">L30 (Low → High)</option>
                            <option value="marketplace_asc">Marketplace (A → Z)</option>
                            <option value="marketplace_desc">Marketplace (Z → A)</option>
                            <option value="price_desc">Price (High → Low)</option>
                            <option value="price_asc">Price (Low → High)</option>
                            <option value="views_desc">Views (High → Low)</option>
                            <option value="views_asc">Views (Low → High)</option>
                            <option value="cvr_desc">CVR% (High → Low)</option>
                            <option value="cvr_asc">CVR% (Low → High)</option>
                            <option value="gpft_desc">GPFT% (High → Low)</option>
                            <option value="gpft_asc">GPFT% (Low → High)</option>
                            <option value="ad_asc">AD% (Low → High)</option>
                            <option value="ad_desc">AD% (High → Low)</option>
                            <option value="tacos_asc">TACOS CH (Low → High)</option>
                            <option value="tacos_desc">TACOS CH (High → Low)</option>
                            <option value="npft_desc">NPFT% (High → Low)</option>
                            <option value="npft_asc">NPFT% (Low → High)</option>
                            <option value="sprice_desc">SPRICE (High → Low)</option>
                            <option value="sprice_asc">SPRICE (Low → High)</option>
                            <option value="sgpft_desc">SGPFT% (High → Low)</option>
                            <option value="sgpft_asc">SGPFT% (Low → High)</option>
                            <option value="spft_desc">SPFT% (High → Low)</option>
                            <option value="spft_asc">SPFT% (Low → High)</option>
                            <option value="sroi_desc">SROI% (High → Low)</option>
                            <option value="sroi_asc">SROI% (Low → High)</option>
                        </select>
                    </div>
                    <div class="table-responsive ovl30-table-wrap">
                        <table class="table table-bordered table-hover mb-0" id="ovl30DetailsTable">
                            <thead style="background-color: #e2e8f0; color: #0f172a;">
                                <tr class="modal-vertical-header">
                                    <th class="ovl30-sortable" data-sort="marketplace" data-dir="asc" title="Sort by Marketplace"><span>M</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="l30" data-dir="desc" title="Sort by L30"><span>L30</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th title="Missing Listings – click dot to view channels with no price"><span>Missing L</span></th>
                                    <th class="ovl30-sortable" data-sort="price" data-dir="desc" title="Sort by Price"><span>Price</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="views" data-dir="desc" title="Sort by Views"><span>Views</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="cvr" data-dir="desc" title="Sort by CVR%"><span>CVR%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="gpft" data-dir="desc" title="Sort by GPFT%"><span>GPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="ad" data-dir="asc" title="Sort by AD%"><span>AD%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="tacos" data-dir="asc" title="Sort by TACOS CH"><span>TACOS CH</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="npft" data-dir="desc" title="Sort by NPFT%"><span>NPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th>LMP</th>
                                    <th>Links</th>
                                    <th class="ovl30-sortable" data-sort="sprice" data-dir="desc" title="Sort by SPRICE"><span>SPRICE</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sgpft" data-dir="desc" title="Sort by SGPFT%"><span>SGPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="spft" data-dir="desc" title="Sort by SPFT%"><span>SPFT%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th class="ovl30-sortable" data-sort="sroi" data-dir="desc" title="Sort by SROI%"><span>SROI%</span><i class="ovl30-sort-icon fas fa-sort ms-1"></i></th>
                                    <th>Push</th>
                                    <th>Pushed By</th>
                                </tr>
                                <tr class="modal-totals-row">
                                    <th><img id="modal-product-image" src="" alt="" style="width: 50px; height: 50px; object-fit: cover; display: none;"><span class="ms-1">Total</span></th>
                                    <th class="text-end" id="modal-total-l30">0</th>
                                    <th class="text-center">
                                        <span class="missing-l-dot" data-sku=""
                                            style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#dc3545;cursor:pointer;border:1px solid #a00211;"
                                            title="View missing listings for this SKU"></span>
                                    </th>
                                    <th class="text-end" id="modal-total-price">$0.00</th>
                                    <th class="text-end" id="modal-total-views">0</th>
                                    <th class="text-end" id="modal-avg-cvr">0%</th>
                                    <th class="text-end" id="modal-avg-gpft">0%</th>
                                    <th class="text-end" id="modal-avg-ad">0%</th>
                                    <th class="text-end" id="modal-avg-tacos">0%</th>
                                    <th class="text-end" id="modal-avg-npft">0%</th>
                                    <th></th>
                                    <th></th>
                                    <th class="text-end" id="modal-avg-sprice">$0.00</th>
                                    <th class="text-end" id="modal-avg-sgpft">0%</th>
                                    <th class="text-end" id="modal-avg-spft">0%</th>
                                    <th class="text-end" id="modal-avg-sroi">0%</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="18" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            </tbody>
                        </table>

                    
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Missing L – Channels with Price Modal -->
    <div class="modal fade" id="missingLModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #e2e8f0; color: #0f172a;">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2" style="color:#dc3545;"></i> Missing Listings &mdash; <span id="missingLSkuName" style="font-weight:bold;"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="missingLTable">
                            <thead style="background-color: #e2e8f0; color: #0f172a;">
                                <tr>
                                    <th>Channel</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Listed</th>
                                    <th class="text-end">L30</th>
                                </tr>
                            </thead>
                            <tbody id="missingLTableBody">
                                <tr><td colspan="4" class="text-center text-muted py-4">No data.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Amazon SPRICE Table Modal -->
    <div class="modal fade" id="amazonSpriceTableModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #232f3e; color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-table me-2"></i> Amazon SPRICE Table
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="amazonSpriceTableModalTable">
                            <thead style="background-color: #232f3e; color: white;">
                                <tr>
                                    <th>SKU</th>
                                    <th class="text-end">SPRICE</th>
                                    <th class="text-end">Amazon Margin</th>
                                    <th class="text-end">SGPFT%</th>
                                    <th class="text-end">SPFT%</th>
                                    <th class="text-end">SROI%</th>
                                    <th class="text-end">Avg PFT%</th>
                                    <th>Updated At</th>
                                </tr>
                            </thead>
                            <tbody id="amazonSpriceTableModalBody">
                                <tr><td colspan="8" class="text-center text-muted py-4">Load data using the button below.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal (Amazon + eBay in single view) -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> LMP Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="lmpDataList">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border text-primary me-2"></div>Loading competitors...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Master Analytics Rolling L30 Chart Modal (Inv, OV L30, Price, CVR) -->
    <div class="modal fade" id="pricingMasterChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width: 98vw; width: 98vw; margin: 10px auto 0;">
            <div class="modal-content" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="pricingMasterChartModalTitle">Master Analytics - Inv (Rolling L30)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="pricingMasterChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="pricingMasterChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="pricingMasterChart"></canvas>
                        </div>
                        <div id="pricingMasterChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="pricingMasterChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="pricingMasterChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="pricingMasterChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="pricingMasterChartLoading" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="pricingMasterChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No daily data for this SKU yet.</p>
                        <p class="text-muted small mb-0"><strong>Refresh this page (Master Analytics CVR)</strong> once so today’s SKU data is saved, then open the graph again.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <!-- Header Bar - Totals (badges with chart links) -->
            <div class="summary-header-bar px-4 py-3 d-flex flex-wrap align-items-center gap-3 border-bottom">
                <a href="#" class="summary-chart-badge" data-metric="inv" data-aggregate="1" title="Click to view Inv line graph">
                    <span class="summary-badge-dot" style="background-color: #4361ee;"></span>
                    <span>Total INV:</span>
                    <span class="summary-badge-value" id="total-inv-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="ov_l30" data-aggregate="1" title="Click to view OV L30 line graph">
                    <span class="summary-badge-dot" style="background-color: #28a745;"></span>
                    <span>Total OV L30:</span>
                    <span class="summary-badge-value" id="total-l30-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="dil" data-aggregate="1" title="Click to view DIL line graph">
                    <span class="summary-badge-dot" style="background-color: #0d6efd;"></span>
                    <span>DIL:</span>
                    <span class="summary-badge-value" id="avg-dil-badge">0%</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="total_views" data-aggregate="1" title="Click to view Total Views line graph">
                    <span class="summary-badge-dot" style="background-color: #17a2b8;"></span>
                    <span>Total Views:</span>
                    <span class="summary-badge-value" id="total-views-badge">0</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="cvr" data-aggregate="1" title="Click to view CVR line graph">
                    <span class="summary-badge-dot" style="background-color: #ff9c00;"></span>
                    <span>CVR:</span>
                    <span class="summary-badge-value" id="avg-cvr-badge">0%</span>
                </a>
                <a href="#" class="summary-chart-badge" data-metric="price" data-aggregate="1" title="Click to view Avg Price line graph">
                    <span class="summary-badge-dot" style="background-color: #e83e8c;"></span>
                    <span>Avg Price:</span>
                    <span class="summary-badge-value" id="avg-price-badge">$0.00</span>
                </a>
                <span class="summary-badge-only">
                    <span class="summary-badge-dot" style="background-color: #6c757d;"></span>
                    <span>Amz LMP:</span>
                    <span class="summary-badge-value" id="amz-lmp-badge">$0.00</span>
                </span>
                <span class="d-flex align-items-center gap-2 ms-auto">
                    <label class="mb-0 fw-semibold">Change Price:</label>
                    <input type="number" id="change-price-input" class="form-control form-control-sm" placeholder="Enter price" step="0.01" min="0" style="width: 100px;">
                    <button type="button" id="apply-change-price-btn" class="btn btn-sm btn-primary">
                        <i class="fas fa-check"></i> Apply
                    </button>
                </span>
            </div>
            <div class="card-body py-3">
             
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <!-- DIL Filter (Walmart-style dropdown) -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.7%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.7-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- CVR Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="cvrFilterDropdown">
                            <span class="status-circle default"></span> CVR
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="cvrFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="avg_cvr" data-range="all">
                                    <span class="status-circle default"></span> All CVR</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0">
                                    <span class="status-circle red"></span> 0 to 0.00%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0.01-1">
                                    <span class="status-circle red"></span> 0.01 - 1%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="1-2">
                                    <span class="status-circle yellow"></span> 1-2%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="2-3">
                                    <span class="status-circle yellow"></span> 2-3%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="3-4">
                                    <span class="status-circle green"></span> 3-4%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="0-4">
                                    <span class="status-circle default"></span> 0-4%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="4-7">
                                    <span class="status-circle green"></span> 4-7%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="7-10">
                                    <span class="status-circle green"></span> 7-10%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_cvr" data-range="10+">
                                    <span class="status-circle pink"></span> 10%+</a></li>
                        </ul>
                    </div>

                    <!-- GPFT% Filter -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="gpftFilterDropdown">
                            <span class="status-circle default"></span> GPFT%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="gpftFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="avg_gpft" data-range="all">
                                    <span class="status-circle default"></span> All GPFT</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="negative">
                                    <span class="status-circle red"></span> Negative</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="0-10">
                                    <span class="status-circle yellow"></span> 0-10%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="10-20">
                                    <span class="status-circle blue"></span> 10-20%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="20-30">
                                    <span class="status-circle green"></span> 20-30%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="30-40">
                                    <span class="status-circle green"></span> 30-40%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="40-50">
                                    <span class="status-circle green"></span> 40-50%</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="avg_gpft" data-range="50+">
                                    <span class="status-circle pink"></span> 50%+</a></li>
                        </ul>
                    </div>

                    <!-- OV vs SW L30 (green = match, red = mismatch) -->
                    <select id="sw-l30-match-filter" class="form-select form-select-sm" style="width: auto; min-width: 168px;"
                        title="Show rows where OV L30 equals SW L30 (green), or only mismatches (red text)">
                        <option value="all" selected>SW L30 — All</option>
                        <option value="red">SW L30 — Red only</option>
                    </select>

                    <!-- SKU/Parent Filter -->
                    <select id="sku-parent-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="both" selected>Both (SKU + Parent)</option>
                        <option value="sku">SKU Only</option>
                        <option value="parent">Parent Only</option>
                    </select>

                    <button type="button" id="remove-filter-btn" class="btn btn-sm btn-outline-danger" title="Remove all filters">
                        <i class="fas fa-times-circle"></i> Remove Filter
                    </button>

                    <!-- Play → shows Pause, enables Next/Prev; Pause → back to normal, disables Next/Prev -->
                    <div class="btn-group align-items-center ms-2" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause - click to reset Play">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="cvr-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU..." style="max-width: 220px;">
                        <input type="text" id="parent-search" class="form-control" placeholder="Search Parent..." style="max-width: 220px;">
                    </div>
                    <!-- Table body -->
                    <div id="cvr-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    /**
     * ========================================
     * CVR MASTER - TABULATOR VIEW
     * ========================================
     * 
     * FEATURES:
     * - Display: Image, SKU, INV, OV L30, DIL%
     * - Color-coded DIL% (Red < 16.7%, Yellow 16.7-25%, Green 25-50%, Pink 50%+)
     * - SKU-wise breakdown modal (click info icon on OV L30)
     * - Filters: Inventory, DIL%
     * - Export to CSV
     * 
     * BACKEND ENDPOINTS:
     * 1. GET /cvr-master-data-json - Main table data
     * 2. GET /cvr-master-breakdown?sku=... - Modal breakdown data
     * 3. GET/POST /cvr-master-column-visibility - Column visibility
     * ========================================
     */

    let table = null;
    
    // ==================== UTILITY FUNCTIONS ====================
    
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
        
        // ==================== MODAL FUNCTIONS ====================
        
        // OVL30 Details modal sort dropdown – re-render table with current sort
        $(document).on('change', '#ovl30ModalSortBy', function() {
            if (ovl30ModalData.length) {
                renderMarketplaceData();
                updateOvl30SortIcons();
            }
        });

        // OVL30 Details modal – column header click to sort
        $(document).on('click', '#ovl30DetailsModal .modal-vertical-header th.ovl30-sortable', function() {
            const sortField = $(this).data('sort');
            const currentVal = ($('#ovl30ModalSortBy').val() || 'l30_desc').toString();
            const [currentField, currentDir] = currentVal.split('_');
            let newDir = currentDir;
            if (currentField === sortField) {
                newDir = currentDir === 'asc' ? 'desc' : 'asc';
            } else {
                newDir = (sortField === 'marketplace') ? 'asc' : 'desc';
            }
            const newVal = sortField + '_' + newDir;
            const $sel = $('#ovl30ModalSortBy');
            if ($sel.find('option[value="' + newVal + '"]').length) {
                $sel.val(newVal);
            } else {
                $sel.val(sortField + '_desc');
            }
            if (ovl30ModalData.length) {
                renderMarketplaceData();
                updateOvl30SortIcons();
            }
        });

        // OV L30 Info Icon Click Handler (SKU-wise)
        $(document).on('click', '.ovl30-info-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            
            const $icon = $(this);
            const sku = $icon.data('sku');
            
            // Validate that we have a valid SKU (prevents wrong row issue)
            if (!sku || sku.trim() === '') {
                console.error('Invalid SKU in click handler');
                return;
            }
            
            // Get data from the icon's data attributes (set in formatter)
            const imagePath = $icon.data('image') || '';
            const inv = parseInt($icon.data('inv')) || 0;
            const l30 = parseInt($icon.data('l30')) || 0;
            const dil = parseFloat($icon.data('dil')) || 0;
            
            // Double-check by getting row data from Tabulator if possible
            try {
                const $row = $icon.closest('.tabulator-row');
                if ($row.length && typeof table !== 'undefined') {
                    const tabulatorRow = table.getRowFromElement($row[0]);
                    if (tabulatorRow) {
                        const rowData = tabulatorRow.getData();
                        // Use row data if available and valid (ensures correct row)
                        if (rowData && rowData.sku && !rowData.is_parent_summary) {
                            loadMarketplaceBreakdown(
                                rowData.sku,
                                rowData.image_path || imagePath,
                                rowData.inventory ?? rowData.inv ?? inv,
                                parseFloat(rowData.overall_l30 || 0),
                                rowData.dil_percent || dil
                            );
                            return;
                        }
                    }
                }
            } catch (err) {
                // If we can't get row data, continue with icon data attributes
                console.warn('Could not get row data from Tabulator, using icon data:', err);
            }
            
            loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil);
        });
        
        // LMP Info Icon Click Handler (from modal breakdown)
        $(document).on('click', '.lmp-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const marketplace = $(this).data('marketplace');
            console.log('Opening LMP modal for:', sku, 'marketplace:', marketplace);
            loadLmpCompetitorsModal(sku);
        });
        
        // LMP Header Link Click Handler (from modal header)
        $(document).on('click', '#modal-header-lmp-link', function(e) {
            e.stopPropagation();
            const sku = $('#modalSkuName').text();
            console.log('Opening LMP modal from header for:', sku);
            loadLmpCompetitorsModal(sku);
        });

        // Missing L main-table dot – directly open missing listings modal without detail modal
        $(document).on('click', '.missing-l-main-dot', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');

            // Show modal with loading state immediately
            $('#missingLSkuName').text(sku);
            $('#missingLTableBody').html(
                '<tr><td colspan="4" class="text-center text-muted py-4">' +
                '<div class="spinner-border spinner-border-sm text-danger me-2" role="status"></div>' +
                'Loading missing listings for ' + sku + '…</td></tr>'
            );
            const missingLModalEl = document.getElementById('missingLModal');
            const existing = bootstrap.Modal.getInstance(missingLModalEl);
            if (existing) { existing.dispose(); }
            new bootstrap.Modal(missingLModalEl, { backdrop: true }).show();

            // Fetch breakdown data and render missing channels
            $.ajax({
                url: '/cvr-master-breakdown?sku=' + encodeURIComponent(sku),
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) {
                    ovl30ModalData = data.slice();

                    const channels = data.filter(function(item) {
                        const price = parseFloat(item.price || 0);
                        if (price > 0) return false;
                        const mp = (item.marketplace || '').toLowerCase();
                        if (mp === 'ebaytwo' && parseFloat(item.act_wt || 0) > 0.75) return false;
                        return true;
                    });

                    let html = '';
                    channels.forEach(function(item) {
                        const l30 = parseInt(item.l30 || 0);
                        const isListed = item.is_listed !== false;
                        const listedBadge = isListed
                            ? '<span class="badge" style="background:#28a745;">Listed</span>'
                            : '<span class="badge bg-danger">Not Listed</span>';
                        html += '<tr>' +
                            '<td><strong>' + (item.marketplace || '-') + '</strong></td>' +
                            '<td class="text-center"><span class="badge bg-danger">Missing Listing</span></td>' +
                            '<td class="text-center">' + listedBadge + '</td>' +
                            '<td class="text-end">' + l30.toLocaleString() + '</td>' +
                            '</tr>';
                    });
                    if (!html) {
                        html = '<tr><td colspan="4" class="text-center text-muted py-4">No missing listings found.</td></tr>';
                    }
                    $('#missingLTableBody').html(html);
                },
                error: function() {
                    $('#missingLTableBody').html(
                        '<tr><td colspan="4" class="text-center text-danger py-4">' +
                        '<i class="fas fa-exclamation-circle me-2"></i>Failed to load data.</td></tr>'
                    );
                }
            });
        });

        // Missing L – show all channels with price
        $(document).on('click', '.missing-l-dot', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku') || $('#modalSkuName').text();
            $('#missingLSkuName').text(sku);

            // Show only channels where price is 0 or null (missing listings)
            // Exception: hide EbayTwo if act_wt > 0.75 LB (weight restriction)
            const channels = ovl30ModalData.filter(item => {
                const price = parseFloat(item.price || 0);
                if (price > 0) return false; // already listed – hide
                const mp = (item.marketplace || '').toLowerCase();
                if (mp === 'ebaytwo' && parseFloat(item.act_wt || 0) > 0.75) return false; // weight restriction
                return true; // missing listing – show
            });

            let html = '';
            channels.forEach(item => {
                const l30 = parseInt(item.l30 || 0);
                const isListed = item.is_listed !== false;
                const listedBadge = isListed
                    ? '<span class="badge" style="background:#28a745;">Listed</span>'
                    : '<span class="badge bg-danger">Not Listed</span>';
                html += `<tr>
                    <td><strong>${item.marketplace || '-'}</strong></td>
                    <td class="text-center"><span class="badge bg-danger">Missing Listing</span></td>
                    <td class="text-center">${listedBadge}</td>
                    <td class="text-end">${l30.toLocaleString()}</td>
                </tr>`;
            });
            if (!html) {
                html = '<tr><td colspan="4" class="text-center text-muted py-4">No missing listings found.</td></tr>';
            }
            $('#missingLTableBody').html(html);

            const missingLModalEl = document.getElementById('missingLModal');
            const existing = bootstrap.Modal.getInstance(missingLModalEl);
            if (existing) { existing.dispose(); }
            new bootstrap.Modal(missingLModalEl, { backdrop: false }).show();
        });

        function loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil) {
            $('#modalSkuName').text(sku);
            
            // Set product image in modal totals row
            const imgElement = $('#modal-product-image');
            if (imagePath) {
                imgElement.attr('src', imagePath);
                imgElement.attr('alt', sku);
                imgElement.show();
            } else {
                imgElement.hide();
            }
            
            // Update header stats with color formatting
            $('#modal-header-inv').text(inv.toLocaleString());
            $('#modal-header-l30').text(l30.toLocaleString());
            
            // Apply color formatting to Dil %
            let dilColor = '';
            const dilValue = parseFloat(dil);
            if (dilValue >= 50) dilColor = '#a00211'; // Dark red
            else if (dilValue >= 30 && dilValue < 50) dilColor = '#dc3545'; // Red
            else if (dilValue >= 20 && dilValue < 30) dilColor = '#ffc107'; // Yellow
            else if (dilValue >= 10 && dilValue < 20) dilColor = '#3591dc'; // Blue
            else if (dilValue >= 5 && dilValue < 10) dilColor = '#28a745'; // Green
            else dilColor = '#e83e8c'; // Pink
            
            $('#modal-header-dil').html(`<span style="${styleForCellColor(dilColor)}">${dilValue.toFixed(1)}%</span>`);
            
            showModalLoading(sku);
            
            const modal = new bootstrap.Modal(document.getElementById('ovl30DetailsModal'));
            modal.show();
            
            // Fetch marketplace breakdown and FBA data
            $.ajax({
                url: '/cvr-master-breakdown?sku=' + encodeURIComponent(sku),
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) { renderMarketplaceData(data); },
                error: function(xhr) { showModalError('Failed to load data'); }
            });
        }

        function showModalLoading(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="18" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="18" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="18" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-circle me-2"></i>${message}
                    </td>
                </tr>
            `);
        }

        let ovl30ModalData = [];

        function getOvl30SortCompare() {
            const val = ($('#ovl30ModalSortBy').val() || 'l30_desc').toString();
            const [field, dir] = val.split('_');
            const asc = dir === 'asc' ? 1 : -1;
            return function(a, b) {
                let cmp = 0;
                if (field === 'l30') {
                    cmp = parseInt(a.l30 || 0) - parseInt(b.l30 || 0);
                } else if (field === 'marketplace') {
                    cmp = (a.marketplace || '').toLowerCase().localeCompare((b.marketplace || '').toLowerCase());
                } else if (field === 'price') {
                    cmp = parseFloat(a.price || 0) - parseFloat(b.price || 0);
                } else if (field === 'views') {
                    cmp = parseInt(a.views || 0) - parseInt(b.views || 0);
                } else if (field === 'cvr') {
                    const va = parseInt(a.views || 0), vb = parseInt(b.views || 0);
                    const la = parseInt(a.l30 || 0), lb = parseInt(b.l30 || 0);
                    const cvrA = va > 0 ? (la / va) * 100 : 0;
                    const cvrB = vb > 0 ? (lb / vb) * 100 : 0;
                    cmp = cvrA - cvrB;
                } else if (field === 'gpft') {
                    cmp = parseFloat(a.gpft || 0) - parseFloat(b.gpft || 0);
                } else if (field === 'ad') {
                    cmp = parseFloat(a.ad || 0) - parseFloat(b.ad || 0);
                } else if (field === 'tacos') {
                    cmp = parseFloat(a.tacos_ch || 0) - parseFloat(b.tacos_ch || 0);
                } else if (field === 'npft') {
                    cmp = parseFloat(a.npft || 0) - parseFloat(b.npft || 0);
                } else if (field === 'sprice') {
                    cmp = parseFloat(a.sprice || 0) - parseFloat(b.sprice || 0);
                } else if (field === 'sgpft') {
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let sgA = 0, sgB = 0;
                    if (sA > 0) sgA = ((sA * marginA - shipA - lpA) / sA) * 100;
                    if (sB > 0) sgB = ((sB * marginB - shipB - lpB) / sB) * 100;
                    cmp = sgA - sgB;
                } else if (field === 'spft') {
                    const l30A = parseInt(a.l30 || 0), l30B = parseInt(b.l30 || 0);
                    const adA = parseFloat(a.ad || 0), adB = parseFloat(b.ad || 0);
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let spA = 0, spB = 0;
                    if (sA > 0) { const sgA = ((sA * marginA - shipA - lpA) / sA) * 100; spA = l30A === 0 ? sgA : sgA - adA; }
                    if (sB > 0) { const sgB = ((sB * marginB - shipB - lpB) / sB) * 100; spB = l30B === 0 ? sgB : sgB - adB; }
                    cmp = spA - spB;
                } else if (field === 'sroi') {
                    const lpA = parseFloat(a.lp || 0), shipA = parseFloat(a.ship || 0), marginA = parseFloat(a.margin || 0.80);
                    const lpB = parseFloat(b.lp || 0), shipB = parseFloat(b.ship || 0), marginB = parseFloat(b.margin || 0.80);
                    const sA = parseFloat(a.sprice || 0), sB = parseFloat(b.sprice || 0);
                    let rA = 0, rB = 0;
                    if (lpA > 0 && sA > 0) rA = ((sA * marginA - lpA - shipA) / lpA) * 100;
                    if (lpB > 0 && sB > 0) rB = ((sB * marginB - lpB - shipB) / lpB) * 100;
                    cmp = rA - rB;
                }
                return cmp * asc;
            };
        }

        function updateOvl30SortIcons() {
            const val = ($('#ovl30ModalSortBy').val() || 'l30_desc').toString();
            const [field, dir] = val.split('_');
            $('#ovl30DetailsModal .modal-vertical-header th.ovl30-sortable').each(function() {
                const $th = $(this);
                const sortField = $th.data('sort');
                const $icon = $th.find('.ovl30-sort-icon');
                $icon.removeClass('fa-sort-up fa-sort-down active').addClass('fa-sort');
                if (sortField === field) {
                    $icon.removeClass('fa-sort').addClass(dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down').addClass('active');
                }
            });
        }

        function renderMarketplaceData(data) {
            if (data && data.length > 0) {
                ovl30ModalData = data.slice();
            }
            const toRender = ovl30ModalData.length ? ovl30ModalData : (data || []);
            if (!toRender.length) {
                showModalEmpty($('#modalSkuName').text());
                return;
            }
            const sorted = toRender.slice();
            sorted.sort(getOvl30SortCompare());
            data = sorted;

            let html = '';
            let totalPrice = 0;
            let totalViews = 0;
            let totalL30 = 0;
            let totalViewsForCVR = 0;  // Exclude Reverb for avg CVR
            let totalL30ForCVR = 0;    // Exclude Reverb for avg CVR
            let totalCVR = 0;
            let totalPftAmount = 0; // Sum of PFT amounts
            let totalNpftAmount = 0; // Sum of NPFT amounts
            let totalSalesAmount = 0; // Sum of sales amounts
            let totalAD = 0;
            let totalTACOS = 0;
            let totalSPRICE = 0;
            let totalSGPFT = 0;
            let totalSPFT = 0;
            let totalSROI = 0;
            let cvrCount = 0;
            let adCount = 0;
            let tacosCount = 0;
            let spriceCount = 0;
            let sgpftCount = 0;
            let spftCount = 0;
            let sroiCount = 0;
            
            data.forEach(item => {
                const isListed = item.is_listed !== false;
                const rowClass = !isListed ? 'table-secondary' : '';
                const textClass = !isListed ? 'text-muted fst-italic' : '';
                
                // Calculate CVR% (L30 / Views * 100)
                const views = parseInt(item.views || 0);
                const l30 = parseInt(item.l30 || 0);
                const cvr = views > 0 ? (l30 / views) * 100 : 0;
                const gpft = parseFloat(item.gpft || 0);
                const ad = parseFloat(item.ad || 0);
                const tacosCh = parseFloat(item.tacos_ch || 0);
                const npft = parseFloat(item.npft || 0);
                
                // SPRICE and calculated values
                const sprice = parseFloat(item.sprice || 0);
                const lp = parseFloat(item.lp || 0);
                const ship = parseFloat(item.ship || 0);
                const margin = parseFloat(item.margin || 0.80);
                
                let sgpft = 0, spft = 0, sroi = 0;
                if (sprice > 0) {
                    sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
                    spft = l30 == 0 ? sgpft : (sgpft - ad);
                    sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
                }
                
                const isEditable = ['amazon', 'doba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'temu2', 'tiktok', 'bestbuy', 'macy', 'reverb', 'tiendamia', 'sb2c', 'shopifyb2c', 'sb2b', 'shopifyb2b', 'fba', 'shein', 'aliexpress', 'purchasingpower'].includes((item.marketplace || '').toLowerCase());
                
                // Color coding for CVR%
                let cvrColor = '';
                if (cvr < 1) cvrColor = '#a00211'; // Dark red
                else if (cvr >= 1 && cvr < 3) cvrColor = '#ffc107'; // Yellow
                else if (cvr >= 3 && cvr < 5) cvrColor = '#28a745'; // Green
                else cvrColor = '#e83e8c'; // Pink
                
                // Color coding for GPFT%, AD%, and NPFT%
                let gpftColor = '';
                let adColor = '';
                let npftColor = '';
                
                if (gpft < 0) gpftColor = '#a00211';
                else if (gpft >= 0 && gpft < 10) gpftColor = '#ffc107';
                else if (gpft >= 10 && gpft < 20) gpftColor = '#3591dc';
                else if (gpft >= 20 && gpft <= 40) gpftColor = '#28a745';
                else gpftColor = '#e83e8c';
                
                // AD% color: lower is better
                if (ad >= 100) adColor = '#a00211'; // Dark red for 100%+
                else if (ad >= 50) adColor = '#dc3545'; // Red
                else if (ad >= 20) adColor = '#ffc107'; // Yellow
                else if (ad >= 10) adColor = '#3591dc'; // Blue
                else if (ad > 0) adColor = '#28a745'; // Green (low is good)
                else adColor = '#6c757d'; // Gray for 0
                
                // TACOS CH color: lower is better (same as AD%)
                let tacosColor = '';
                if (tacosCh >= 100) tacosColor = '#a00211';
                else if (tacosCh >= 50) tacosColor = '#dc3545';
                else if (tacosCh >= 20) tacosColor = '#ffc107';
                else if (tacosCh >= 10) tacosColor = '#3591dc';
                else if (tacosCh > 0) tacosColor = '#28a745';
                else tacosColor = '#6c757d';
                
                if (npft < 0) npftColor = '#a00211';
                else if (npft >= 0 && npft < 10) npftColor = '#ffc107';
                else if (npft >= 10 && npft < 20) npftColor = '#3591dc';
                else if (npft >= 20 && npft <= 40) npftColor = '#28a745';
                else npftColor = '#e83e8c';
                
                // Color coding for SGPFT%, SPFT%, SROI%
                let sgpftColor = '';
                if (sgpft < 0) sgpftColor = '#a00211';
                else if (sgpft >= 0 && sgpft < 10) sgpftColor = '#ffc107';
                else if (sgpft >= 10 && sgpft < 20) sgpftColor = '#3591dc';
                else if (sgpft >= 20 && sgpft <= 40) sgpftColor = '#28a745';
                else sgpftColor = '#e83e8c';
                
                let spftColor = '';
                if (spft < 0) spftColor = '#a00211';
                else if (spft >= 0 && spft < 10) spftColor = '#ffc107';
                else if (spft >= 10 && spft < 20) spftColor = '#3591dc';
                else if (spft >= 20 && spft <= 40) spftColor = '#28a745';
                else spftColor = '#e83e8c';
                
                let sroiColor = '';
                if (sroi < 0) sroiColor = '#a00211';
                else if (sroi >= 0 && sroi < 50) sroiColor = '#ffc107';
                else if (sroi >= 50 && sroi < 100) sroiColor = '#3591dc';
                else if (sroi >= 100 && sroi <= 150) sroiColor = '#28a745';
                else sroiColor = '#e83e8c';
                
                // Add to totals only if listed
                if (isListed) {
                    // Calculate sold amount = price × L30 qty
                    const price = parseFloat(item.price || 0);
                    const soldAmount = price * l30;
                    totalPrice += soldAmount;
                    totalViews += views;
                    totalL30 += l30;
                    // For avg CVR: exclude Reverb views and L30
                    const isReverb = (item.marketplace || '').toLowerCase() === 'reverb';
                    if (!isReverb) {
                        totalViewsForCVR += views;
                        totalL30ForCVR += l30;
                    }
                    
                    // Calculate PFT amount = Sales Amount × GPFT%
                    const pftAmount = soldAmount * (gpft / 100);
                    totalPftAmount += pftAmount;
                    
                    // Calculate NPFT amount = Sales Amount × NPFT%
                    const npftAmount = soldAmount * (npft / 100);
                    totalNpftAmount += npftAmount;
                    
                    totalSalesAmount += soldAmount;
                    
                    if (cvr > 0) {
                        totalCVR += cvr;
                        cvrCount++;
                    }
                    // Always count AD% for average (even if 0)
                    totalAD += ad;
                    adCount++;
                    // Count TACOS CH% if present
                    if (tacosCh !== 0) {
                        totalTACOS += tacosCh;
                        tacosCount++;
                    }
                    if (sprice > 0) {
                        totalSPRICE += sprice;
                        spriceCount++;
                    }
                    if (sgpft !== 0) {
                        totalSGPFT += sgpft;
                        sgpftCount++;
                    }
                    if (spft !== 0) {
                        totalSPFT += spft;
                        spftCount++;
                    }
                    if (sroi !== 0) {
                        totalSROI += sroi;
                        sroiCount++;
                    }
                }
                
                // Determine if upload button should be shown (Amazon, Doba, Walmart, Shopify B2C, Shopify B2B)
                const canPushPrice = ['amazon', 'doba', 'sb2c', 'sb2b', 'reverb', 'fba'].includes((item.marketplace || '').toLowerCase()) && isListed;
                
                html += `
                    <tr class="${rowClass}" data-marketplace="${item.marketplace}" data-sku="${item.sku}" 
                        data-lp="${lp}" data-ship="${ship}" data-ad="${ad}" data-margin="${margin}" data-l30="${l30}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? l30.toLocaleString() : '-'}</td>
                        <td class="text-center">-</td>
                        <td class="text-end ${textClass}">${isListed ? '$' + parseFloat(item.price || 0).toFixed(2) : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? views.toLocaleString() : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && views > 0 ? '<span style="' + styleForCellColor(cvrColor) + '">' + cvr.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && gpft !== 0 ? '<span style="' + styleForCellColor(gpftColor) + '">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '<span style="' + styleForCellColor(adColor) + '">' + ad.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">
                            ${isListed && tacosCh !== 0 ? '<span style="' + styleForCellColor(tacosColor) + '">' + tacosCh.toFixed(1) + '%</span>' : '-'}
                        </td>
                        <td class="text-end ${textClass}">${isListed && npft !== 0 ? '<span style="' + styleForCellColor(npftColor) + '">' + Math.round(npft) + '%</span>' : '-'}</td>
                        <td class="text-center ${textClass}">
                            ${isListed ? 
                                '<i class="fas fa-circle lmp-info-icon" style="cursor: pointer; color: #17a2b8; font-size: 10px;" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                'data-sku="' + item.sku + '" ' +
                                'title="View LMP Data for ' + item.marketplace + '"></i>' 
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}" style="white-space: nowrap;">
                            ${(item.buyer_link || item.seller_link) ? 
                                (item.buyer_link ? '<a href="' + item.buyer_link + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1" title="Buyer link">B</a>' : '') +
                                (item.seller_link ? '<a href="' + item.seller_link + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Seller link">S</a>' : '')
                                : '-'}
                        </td>
                        <td class="text-end ${textClass}">
                            ${isEditable && isListed ? 
                                '<input type="number" class="form-control form-control-sm editable-sprice" value="' + sprice.toFixed(2) + '" step="0.01" style="width:80px;">' 
                                : (sprice > 0 ? '$' + sprice.toFixed(2) : '-')}
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sgpft" style="${styleForCellColor(sgpftColor)}">${Math.round(sgpft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-spft" style="${styleForCellColor(spftColor)}">${Math.round(spft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sroi" style="${styleForCellColor(sroiColor)}">${Math.round(sroi)}%</span>
                        </td>
                        <td class="text-center ${textClass}">
                            ${canPushPrice ? 
                                '<button class="btn btn-sm btn-primary push-price-btn" ' +
                                'data-sku="' + item.sku + '" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                'title="Push price to ' + item.marketplace + '">' +
                                '<i class="fas fa-upload"></i></button>' 
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}">
                            ${item.pushed_by ? 
                                '<div class="pushed-by-info"><strong>' + item.pushed_by + '</strong>' +
                                '<div class="text-muted">' + item.pushed_at + '</div></div>'
                                : '<span class="text-muted">-</span>'}
                        </td>
                    </tr>
                `;
            });
            
            $('#ovl30DetailsTableBody').html(html);
            
            // Calculate averages
            // Avg CVR using CVR formula: (Total L30 / Total Views) × 100 — exclude Reverb
            const avgCVR = totalViewsForCVR > 0 ? (totalL30ForCVR / totalViewsForCVR) * 100 : 0;
            // Avg GPFT% = (Total PFT Amount / Total Sales Amount) × 100
            const avgGPFT = totalSalesAmount > 0 ? (totalPftAmount / totalSalesAmount) * 100 : 0;
            const avgAD = adCount > 0 ? totalAD / adCount : 0;
            const avgTACOS = tacosCount > 0 ? totalTACOS / tacosCount : 0;
            // Avg NPFT% = (Total NPFT Amount / Total Sales Amount) × 100
            const avgNPFT = totalSalesAmount > 0 ? (totalNpftAmount / totalSalesAmount) * 100 : 0;
            const avgSGPFT = sgpftCount > 0 ? totalSGPFT / sgpftCount : 0;
            const avgSPFT = spftCount > 0 ? totalSPFT / spftCount : 0;
            const avgSROI = sroiCount > 0 ? totalSROI / sroiCount : 0;
            
            // Apply color formatting for totals row
            // CVR% color
            let cvrColorTotal = '';
            if (avgCVR < 1) cvrColorTotal = '#a00211';
            else if (avgCVR >= 1 && avgCVR < 3) cvrColorTotal = '#ffc107';
            else if (avgCVR >= 3 && avgCVR < 5) cvrColorTotal = '#28a745';
            else cvrColorTotal = '#e83e8c';
            
            // GPFT%, NPFT%, SGPFT%, SPFT% color
            let gpftColorTotal = '';
            if (avgGPFT < 0) gpftColorTotal = '#a00211';
            else if (avgGPFT >= 0 && avgGPFT < 10) gpftColorTotal = '#ffc107';
            else if (avgGPFT >= 10 && avgGPFT < 20) gpftColorTotal = '#3591dc';
            else if (avgGPFT >= 20 && avgGPFT <= 40) gpftColorTotal = '#28a745';
            else gpftColorTotal = '#e83e8c';
            
            let npftColorTotal = '';
            if (avgNPFT < 0) npftColorTotal = '#a00211';
            else if (avgNPFT >= 0 && avgNPFT < 10) npftColorTotal = '#ffc107';
            else if (avgNPFT >= 10 && avgNPFT < 20) npftColorTotal = '#3591dc';
            else if (avgNPFT >= 20 && avgNPFT <= 40) npftColorTotal = '#28a745';
            else npftColorTotal = '#e83e8c';
            
            let sgpftColorTotal = '';
            if (avgSGPFT < 0) sgpftColorTotal = '#a00211';
            else if (avgSGPFT >= 0 && avgSGPFT < 10) sgpftColorTotal = '#ffc107';
            else if (avgSGPFT >= 10 && avgSGPFT < 20) sgpftColorTotal = '#3591dc';
            else if (avgSGPFT >= 20 && avgSGPFT <= 40) sgpftColorTotal = '#28a745';
            else sgpftColorTotal = '#e83e8c';
            
            let spftColorTotal = '';
            if (avgSPFT < 0) spftColorTotal = '#a00211';
            else if (avgSPFT >= 0 && avgSPFT < 10) spftColorTotal = '#ffc107';
            else if (avgSPFT >= 10 && avgSPFT < 20) spftColorTotal = '#3591dc';
            else if (avgSPFT >= 20 && avgSPFT <= 40) spftColorTotal = '#28a745';
            else spftColorTotal = '#e83e8c';
            
            // AD% color (lower is better)
            let adColorTotal = '';
            if (avgAD >= 100) adColorTotal = '#a00211';
            else if (avgAD >= 50) adColorTotal = '#dc3545';
            else if (avgAD >= 20) adColorTotal = '#ffc107';
            else if (avgAD >= 10) adColorTotal = '#3591dc';
            else if (avgAD > 0) adColorTotal = '#28a745';
            else adColorTotal = '#6c757d';
            
            // TACOS CH color (lower is better, same as AD%)
            let tacosColorTotal = '';
            if (avgTACOS >= 100) tacosColorTotal = '#a00211';
            else if (avgTACOS >= 50) tacosColorTotal = '#dc3545';
            else if (avgTACOS >= 20) tacosColorTotal = '#ffc107';
            else if (avgTACOS >= 10) tacosColorTotal = '#3591dc';
            else if (avgTACOS > 0) tacosColorTotal = '#28a745';
            else tacosColorTotal = '#6c757d';
            
            // SROI% color
            let sroiColorTotal = '';
            if (avgSROI < 0) sroiColorTotal = '#a00211';
            else if (avgSROI >= 0 && avgSROI < 50) sroiColorTotal = '#ffc107';
            else if (avgSROI >= 50 && avgSROI < 100) sroiColorTotal = '#3591dc';
            else if (avgSROI >= 100 && avgSROI <= 150) sroiColorTotal = '#28a745';
            else sroiColorTotal = '#e83e8c';
            
            // Update totals with color formatting
            // Calculate average price = Total Sold Amount / Total Sold Qty (L30)
            const avgPrice = totalL30 > 0 ? totalPrice / totalL30 : 0;
            $('#modal-total-price').text('$' + avgPrice.toFixed(2));
            $('#modal-total-views').text(totalViews.toLocaleString());
            $('#modal-total-l30').text(totalL30.toLocaleString());
            
            // Update header L30 to match the calculated total from breakdown (fixes L30 diff issue)
            $('#modal-header-l30').text(totalL30.toLocaleString());
            
            $('#modal-avg-cvr').html(`<span style="${styleForCellColor(cvrColorTotal)}">${avgCVR.toFixed(1)}%</span>`);
            $('#modal-avg-gpft').html(`<span style="${styleForCellColor(gpftColorTotal)}">${avgGPFT.toFixed(1)}%</span>`);
            $('#modal-avg-ad').html(`<span style="${styleForCellColor(adColorTotal)}">${avgAD.toFixed(1)}%</span>`);
            $('#modal-avg-tacos').html(`<span style="${styleForCellColor(tacosColorTotal)}">${avgTACOS.toFixed(1)}%</span>`);
            $('#modal-avg-npft').html(`<span style="${styleForCellColor(npftColorTotal)}">${avgNPFT.toFixed(1)}%</span>`);
            
            $('#modal-avg-sprice').text('$' + (spriceCount > 0 ? totalSPRICE / spriceCount : 0).toFixed(2));
            $('#modal-avg-sgpft').html(`<span style="${styleForCellColor(sgpftColorTotal)}">${avgSGPFT.toFixed(1)}%</span>`);
            $('#modal-avg-spft').html(`<span style="${styleForCellColor(spftColorTotal)}">${avgSPFT.toFixed(1)}%</span>`);
            $('#modal-avg-sroi').html(`<span style="${styleForCellColor(sroiColorTotal)}">${avgSROI.toFixed(1)}%</span>`);
            updateOvl30SortIcons();
        }

        // ==================== TABULATOR INITIALIZATION ====================
        
        table = new Tabulator("#cvr-table", {
            ajaxURL: "/cvr-master-data-json",
            ajaxSorting: false,
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "top",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{ column: "parent", dir: "asc" }],
            rowFormatter: function(row) {
                const data = row.getData();
                if (data.is_parent_summary === true) {
                    row.getElement().style.backgroundColor = "#bde0ff";
                    row.getElement().style.fontWeight = "bold";
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
                {
                    title: "#",
                    field: "_selected",
                    headerSort: false,
                    minWidth: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const checked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="row-select-cb" data-sku="${(sku || '').replace(/"/g, '&quot;')}" ${checked}>`;
                    },
                    titleFormatter: function(column) {
                        const allChecked = isAllFilteredSelected();
                        return `<input type="checkbox" class="select-all-cb" title="Select all filtered rows SKUs (excludes parent rows)" ${allChecked ? 'checked' : ''}>`;
                    }
                },
                {
                    title: "Image",
                    field: "image_path",
                    sorter: "string",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    minWidth: 60
                },
                {
                    title: "P",
                    field: "parent",
                    sorter: "string",
                    minWidth: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const parent = cell.getValue();
                        if (!parent) {
                            return '<span class="parent-sku-dot no-parent" title="No parent"></span>';
                        }
                        return `<span class="parent-sku-dot parent-sku-dot-btn" 
                                    data-parent="${parent.replace(/"/g, '&quot;')}" 
                                    title="Click to view SKUs for parent: ${parent.replace(/"/g, '&quot;')}"></span>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent",
                    sorter: "string",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent",
                    minWidth: 80,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const parent = rowData.parent;
                        if (parent === undefined || parent === null || (typeof parent === 'string' && !parent.trim())) return '-';
                        return (typeof parent === 'string' ? parent : String(parent)).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                    }
                },
                {
                    title: "SKU",
                    field: "sku",
                    sorter: "string",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold pricing-master-sku-col",
                    tooltip: true,
                    frozen: true,
                    minWidth: 120,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        let html = `<span class="pricing-master-sku-text">${sku}</span>`;
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}" title="Copy SKU"></i>`;
                        return html;
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #dc3545; font-weight: 600;">0</span>' : `<span style="font-weight: 600;">${value}</span>`;
                        const parentEsc = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEsc = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="inv" data-parent="' + parentEsc + '" data-sku="' + skuEsc + '" style="cursor:pointer;color:#4361ee;font-size:8px;vertical-align:middle;" title="View Inv graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="inv" data-sku="' + skuEsc + '" style="cursor:pointer;color:#4361ee;font-size:8px;vertical-align:middle;" title="View Inv graph (Rolling L30)"></i>';
                        }
                        return html;
                    }
                },
                {
                    title: "OV L30 + FBA",
                    field: "ov_l30_plus_fba",
                    hozAlign: "center",
                    minWidth: 100,
                    sorter: "number",
                    headerTooltip: "Shopify OV L30 plus FBA L30: Product SKU is resolved to an FBA listing (FbaInventoryService, same as FBA Dispatch), then fba_monthly_sales.l30_units for that MSKU.",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "overall_l30",
                    hozAlign: "center",
                    minWidth: 80,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const parentEsc = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEsc = (sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            return `<span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="ov_l30" data-parent="${parentEsc}" data-sku="${skuEsc}" style="cursor:pointer;color:#28a745;font-size:8px;vertical-align:middle;" title="View OV L30 graph (Parent, Rolling L30)"></i>`;
                        }
                        return `<span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="ov_l30" data-sku="${skuEsc}" style="cursor:pointer;color:#28a745;font-size:8px;vertical-align:middle;" title="View OV L30 graph (Rolling L30)"></i>`;
                    }
                },
                {
                    title: "SW L30",
                    field: "m_l30",
                    hozAlign: "center",
                    minWidth: 80,
                    sorter: "number",
                    headerTooltip: "SW L30: total L30 summed across marketplace channels (Amazon, eBay, Walmart, Temu, Temu 2, Macy's, Reverb, etc.). Per-channel values appear in the SKU detail modal. Green when SW L30 equals OV L30; red otherwise.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sw = parseFloat(cell.getValue() || 0);
                        const ov = parseFloat(rowData.overall_l30 ?? 0);
                        const match = Math.abs(sw - ov) < 0.01;
                        const color = match ? '#28a745' : '#dc3545';
                        return `<span style="font-weight: 600; color: ${color};">${sw}</span>`;
                    }
                },
                {
                    title: "Details",
                    field: "details_dot",
                    headerSort: false,
                    hozAlign: "center",
                    minWidth: 52,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) return '';
                        const sku = rowData.sku;
                        const imagePath = rowData.image_path || '';
                        const inv = rowData.inventory ?? rowData.inv ?? 0;
                        const value = parseFloat(cell.getRow().getData().overall_l30 || 0);
                        const dilPercent = rowData.dil_percent || 0;
                        return `<i class="fas fa-info-circle text-info ovl30-info-icon" 
                               style="cursor: pointer; font-size: 12px;" 
                               data-sku="${sku}"
                               data-image="${imagePath}"
                               data-inv="${inv}"
                               data-l30="${value}"
                               data-dil="${dilPercent}"
                               title="View breakdown for ${sku}"></i>`;
                    }
                },
                {
title: "Dil %",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';

                        if (value === 0) color = '#6c757d';
                        else if (value < 16.7) color = '#a00211';
                        else if (value >= 16.7 && value < 25) color = '#ffc107';
                        else if (value >= 25 && value < 50) color = '#28a745';
                        else color = '#e83e8c';

                        let html = `<span style="${styleForCellColor(color)}">${Math.round(value)}%</span>`;
                        const parentEscDil = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscDil = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="dil" data-parent="' + parentEscDil + '" data-sku="' + skuEscDil + '" style="cursor:pointer;color:#0d6efd;font-size:8px;vertical-align:middle;" title="View DIL history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="dil" data-sku="' + skuEscDil + '" style="cursor:pointer;color:#0d6efd;font-size:8px;vertical-align:middle;" title="View DIL history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 60
                },
                {
                    title: "Missing L",
                    field: "missing_l",
                    hozAlign: "center",
                    minWidth: 55,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) return '';
                        const hasMissing = cell.getValue();
                        const color = hasMissing ? '#dc3545' : '#28a745';
                        const borderColor = hasMissing ? '#a00211' : '#1a7a38';
                        const title = hasMissing ? 'Has missing listings – click to view breakdown' : 'All channels have a price';
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const imagePath = (rowData.image_path || '').replace(/"/g, '&quot;');
                        const inv = rowData.inventory ?? rowData.inv ?? 0;
                        const l30 = rowData.overall_l30 || 0;
                        const dil = rowData.dil_percent || 0;
                        return `<span class="missing-l-main-dot"
                            data-sku="${sku}"
                            data-image="${imagePath}"
                            data-inv="${inv}"
                            data-l30="${l30}"
                            data-dil="${dil}"
                            style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${color};cursor:pointer;border:1px solid ${borderColor};"
                            title="${title}"></span>`;
                    }
                },
                {
                    title: "CVR",
                    field: "avg_cvr",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        if (value === 0) color = '#6c757d';
                        else if (value < 1) color = '#a00211';
                        else if (value >= 1 && value < 3) color = '#ffc107';
                        else if (value >= 3 && value < 5) color = '#28a745';
                        else color = '#e83e8c';
                        let html = `<span style="${styleForCellColor(color)}">${value.toFixed(1)}%</span>`;
                        const parentEscCvr = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscCvr = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="cvr" data-parent="' + parentEscCvr + '" data-sku="' + skuEscCvr + '" style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;" title="View CVR graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="cvr" data-sku="' + skuEscCvr + '" style="cursor:pointer;color:#ff9c00;font-size:8px;vertical-align:middle;" title="View CVR graph (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "Amz Price",
                    field: "amazon_price",
                    hozAlign: "right",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = cell.getValue();
                        let html = '';
                        if (value == null || value === '' || parseFloat(value) <= 0) {
                            html = '-';
                        } else {
                            const num = parseFloat(value);
                            html = `<span style="font-weight: 600;">$` + num.toFixed(2) + '</span>';
                        }
                        const parentEscAmz = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscAmz = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="amz_price" data-parent="' + parentEscAmz + '" data-sku="' + skuEscAmz + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Amz Price history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="amz_price" data-sku="' + skuEscAmz + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Amz Price history (Rolling L30)"></i>';
                        }
                        return html;
                    }
                },
                {
                    title: "Amz GPFT%",
                    field: "amz_pft",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 10) color = '#a00211';
                        else if (pct >= 10 && pct < 20) color = '#ffc107';
                        else if (pct >= 20 && pct < 50) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${pct.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "Amz GROI%",
                    field: "amz_roi",
                    hozAlign: "center",
                    minWidth: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 50) color = '#a00211';
                        else if (pct >= 50 && pct < 100) color = '#ffc107';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${pct.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: "Avg Price",
                    field: "avg_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseFloat(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #6c757d;">-</span>' : `<span style="font-weight: 600;">$${value.toFixed(2)}</span>`;
                        const parentEscPrice = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscPrice = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="price" data-parent="' + parentEscPrice + '" data-sku="' + skuEscPrice + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Price graph (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="price" data-sku="' + skuEscPrice + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Price graph (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg PFT",
                    field: "avg_pft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for PFT% (Net Profit)
                        if (value < 0) color = '#a00211';
                        else if (value >= 0 && value < 10) color = '#ffc107';
                        else if (value >= 10 && value < 20) color = '#3591dc';
                        else if (value >= 20 && value <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="${styleForCellColor(color)}">${Math.round(value)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Sprice",
                    field: "sprice_dot",
                    headerSort: false,
                    minWidth: 44,
                    hozAlign: "center",
                    formatter: function(cell) {
                        return '<span style="cursor:pointer;color:#0d6efd;font-size:14px;line-height:1;" title="Click to show Sprice details">●</span>';
                    }
                },
                {
                    title: "Amz SPRICE",
                    field: "amazon_sprice",
                    visible: false,
                    hozAlign: "right",
                    minWidth: 70,
                    sorter: "number",
                    editor: "number",
                    editorParams: { step: 0.01, min: 0 },
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        return d.is_parent_summary !== true && d.sku && d.sku.indexOf('PARENT') === -1;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (rowData.is_parent_summary === true) {
                            const value = cell.getValue();
                            if (value == null || value === '' || parseFloat(value) <= 0) return '-';
                            return '<span style="font-weight: 600;">$' + parseFloat(value).toFixed(2) + '</span>';
                        }
                        const value = cell.getValue();
                        if (value == null || value === '' || parseFloat(value) <= 0) return '-';
                        const num = parseFloat(value);
                        return `<span style="font-weight: 600;">$` + num.toFixed(2) + '</span>';
                    }
                },
                {
                    title: "Amz SGPFT%",
                    field: "amazon_sgpft",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 10) color = '#ffc107';
                        else if (pct >= 10 && pct < 20) color = '#3591dc';
                        else if (pct >= 20 && pct <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Amz SPFT%",
                    field: "amazon_spft",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 10) color = '#ffc107';
                        else if (pct >= 10 && pct < 20) color = '#3591dc';
                        else if (pct >= 20 && pct <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Amz SROI%",
                    field: "amazon_sroi",
                    visible: false,
                    hozAlign: "center",
                    minWidth: 70,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '-';
                        const pct = parseFloat(value);
                        let color = '';
                        if (pct < 0) color = '#a00211';
                        else if (pct >= 0 && pct < 50) color = '#ffc107';
                        else if (pct >= 50 && pct < 100) color = '#3591dc';
                        else if (pct >= 100 && pct <= 150) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="${styleForCellColor(color)}">${Math.round(pct)}%</span>`;
                    }
                },
                {
                    title: "Rating",
                    field: "rating",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "Rating and reviews from Jungle Scout",
                    formatter: function(cell) {
                        const rating = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const reviews = rowData.reviews || 0;
                        let html = '';
                        if (!rating || rating === 0) {
                            html = '<span style="color: #6c757d;">-</span>';
                        } else {
                            let ratingColor = '';
                            const ratingVal = parseFloat(rating);
                            if (ratingVal < 3) ratingColor = '#a00211';
                            else if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                            else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                            else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                            else ratingColor = '#e83e8c';
                            const reviewColor = reviews < 4 ? '#a00211' : '#6c757d';
                            html = `<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                <span style="${styleForCellColor(ratingColor)}"><i class="fa fa-star"></i> ${parseFloat(rating).toFixed(1)}</span>
                                <span style="font-size: 11px; ${styleForCellColor(reviewColor)}">${parseInt(reviews).toLocaleString()} reviews</span>
                            </div>`;
                        }
                        const parentEscRat = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscRat = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="rating" data-parent="' + parentEscRat + '" data-sku="' + skuEscRat + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Rating history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="rating" data-sku="' + skuEscRat + '" style="cursor:pointer;color:#e83e8c;font-size:8px;vertical-align:middle;" title="View Rating history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 70
                },
                {
                    title: "AVG LQS",
                    field: "listing_quality_score",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "5core Listing Quality Score from Jungle Scout",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value == null || value === '') return '<span style="color: #6c757d;">-</span>';
                        const num = typeof value === 'number' ? value : parseFloat(value);
                        if (isNaN(num)) return '<span style="color: #6c757d;">-</span>';
                        return '<span style="font-weight: 600;">' + num + '</span>';
                    },
                    minWidth: 50
                },
                {
                    title: "Total Views",
                    field: "total_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseInt(cell.getValue() || 0);
                        let html = value === 0 ? '<span style="color: #6c757d;">0</span>' : `<span style="font-weight: 600;">${value.toLocaleString()}</span>`;
                        const parentEscTv = (rowData.parent || '').replace(/"/g, '&quot;');
                        const skuEscTv = (rowData.sku || '').replace(/"/g, '&quot;');
                        if (rowData.is_parent_summary === true) {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="total_views" data-parent="' + parentEscTv + '" data-sku="' + skuEscTv + '" style="cursor:pointer;color:#17a2b8;font-size:8px;vertical-align:middle;" title="View Total Views history (Parent, Rolling L30)"></i>';
                        } else {
                            html += ' <i class="fas fa-circle pricing-master-chart-link ms-1" data-metric="total_views" data-sku="' + skuEscTv + '" style="cursor:pointer;color:#17a2b8;font-size:8px;vertical-align:middle;" title="View Total Views history (Rolling L30)"></i>';
                        }
                        return html;
                    },
                    minWidth: 80
                },
                {
                    title: "Amz LMP",
                    field: "amazon_lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const skuEnc = encodeURIComponent(rowData.sku || '');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            const url = '/repricer/amazon-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open Amazon repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="amazon" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 70
                },
                {
                    title: "eBay LMP",
                    field: "ebay_lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = (rowData.sku || '').replace(/"/g, '&quot;');
                        const skuEnc = encodeURIComponent(rowData.sku || '');
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        const price = value != null && value !== '' ? parseFloat(value) : null;
                        if (price == null || price <= 0) {
                            const url = '/repricer/ebay-search' + (skuEnc ? '?sku=' + skuEnc : '');
                            return '<a href="' + url + '" target="_blank" rel="noopener" class="lmp-no-data-link" title="No LMP – open eBay repricer search"><i class="fas fa-circle" style="color: #ff9c00; font-size: 10px;"></i></a>';
                        }
                        const avgPrice = parseFloat(rowData.avg_price || 0);
                        const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                        return `<a href="#" class="lmp-price-link" data-sku="${sku}" data-marketplace="ebay" style="${styleForCellColor(color)} text-decoration: none; cursor: pointer;">$${price.toFixed(2)}</a>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg GPFT",
                    field: "avg_gpft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for GPFT%
                        if (value < 0) color = '#a00211';
                        else if (value >= 0 && value < 10) color = '#ffc107';
                        else if (value >= 10 && value < 20) color = '#3591dc';
                        else if (value >= 20 && value <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="${styleForCellColor(color)}">${Math.round(value)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Avg AD",
                    field: "avg_ad",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for AD% (lower is better)
                        if (value >= 100) color = '#a00211';
                        else if (value >= 50) color = '#dc3545';
                        else if (value >= 20) color = '#ffc107';
                        else if (value >= 10) color = '#3591dc';
                        else if (value > 0) color = '#28a745';
                        else color = '#6c757d';
                        
                        return `<span style="${styleForCellColor(color)}">${value.toFixed(1)}%</span>`;
                    },
                    minWidth: 70
                },
                {
                    title: "Sh L30",
                    field: "shein_l30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#e83e8c;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                },
                {
                    title: "AE L30",
                    field: "ae_l30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#ff6600;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                },
                {
                    title: "PP L30",
                    field: "pp_l30",
                    hozAlign: "center",
                    sorter: "number",
                    tooltip: "Purchasing Power last-30-days sales",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) return '<span style="color:#6c757d;">0</span>';
                        return `<span style="color:#6f42c1;font-weight:600;">${value.toLocaleString()}</span>`;
                    },
                    minWidth: 60
                }
            ]
        });

        // Row reference for Sprice modal save (set when modal opens, used on blur)
        let spriceModalCurrentRow = null;

        // Sprice dot click: open modal with editable Amz SPRICE and instant SGPFT/SPFT/SROI
        table.on('cellClick', function(e, cell) {
            if (cell.getField() !== 'sprice_dot') return;
            const row = cell.getRow();
            const d = row.getData();
            if (d.is_parent_summary === true) return;
            spriceModalCurrentRow = row;
            const skuName = (d.sku || '-') + (d.parent ? ' (' + d.parent + ')' : '');
            const lp = parseFloat(d.amazon_lp) || 0;
            const ship = parseFloat(d.amazon_ship) || 0;
            const ad = parseFloat(d.amazon_ad) || 0;
            const margin = parseFloat(d.amazon_margin) || 0.80;
            const l30 = parseInt(d.amazon_l30, 10) || 0;
            const sprice = d.amazon_sprice;
            const sgpft = d.amazon_sgpft;
            const spft = d.amazon_spft;
            const sroi = d.amazon_sroi;
            $('#spriceModalSkuName').text(skuName);
            const $modal = $('#spriceDetailsModal');
            $modal.attr('data-sku', d.sku || '');
            $modal.attr('data-lp', lp);
            $modal.attr('data-ship', ship);
            $modal.attr('data-ad', ad);
            $modal.attr('data-margin', margin);
            $modal.attr('data-l30', l30);
            const spriceVal = (sprice != null && sprice !== '' && parseFloat(sprice) > 0) ? parseFloat(sprice) : '';
            $('#spriceModalAmzSpriceInput').val(spriceVal === '' ? '' : spriceVal.toFixed(2));
            function updateSpriceModalCalculated(spriceNum) {
                if (spriceNum <= 0) {
                    applyCellColor($('#spriceModalSgpft'), '#6c757d');
                    $('#spriceModalSgpft').text('-');
                    applyCellColor($('#spriceModalSpft'), '#6c757d');
                    $('#spriceModalSpft').text('-');
                    applyCellColor($('#spriceModalSroi'), '#6c757d');
                    $('#spriceModalSroi').text('-');
                    return;
                }
                const sgpftVal = ((spriceNum * margin - ship - lp) / spriceNum) * 100;
                const spftVal = l30 === 0 ? sgpftVal : (sgpftVal - ad);
                const sroiVal = lp > 0 ? ((spriceNum * margin - lp - ship) / lp) * 100 : 0;
                applyCellColor($('#spriceModalSgpft'), getSgpftSpftColor(sgpftVal));
                $('#spriceModalSgpft').text(Math.round(sgpftVal) + '%');
                applyCellColor($('#spriceModalSpft'), getSgpftSpftColor(spftVal));
                $('#spriceModalSpft').text(Math.round(spftVal) + '%');
                applyCellColor($('#spriceModalSroi'), getSroiColor(sroiVal));
                $('#spriceModalSroi').text(Math.round(sroiVal) + '%');
            }
            if (spriceVal !== '') updateSpriceModalCalculated(parseFloat(spriceVal));
            else {
                applyCellColor($('#spriceModalSgpft'), '#6c757d');
                $('#spriceModalSgpft').text('-');
                applyCellColor($('#spriceModalSpft'), '#6c757d');
                $('#spriceModalSpft').text('-');
                applyCellColor($('#spriceModalSroi'), '#6c757d');
                $('#spriceModalSroi').text('-');
            }
            new bootstrap.Modal(document.getElementById('spriceDetailsModal')).show();
        });

        // Sprice modal: top-center position, draggable by header, and load FBA data
        $('#spriceDetailsModal').on('shown.bs.modal', function() {
            const modal = document.getElementById('spriceDetailsModal');
            const dialog = modal.querySelector('.modal-dialog');
            if (!dialog) return;
            dialog.style.position = 'fixed';
            dialog.style.left = '50%';
            dialog.style.top = '1.5rem';
            dialog.style.transform = 'translateX(-50%)';
            dialog.style.margin = '0';
        });
        (function() {
            let startX = 0, startY = 0, startLeft = 0, startTop = 0;
            const modal = document.getElementById('spriceDetailsModal');
            if (!modal) return;
            const header = modal.querySelector('.sprice-modal-drag-header');
            const dialog = modal.querySelector('.modal-dialog');
            if (!header || !dialog) return;
            function onMove(e) {
                dialog.style.left = (startLeft + (e.clientX - startX)) + 'px';
                dialog.style.top = (startTop + (e.clientY - startY)) + 'px';
                dialog.style.transform = 'none';
            }
            function onUp() {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            }
            header.addEventListener('mousedown', function(e) {
                if (e.target.closest('.btn-close')) return;
                const r = dialog.getBoundingClientRect();
                startLeft = r.left;
                startTop = r.top;
                startX = e.clientX;
                startY = e.clientY;
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                e.preventDefault();
            });
        })();

        // Amz SPRICE edited in table: save and recalculate SGPFT, SPFT, SROI
        table.on('cellEdited', function(cell) {
            if (cell.getField() !== 'amazon_sprice') return;
            const row = cell.getRow();
            const rowData = row.getData();
            if (rowData.is_parent_summary === true) return;
            const sku = rowData.sku;
            const sprice = parseFloat(cell.getValue()) || 0;
            if (sprice <= 0) return;
            const lp = parseFloat(rowData.amazon_lp) || 0;
            const ship = parseFloat(rowData.amazon_ship) || 0;
            const ad = parseFloat(rowData.amazon_ad) || 0;
            const margin = parseFloat(rowData.amazon_margin) || 0.80;
            const l30 = parseInt(rowData.amazon_l30, 10) || 0;
            const sgpft = sprice > 0 ? ((sprice * margin - ship - lp) / sprice) * 100 : 0;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: 'amazon',
                    sprice: sprice,
                    sgpft: Math.round(sgpft * 100) / 100,
                    spft: Math.round(spft * 100) / 100,
                    sroi: Math.round(sroi * 100) / 100,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    row.update({
                        amazon_sgpft: Math.round(sgpft * 100) / 100,
                        amazon_spft: Math.round(spft * 100) / 100,
                        amazon_sroi: Math.round(sroi * 100) / 100
                    });
                    showToast('Amz SPRICE saved', 'success');
                },
                error: function() {
                    showToast('Failed to save Amz SPRICE', 'error');
                }
            });
        });

        // ==================== TABLE EVENT HANDLERS ====================
        
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
        });
        $('#parent-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("parent", "like", value);
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        // ==================== SPRICE EDITING ====================
        
        // Helper: same color logic as modal table for SGPFT%, SPFT%, SROI%
        function getSgpftSpftColor(pct) {
            if (pct < 0) return '#a00211';
            if (pct >= 0 && pct < 10) return '#ffc107';
            if (pct >= 10 && pct < 20) return '#3591dc';
            if (pct >= 20 && pct <= 40) return '#28a745';
            return '#e83e8c';
        }
        function getSroiColor(pct) {
            if (pct < 0) return '#a00211';
            if (pct >= 0 && pct < 50) return '#ffc107';
            if (pct >= 50 && pct < 100) return '#3591dc';
            if (pct >= 100 && pct <= 150) return '#28a745';
            return '#e83e8c';
        }
        // Dark mustard text (no yellow background)
        const darkMustard = '#ff9c00'; // orange/mustard accent
        function styleForCellColor(c) {
            if (!c) return 'font-weight:600;';
            if (c === '#ffc107') return 'color:' + darkMustard + ';font-weight:600;';
            return 'color:' + c + ';font-weight:600;';
        }
        function applyCellColor($el, c) {
            if (c === '#ffc107') { $el.css({ backgroundColor: '', color: darkMustard }); }
            else { $el.css({ backgroundColor: '', color: c || '#6c757d' }); }
        }

        // Sprice modal: instant recalc when Amz SPRICE input changes
        $(document).on('input', '.sprice-modal-sprice-input', function() {
            const $modal = $('#spriceDetailsModal');
            const sprice = parseFloat($(this).val()) || 0;
            const lp = parseFloat($modal.attr('data-lp')) || 0;
            const ship = parseFloat($modal.attr('data-ship')) || 0;
            const ad = parseFloat($modal.attr('data-ad')) || 0;
            const margin = parseFloat($modal.attr('data-margin')) || 0.80;
            const l30 = parseInt($modal.attr('data-l30'), 10) || 0;
            if (sprice <= 0) {
                applyCellColor($('#spriceModalSgpft'), '#6c757d');
                $('#spriceModalSgpft').text('-');
                applyCellColor($('#spriceModalSpft'), '#6c757d');
                $('#spriceModalSpft').text('-');
                applyCellColor($('#spriceModalSroi'), '#6c757d');
                $('#spriceModalSroi').text('-');
                return;
            }
            const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            applyCellColor($('#spriceModalSgpft'), getSgpftSpftColor(sgpft));
            $('#spriceModalSgpft').text(Math.round(sgpft) + '%');
            applyCellColor($('#spriceModalSpft'), getSgpftSpftColor(spft));
            $('#spriceModalSpft').text(Math.round(spft) + '%');
            applyCellColor($('#spriceModalSroi'), getSroiColor(sroi));
            $('#spriceModalSroi').text(Math.round(sroi) + '%');
        });

        // Sprice modal: save on blur and update table row
        $(document).on('blur', '.sprice-modal-sprice-input', function() {
            const input = $(this);
            const sprice = parseFloat(input.val()) || 0;
            const $modal = $('#spriceDetailsModal');
            const sku = $modal.attr('data-sku');
            if (!sku || sprice <= 0) return;
            const lp = parseFloat($modal.attr('data-lp')) || 0;
            const ship = parseFloat($modal.attr('data-ship')) || 0;
            const ad = parseFloat($modal.attr('data-ad')) || 0;
            const margin = parseFloat($modal.attr('data-margin')) || 0.80;
            const l30 = parseInt($modal.attr('data-l30'), 10) || 0;
            const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
            const spft = l30 === 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: 'amazon',
                    sprice: sprice,
                    sgpft: Math.round(sgpft * 100) / 100,
                    spft: Math.round(spft * 100) / 100,
                    sroi: Math.round(sroi * 100) / 100,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    if (spriceModalCurrentRow) {
                        spriceModalCurrentRow.update({
                            amazon_sprice: Math.round(sprice * 100) / 100,
                            amazon_sgpft: Math.round(sgpft * 100) / 100,
                            amazon_spft: Math.round(spft * 100) / 100,
                            amazon_sroi: Math.round(sroi * 100) / 100
                        });
                    }
                    showToast('Sprice saved', 'success');
                },
                error: function() {
                    showToast('Failed to save Sprice', 'error');
                }
            });
        });

        // Real-time calculation when SPRICE changes (OVL30 modal – same formula as main table)
        $(document).on('input', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sprice = parseFloat(input.val()) || 0;
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            const $sgpftSpan = row.find('.calculated-sgpft');
            const $spftSpan = row.find('.calculated-spft');
            const $roiSpan = row.find('.calculated-sroi');
            
            if (sprice > 0) {
                const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
                const spft = l30 == 0 ? sgpft : (sgpft - ad);
                const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
                
                applyCellColor($sgpftSpan, getSgpftSpftColor(sgpft));
                $sgpftSpan.text(Math.round(sgpft) + '%');
                applyCellColor($spftSpan, getSgpftSpftColor(spft));
                $spftSpan.text(Math.round(spft) + '%');
                applyCellColor($roiSpan, getSroiColor(sroi));
                $roiSpan.text(Math.round(sroi) + '%');
            } else {
                applyCellColor($sgpftSpan, '#6c757d');
                $sgpftSpan.text('-');
                applyCellColor($spftSpan, '#6c757d');
                $spftSpan.text('-');
                applyCellColor($roiSpan, '#6c757d');
                $roiSpan.text('-');
            }
        });
        
        // Auto-save on blur
        $(document).on('blur', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sku = row.attr('data-sku');
            const marketplace = row.attr('data-marketplace');
            const sprice = parseFloat(input.val()) || 0;
            
            if (sprice === 0) return;
            
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            const sgpft = sprice > 0 ? ((sprice * margin - ship - lp) / sprice) * 100 : 0;
            const spft = l30 == 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            
            input.css('border-color', '#ff9c00');
            
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: {
                    sku: sku,
                    marketplace: marketplace,
                    sprice: sprice,
                    sgpft: sgpft,
                    spft: spft,
                    sroi: sroi,
                    amazon_margin: margin,
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    input.css('border-color', '#28a745');
                    setTimeout(() => input.css('border-color', ''), 1000);
                    showToast('Saved!', 'success');
                },
                error: function() {
                    input.css('border-color', '#dc3545');
                    showToast('Failed to save', 'error');
                }
            });
        });
        
        // ==================== PRICE PUSH TO AMAZON ====================
        
        // Push price button click handler
        $(document).on('click', '.push-price-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const row = btn.closest('tr');
            const sku = btn.data('sku');
            const marketplace = btn.data('marketplace');
            const priceInput = row.find('.editable-sprice');
            const price = parseFloat(priceInput.val()) || 0;
            
            if (price <= 0) {
                showToast('Please enter a valid price greater than 0', 'error');
                priceInput.focus();
                return;
            }
            
            // Confirm before pushing
            if (!confirm(`Push price $${price.toFixed(2)} to ${marketplace.toUpperCase()} for SKU: ${sku}?`)) {
                return;
            }
            
            // Disable button and show loading state
            const originalHtml = btn.html();
            btn.prop('disabled', true);
            btn.html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/cvr-master-push-price',
                method: 'POST',
                data: {
                    sku: sku,
                    price: price,
                    marketplace: marketplace,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        btn.html('<i class="fas fa-check"></i>');
                        btn.removeClass('btn-primary').addClass('btn-success');
                        
                        // Reload modal data to show pushed_by info
                        setTimeout(() => {
                            const currentSku = $('#modalSkuName').text();
                            const currentImage = $('#modal-product-image').attr('src');
                            const currentInv = $('#modal-header-inv').text().replace(/,/g, '');
                            const currentL30 = $('#modal-header-l30').text().replace(/,/g, '');
                            const currentDil = parseFloat($('#modal-header-dil').text());
                            loadMarketplaceBreakdown(currentSku, currentImage, currentInv, currentL30, currentDil);
                        }, 1500);
                    } else {
                        showToast(response.message || 'Failed to push price', 'error');
                        btn.html(originalHtml);
                        btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    console.error('Price push failed:', {
                        sku: sku,
                        marketplace: marketplace,
                        status: xhr.status,
                        error: xhr.responseJSON
                    });
                    
                    let errorMsg = 'Failed to push price';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showToast(errorMsg, 'error');
                    btn.html(originalHtml);
                    btn.prop('disabled', false);
                }
            });
        });

        // ==================== BULK CHANGE PRICE ====================
        $('#apply-change-price-btn').on('click', function() {
            const price = parseFloat($('#change-price-input').val()) || 0;
            if (price <= 0) {
                showToast('Please enter a valid price greater than 0', 'error');
                $('#change-price-input').focus();
                return;
            }

            let skus = [];
            if (selectedSkus.size > 0) {
                skus = Array.from(selectedSkus);
            } else {
                const rows = table.getRows('active');
                rows.forEach(r => {
                    const d = r.getData();
                    if (d.is_parent_summary !== true) skus.push(d.sku);
                });
            }

            if (skus.length === 0) {
                showToast('Please select SKUs or ensure table has data', 'error');
                return;
            }

            const msg = `Apply price $${price.toFixed(2)} to ${skus.length} SKU(s) across all marketplaces?\n\n` +
                'Amazon, Walmart, Shopify B2C, Reverb: full price\n' +
                'Doba & Shopify Wholesale: 25% discount applied\n' +
                'Shopify B2B: 25% discount + shipping deducted';
            if (!confirm(msg)) return;

            const btn = $(this);
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

            $.ajax({
                url: '/cvr-master-bulk-change-price',
                method: 'POST',
                data: {
                    price: price,
                    skus: skus,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || `Updated ${res.updated || 0} SKU(s) across marketplaces`, 'success');
                        $('#change-price-input').val('');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(res.message || 'Some updates failed', 'error');
                    }
                    btn.prop('disabled', false).html(origHtml);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to apply price';
                    showToast(msg, 'error');
                    btn.prop('disabled', false).html(origHtml);
                }
            });
        });

        // ==================== LMP COMPETITORS MODAL ====================
        
        function loadLmpCompetitorsModal(sku, marketplace) {
            $('#lmpSku').text(sku);
            $('#lmpModal').data('lmp-marketplace', marketplace || null);
            const modal = new bootstrap.Modal(document.getElementById('lmpModal'));
            modal.show();
            
            $('#lmpDataList').html('<div class="text-center py-5 text-muted"><div class="spinner-border text-primary me-2"></div>Loading competitors...</div>');
            
            let amazonData = null;
            let ebayData = null;
            const onlyAmazon = marketplace === 'amazon';
            const onlyEbay = marketplace === 'ebay';
            const needAmazon = !onlyEbay;
            const needEbay = !onlyAmazon;
            let loaded = 0;
            const totalNeeded = (needAmazon ? 1 : 0) + (needEbay ? 1 : 0);
            
            function tryRender() {
                loaded++;
                if (loaded < totalNeeded) return;
                renderLmpCombined(sku, amazonData, ebayData, marketplace);
            }
            
            if (needAmazon) {
                $.ajax({
                    url: '/amazon/competitors',
                    method: 'GET',
                    data: { sku: sku },
                    success: function(res) {
                        amazonData = res.success && res.competitors ? res : null;
                        tryRender();
                    },
                    error: function() {
                        amazonData = null;
                        tryRender();
                    }
                });
            }
            
            if (needEbay) {
                $.ajax({
                    url: '/ebay-lmp-data',
                    method: 'GET',
                    data: { sku: sku },
                    success: function(res) {
                        ebayData = res.success && res.competitors ? res : null;
                        tryRender();
                    },
                    error: function() {
                        ebayData = null;
                        tryRender();
                    }
                });
            }
        }
        
        function renderLmpCombined(sku, amazonRes, ebayRes, marketplace) {
            const amzList = (amazonRes && amazonRes.competitors) ? amazonRes.competitors : [];
            const ebayList = (ebayRes && ebayRes.competitors) ? ebayRes.competitors : [];
            const onlyAmazon = marketplace === 'amazon';
            const onlyEbay = marketplace === 'ebay';
            
            const amzLowest = amzList.length ? Math.min(...amzList.map(c => parseFloat(c.price) || 0).filter(p => p > 0)) : null;
            const ebayTotals = ebayList.map(c => parseFloat(c.total_price || c.price) || 0).filter(t => t > 0);
            const ebayLowest = ebayTotals.length ? Math.min(...ebayTotals) : null;
            
            const listToShow = onlyAmazon ? amzList : (onlyEbay ? ebayList : null);
            if (onlyAmazon && amzList.length === 0) {
                $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No Amazon competitors found for this SKU</div>');
                return;
            }
            if (onlyEbay && ebayList.length === 0) {
                $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No eBay competitors found for this SKU</div>');
                return;
            }
            if (!onlyAmazon && !onlyEbay && amzList.length === 0 && ebayList.length === 0) {
                $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No Amazon or eBay competitors found for this SKU</div>');
                return;
            }
            
            const maxRows = onlyAmazon ? amzList.length : (onlyEbay ? ebayList.length : Math.max(amzList.length, ebayList.length));
            let html = '';
            if (onlyAmazon && amzLowest != null && amzLowest > 0) {
                html += '<div class="mb-3"><span class="badge" style="background-color: transparent; color: #ff9c00; font-weight: 600;">Amz lowest: $' + amzLowest.toFixed(2) + '</span></div>';
            } else if (onlyEbay && ebayLowest != null && ebayLowest > 0) {
                html += '<div class="mb-3"><span class="badge bg-info text-dark">eBay lowest: $' + ebayLowest.toFixed(2) + '</span></div>';
            } else if (!onlyAmazon && !onlyEbay && ((amzLowest != null && amzLowest > 0) || (ebayLowest != null && ebayLowest > 0))) {
                const parts = [];
                if (amzLowest != null && amzLowest > 0) parts.push('<span class="badge me-1" style="background-color: transparent; color: #ff9c00; font-weight: 600;">Amz lowest: $' + amzLowest.toFixed(2) + '</span>');
                if (ebayLowest != null && ebayLowest > 0) parts.push('<span class="badge bg-info text-dark">eBay lowest: $' + ebayLowest.toFixed(2) + '</span>');
                html += '<div class="mb-3">' + parts.join(' ') + '</div>';
            }
            
            if (onlyAmazon) {
                html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light"><tr><th>#</th><th>Amz</th><th>Title</th><th>Rating</th><th>Reviews</th><th>Old Price</th><th>Delivery</th><th>Action</th></tr></thead><tbody>';
                amzList.forEach(function(amz, i) {
                    const sn = 'L' + (i + 1);
                    const amzPrice = parseFloat(amz.price) || 0;
                    const amzLink = amz.product_link || amz.link || '';
                    const amzImage = amz.image || '';
                    const amzLowestFlag = amzPrice > 0 && amzLowest != null && Math.abs(amzPrice - amzLowest) < 0.01;
                    const amzImgHtml = amzImage ? `<img src="${amzImage.replace(/"/g, '&quot;')}" alt="Amz" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                    const amzCell = amzPrice > 0
                        ? `<div class="d-flex align-items-center">${amzImgHtml}<span>${amzLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${amzPrice.toFixed(2)}</span>${amzLink ? ` <a href="${amzLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                        : `<div class="d-flex align-items-center">${amzImgHtml}<span class="text-muted">-</span></div>`;
                    const title = (amz.product_title || '').substring(0, 40) + ((amz.product_title || '').length > 40 ? '...' : '');
                    const ratingVal = amz.rating != null ? parseFloat(amz.rating) : null;
                    const ratingCell = ratingVal != null ? '<span><i class="fa fa-star text-warning"></i> ' + ratingVal.toFixed(1) + '</span>' : '<span class="text-muted">-</span>';
                    const reviewsCell = amz.reviews != null ? (parseInt(amz.reviews) || 0).toLocaleString() : '<span class="text-muted">-</span>';
                    const oldPrice = amz.extracted_old_price != null ? parseFloat(amz.extracted_old_price) : null;
                    const oldPriceCell = oldPrice != null && oldPrice > 0 ? '$' + oldPrice.toFixed(2) : '<span class="text-muted">-</span>';
                    const deliveryCell = (amz.delivery || '').substring(0, 50) + ((amz.delivery || '').length > 50 ? '...' : '') || '<span class="text-muted">-</span>';
                    const rowClass = amzLowestFlag ? 'table-success' : '';
                    const delBtn = '<button type="button" class="btn btn-sm btn-outline-danger delete-lmp-row-btn" data-id="' + amz.id + '" data-marketplace="amazon" data-sku="' + (sku || '').replace(/"/g, '&quot;') + '" data-price="' + amzPrice + '" title="Delete this competitor"><i class="fa fa-trash"></i></button>';
                    html += `<tr class="${rowClass}"><td>${sn}</td><td>${amzCell}</td><td title="${(amz.product_title || '').replace(/"/g, '&quot;')}">${title || '-'}</td><td>${ratingCell}</td><td>${reviewsCell}</td><td>${oldPriceCell}</td><td title="${(amz.delivery || '').replace(/"/g, '&quot;')}">${deliveryCell}</td><td>${delBtn}</td></tr>`;
                });
            } else if (onlyEbay) {
                html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light"><tr><th>#</th><th>eBay</th><th>Action</th></tr></thead><tbody>';
                ebayList.forEach(function(ebay, i) {
                    const sn = 'L' + (i + 1);
                    const ebayPrice = parseFloat(ebay.total_price || ebay.price) || 0;
                    const ebayLink = ebay.link || ebay.product_link || '';
                    const ebayImage = ebay.image || '';
                    const ebayLowestFlag = ebayPrice > 0 && ebayLowest != null && Math.abs(ebayPrice - ebayLowest) < 0.01;
                    const ebayImgHtml = ebayImage ? `<img src="${ebayImage.replace(/"/g, '&quot;')}" alt="eBay" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                    const ebayCell = ebayPrice > 0
                        ? `<div class="d-flex align-items-center">${ebayImgHtml}<span>${ebayLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${ebayPrice.toFixed(2)}</span>${ebayLink ? ` <a href="${ebayLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                        : `<div class="d-flex align-items-center">${ebayImgHtml}<span class="text-muted">-</span></div>`;
                    const rowClass = ebayLowestFlag ? 'table-success' : '';
                    const delBtn = '<button type="button" class="btn btn-sm btn-outline-danger delete-lmp-row-btn" data-id="' + ebay.id + '" data-marketplace="ebay" data-sku="' + (sku || '').replace(/"/g, '&quot;') + '" data-price="' + ebayPrice + '" title="Delete this competitor"><i class="fa fa-trash"></i></button>';
                    html += `<tr class="${rowClass}"><td>${sn}</td><td>${ebayCell}</td><td>${delBtn}</td></tr>`;
                });
            } else {
                html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light"><tr><th>#</th><th>Amz</th><th>Rating</th><th>Reviews</th><th>Old Price</th><th>Delivery</th><th>eBay</th><th>Action</th></tr></thead><tbody>';
                for (let i = 0; i < maxRows; i++) {
                    const sn = 'L' + (i + 1);
                    const amz = amzList[i];
                    const ebay = ebayList[i];
                    const amzPrice = amz ? (parseFloat(amz.price) || 0) : null;
                    const ebayPrice = ebay ? (parseFloat(ebay.total_price || ebay.price) || 0) : null;
                    const amzLink = amz ? (amz.product_link || amz.link || '') : '';
                    const ebayLink = ebay ? (ebay.link || ebay.product_link || '') : '';
                    const amzImage = amz ? (amz.image || '') : '';
                    const ebayImage = ebay ? (ebay.image || '') : '';
                    const amzLowestFlag = amzPrice > 0 && amzLowest != null && Math.abs(amzPrice - amzLowest) < 0.01;
                    const ebayLowestFlag = ebayPrice > 0 && ebayLowest != null && Math.abs(ebayPrice - ebayLowest) < 0.01;
                    const amzImgHtml = amzImage ? `<img src="${amzImage.replace(/"/g, '&quot;')}" alt="Amz" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                    const ebayImgHtml = ebayImage ? `<img src="${ebayImage.replace(/"/g, '&quot;')}" alt="eBay" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                    const amzCell = amzPrice != null && amzPrice > 0
                        ? `<div class="d-flex align-items-center">${amzImgHtml}<span>${amzLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${amzPrice.toFixed(2)}</span>${amzLink ? ` <a href="${amzLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                        : amz ? `<div class="d-flex align-items-center">${amzImgHtml}<span class="text-muted">-</span></div>` : '<span class="text-muted">-</span>';
                    const ebayCell = ebayPrice != null && ebayPrice > 0
                        ? `<div class="d-flex align-items-center">${ebayImgHtml}<span>${ebayLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${ebayPrice.toFixed(2)}</span>${ebayLink ? ` <a href="${ebayLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                        : ebay ? `<div class="d-flex align-items-center">${ebayImgHtml}<span class="text-muted">-</span></div>` : '<span class="text-muted">-</span>';
                    const ratingVal = amz && amz.rating != null ? parseFloat(amz.rating) : null;
                    const ratingCell = ratingVal != null ? '<span><i class="fa fa-star text-warning"></i> ' + ratingVal.toFixed(1) + '</span>' : '<span class="text-muted">-</span>';
                    const reviewsCell = amz && amz.reviews != null ? (parseInt(amz.reviews) || 0).toLocaleString() : '<span class="text-muted">-</span>';
                    const oldPrice = amz && amz.extracted_old_price != null ? parseFloat(amz.extracted_old_price) : null;
                    const oldPriceCell = oldPrice != null && oldPrice > 0 ? '$' + oldPrice.toFixed(2) : '<span class="text-muted">-</span>';
                    const deliveryCell = (amz && amz.delivery) ? ((amz.delivery + '').substring(0, 35) + ((amz.delivery + '').length > 35 ? '...' : '')) : '<span class="text-muted">-</span>';
                    const rowClass = (amzLowestFlag || ebayLowestFlag) ? 'table-success' : '';
                    let actionCell = '';
                    if (amz && amz.id) {
                        actionCell += '<button type="button" class="btn btn-sm btn-outline-danger delete-lmp-row-btn me-1" data-id="' + amz.id + '" data-marketplace="amazon" data-sku="' + (sku || '').replace(/"/g, '&quot;') + '" data-price="' + (amzPrice || 0) + '" title="Delete Amz"><i class="fa fa-trash"></i></button>';
                    }
                    if (ebay && ebay.id) {
                        actionCell += '<button type="button" class="btn btn-sm btn-outline-danger delete-lmp-row-btn" data-id="' + ebay.id + '" data-marketplace="ebay" data-sku="' + (sku || '').replace(/"/g, '&quot;') + '" data-price="' + (ebayPrice || 0) + '" title="Delete eBay"><i class="fa fa-trash"></i></button>';
                    }
                    if (!actionCell) actionCell = '<span class="text-muted">-</span>';
                    html += `<tr class="${rowClass}"><td>${sn}</td><td>${amzCell}</td><td>${ratingCell}</td><td>${reviewsCell}</td><td>${oldPriceCell}</td><td title="${(amz && amz.delivery) ? (amz.delivery + '').replace(/"/g, '&quot;') : ''}">${deliveryCell}</td><td>${ebayCell}</td><td>${actionCell}</td></tr>`;
                }
            }
            
            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }
        
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (sku) loadLmpCompetitorsModal(sku);
        });

        $(document).on('click', '.lmp-price-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            const marketplace = $(this).data('marketplace'); // 'amazon' or 'ebay'
            if (sku) loadLmpCompetitorsModal(sku, marketplace);
        });

        $(document).on('click', '.delete-lmp-row-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const btn = $(this);
            const id = btn.data('id');
            const marketplace = btn.data('marketplace');
            const sku = btn.data('sku') || $('#lmpSku').text();
            const price = btn.data('price');
            const label = marketplace === 'amazon' ? 'Amazon' : 'eBay';
            if (!id) return;
            if (!confirm('Delete this ' + label + ' competitor ($' + (price ? parseFloat(price).toFixed(2) : '') + ') from LMP? This cannot be undone.')) return;
            const url = marketplace === 'amazon' ? '/amazon/lmp/delete' : '/ebay-lmp-delete';
            $.ajax({
                url: url,
                method: 'POST',
                data: { id: id, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message || 'Competitor deleted', 'success');
                        loadLmpCompetitorsModal(sku, marketplace);
                    } else {
                        showToast(response.error || 'Failed to delete', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to delete competitor';
                    showToast(msg, 'error');
                }
            });
        });

        // ==================== REMARK FUNCTIONS ====================
        
        // Submit remark
        $(document).on('click', '.submit-remark-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const remarkInput = $(`.remark-input[data-sku="${sku}"]`);
            const remark = remarkInput.val().trim();
            
            if (!remark) {
                showToast('Please enter a remark', 'error');
                return;
            }
            
            $.ajax({
                url: '/cvr-master-remark',
                method: 'POST',
                data: {
                    sku: sku,
                    remark: remark,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast('Remark saved successfully', 'success');
                    remarkInput.val(''); // Clear input
                    
                    // Update the cell directly
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        row.update({latest_remark: remark, remark_solved: false});
                    }
                },
                error: function() {
                    showToast('Failed to save remark', 'error');
                }
            });
        });

        // Open remark history modal
        $(document).on('click', '.remark-history-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            loadRemarkHistory(sku);
        });

        function loadRemarkHistory(sku) {
            $('#historySkuName').text(sku);
            const modal = new bootstrap.Modal(document.getElementById('remarkHistoryModal'));
            modal.show();
            
            $('#remarkHistoryTableBody').html(`
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading history...
                    </td>
                </tr>
            `);
            
            $.ajax({
                url: `/cvr-master-remark-history/${sku}`,
                method: 'GET',
                success: function(data) {
                    if (data.length === 0) {
                        $('#remarkHistoryTableBody').html(`
                            <tr><td colspan="5" class="text-center text-muted py-4">No remarks yet for this SKU</td></tr>
                        `);
                        return;
                    }
                    
                    let html = '';
                    data.forEach(item => {
                        const statusClass = item.is_solved ? 'success' : 'warning';
                        const statusText = item.is_solved ? 'Solved' : 'Pending';
                        const statusIcon = item.is_solved ? 'check-circle' : 'clock';
                        
                        html += `
                            <tr>
                                <td>${item.remark}</td>
                                <td>${item.user_name}</td>
                                <td><small>${item.created_at}</small></td>
                                <td>
                                    <span class="badge bg-${statusClass}">
                                        <i class="fas fa-${statusIcon}"></i> ${statusText}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-${item.is_solved ? 'warning' : 'success'} toggle-solved-btn" 
                                            data-id="${item.id}"
                                            title="${item.is_solved ? 'Mark as Pending' : 'Mark as Solved'}">
                                        <i class="fas fa-${item.is_solved ? 'undo' : 'check'}"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $('#remarkHistoryTableBody').html(html);
                },
                error: function() {
                    $('#remarkHistoryTableBody').html(`
                        <tr><td colspan="5" class="text-center text-danger py-4">Failed to load history</td></tr>
                    `);
                }
            });
        }

        // Toggle solved status
        $(document).on('click', '.toggle-solved-btn', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const sku = $('#historySkuName').text();
            
            $.ajax({
                url: `/cvr-master-remark-toggle/${id}`,
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    showToast('Status updated', 'success');
                    loadRemarkHistory(sku); // Reload history
                    
                    // Update the row in table if it's the latest remark
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        const currentData = row.getData();
                        row.update({remark_solved: response.is_solved});
                    }
                },
                error: function() {
                    showToast('Failed to update status', 'error');
                }
            });
        });

        // Store all data for parent expand/collapse
        let fullDataset = [];
        let expandedParent = null;
        let dotExpandedParent = null;
        // Play/Pause parent navigation (same as product master: show only current parent, ignore other filters)
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        // Prevent dataLoaded side-effects for local setData operations
        let suppressDataLoadedHandler = false;

        /** Reorder data so "10 FR" group is first, then other groups A-Z by parent; within each group children A-Z by SKU then parent row last. */
        function reorderDataWith10FRFirst(data) {
            if (!data || data.length === 0) return data;
            const parentGroups = {};
            data.forEach(row => {
                const p = (row.parent || '').toString().trim();
                if (!parentGroups[p]) parentGroups[p] = { children: [], parentRow: null };
                if (row.is_parent_summary === true) {
                    parentGroups[p].parentRow = row;
                } else {
                    parentGroups[p].children.push(row);
                }
            });
            const PREFER_FIRST = '10 FR';
            const parentNames = Object.keys(parentGroups).filter(p => p !== '');
            parentNames.sort((a, b) => {
                if (a === PREFER_FIRST) return -1;
                if (b === PREFER_FIRST) return 1;
                return String(a).localeCompare(String(b));
            });
            const out = [];
            parentNames.forEach(p => {
                const g = parentGroups[p];
                if (!g) return;
                g.children.sort((a, b) => String((a.sku || '')).localeCompare(String((b.sku || ''))));
                out.push(...g.children);
                if (g.parentRow) out.push(g.parentRow);
            });
            // Rows with empty parent at end
            if (parentGroups['']) {
                const g = parentGroups[''];
                g.children.sort((a, b) => String((a.sku || '')).localeCompare(String((b.sku || ''))));
                out.push(...g.children);
                if (g.parentRow) out.push(g.parentRow);
            }
            return out;
        }

        // Row selection - Set of selected SKUs
        let selectedSkus = new Set();

        function isAllFilteredSelected() {
            if (!table) return false;
            const rows = table.getRows('active');
            if (rows.length === 0) return false;
            return rows.every(r => selectedSkus.has(r.getData().sku));
        }

        // Row checkbox click
        $(document).on('change', '.row-select-cb', function() {
            if (!table) return;
            const sku = $(this).data('sku');
            if ($(this).is(':checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            const row = table.getRow(function(r) { return r.getData().sku === sku; });
            if (row) row.reformat();
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Select all checkbox click
        $(document).on('change', '.select-all-cb', function() {
            if (!table) return;
            const $cb = $(this);
            const rows = table.getRows('active');
            if ($cb.is(':checked')) {
                rows.forEach(r => selectedSkus.add(r.getData().sku));
            } else {
                rows.forEach(r => selectedSkus.delete(r.getData().sku));
            }
            table.getRows().forEach(r => r.reformat());
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Parent SKU dot click - expand to show parent + all child rows with full data
        $(document).on('click', '.parent-sku-dot-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $dot = $(this);
            const parentVal = $dot.data('parent');
            if (!parentVal) return;

            if (dotExpandedParent === parentVal) {
                // Keep current value; applyFilters() needs it to restore fullDataset
                applyFilters();
                return;
            }

            dotExpandedParent = parentVal;

            const parentRow = fullDataset.find(row =>
                row.is_parent_summary === true && row.parent === parentVal
            );
            const childRows = fullDataset.filter(row =>
                row.parent === parentVal && row.is_parent_summary !== true
            );

            let displayData = [];
            displayData = displayData.concat(childRows);
            if (parentRow) {
                parentRow._expanded = true;
                displayData.push(parentRow);
            }

            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                updateSummary();
            });
        });

        function buildParentView() {
            console.log('=== Building Parent View ===');
            console.log('fullDataset length:', fullDataset.length);
            console.log('expandedParent:', expandedParent);
            
            if (!fullDataset || fullDataset.length === 0) {
                console.error('❌ No data available in fullDataset');
                return;
            }
            
            const parentRows = fullDataset.filter(row => row.is_parent_summary === true);
            const childRows = fullDataset.filter(row => row.is_parent_summary !== true);
            
            console.log('Parents found:', parentRows.length);
            console.log('Children found:', childRows.length);
            
            // Debug: Show first few parent values
            if (parentRows.length > 0) {
                console.log('Sample parent values:', parentRows.slice(0, 3).map(p => p.parent));
            }
            
            let displayData = [];
            
            // Build ordered list: when expanded, show children first then parent last (parent row highlighted at bottom)
            parentRows.forEach(parent => {
                // Mark parent as expanded or not (for icon display)
                parent._expanded = (expandedParent === parent.parent);
                
                if (expandedParent !== null && expandedParent === parent.parent) {
                    const children = childRows.filter(child => child.parent === expandedParent);
                    console.log('✓ Parent matched! Adding', children.length, 'children then parent for:', parent.parent);
                    if (children.length > 0) {
                        console.log('Sample children SKUs:', children.slice(0, 3).map(c => c.sku));
                    }
                    // Children first, then parent at bottom (like product-master: parent row highlighted last)
                    displayData = displayData.concat(children);
                    displayData.push(parent);
                } else {
                    displayData.push(parent);
                }
            });
            
            // Apply inventory filter to parent view (so "More than 0" hides parent rows with INV 0)
            const inventoryFilter = $('#inventory-filter').val();
            if (inventoryFilter === 'zero') {
                displayData = displayData.filter(row => (parseFloat(row.inventory) || 0) === 0);
            } else if (inventoryFilter === 'more') {
                displayData = displayData.filter(row => (parseFloat(row.inventory) || 0) > 0);
            }
            // Apply DIL% filter to parent view
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            if (dilFilter !== 'all') {
                displayData = displayData.filter(row => {
                    const inv = parseFloat(row.inventory) || 0;
                    const l30 = parseFloat(row.overall_l30) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    if (dilFilter === 'red') return dil < 16.7;
                    if (dilFilter === 'yellow') return dil >= 16.7 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }
            // Apply CVR filter to parent view (ranges: 0, 0.01-1, 1-2, 2-3, 3-4, 0-4, 4-7, 7-10, 10+)
            const cvrRange = $('.column-filter[data-column="avg_cvr"].active')?.data('range') || 'all';
            if (cvrRange !== 'all') {
                displayData = displayData.filter(row => {
                    const cvr = parseFloat(row.avg_cvr) || 0;
                    if (cvrRange === '0') return cvr >= 0 && cvr < 0.01;
                    if (cvrRange === '0.01-1') return cvr >= 0.01 && cvr < 1;
                    if (cvrRange === '1-2') return cvr >= 1 && cvr < 2;
                    if (cvrRange === '2-3') return cvr >= 2 && cvr < 3;
                    if (cvrRange === '3-4') return cvr >= 3 && cvr < 4;
                    if (cvrRange === '0-4') return cvr >= 0 && cvr < 4;
                    if (cvrRange === '4-7') return cvr >= 4 && cvr < 7;
                    if (cvrRange === '7-10') return cvr >= 7 && cvr < 10;
                    if (cvrRange === '10+') return cvr >= 10;
                    return true;
                });
            }
            // Apply GPFT% filter to parent view (ranges: negative, 0-10, 10-20, 20-30, 30-40, 40-50, 50+; legacy saved: 50-60)
            const gpftRange = $('.column-filter[data-column="avg_gpft"].active')?.data('range') || 'all';
            if (gpftRange !== 'all') {
                displayData = displayData.filter(row => {
                    const gpft = parseFloat(row.avg_gpft) || 0;
                    if (gpftRange === 'negative') return gpft < 0;
                    if (gpftRange === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftRange === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftRange === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftRange === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftRange === '40-50') return gpft >= 40 && gpft < 50;
                    if (gpftRange === '50-60') return gpft >= 50 && gpft < 60;
                    if (gpftRange === '50+') return gpft >= 50;
                    return true;
                });
            }
            const swL30MatchFilter = ($('#sw-l30-match-filter').val() || 'all').toString();
            if (swL30MatchFilter === 'red') {
                displayData = displayData.filter(row => {
                    const sw = parseFloat(row.m_l30 ?? 0);
                    const ov = parseFloat(row.overall_l30 ?? 0);
                    return Math.abs(sw - ov) >= 0.01;
                });
            }
            
            console.log('Final display data length:', displayData.length);
            console.log('Expected:', parentRows.length, '+ children if expanded');
            
            // Update table
            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                console.log('✓ Table updated successfully');
                // Re-apply SKU and Parent search when in parent view
                const skuVal = $('#sku-search').val();
                if (skuVal) table.addFilter("sku", "like", skuVal);
                const parentVal = $('#parent-search').val();
                if (parentVal) table.addFilter("parent", "like", parentVal);
                updateSummary();
            }).catch(err => {
                console.error('❌ Error updating table:', err);
            });
        }

        // ==================== NAVIGATE PARENTS (Play/Pause like product master) ====================
        function getParentRows() {
            if (!fullDataset || fullDataset.length === 0) return [];
            return fullDataset.filter(row => row.is_parent_summary === true);
        }

        function getCurrentParentIndex() {
            const parentRows = getParentRows();
            if (parentRows.length === 0 || expandedParent === null) return -1;
            const idx = parentRows.findIndex(p => p.parent === expandedParent);
            return idx >= 0 ? idx : -1;
        }

        function goToParentByIndex(index) {
            const parentRows = getParentRows();
            if (parentRows.length === 0 || index < 0 || index >= parentRows.length) return;
            expandedParent = parentRows[index].parent;
            buildParentView();
        }

        /** Show only current parent's rows (children first, parent row last – like product master). No other filters. */
        function showCurrentParentPlayView() {
            if (!fullDataset || fullDataset.length === 0) return;
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            const currentParent = parentRows[currentPlayParentIndex].parent;
            const childRows = fullDataset.filter(row => row.parent === currentParent && row.is_parent_summary !== true);
            const parentRow = fullDataset.find(row => row.is_parent_summary === true && row.parent === currentParent);
            // Children first, then parent row at the end (same order as product master)
            const displayData = [...childRows];
            if (parentRow) displayData.push(parentRow);
            suppressDataLoadedHandler = true;
            table.clearSort(); // Keep our order: parent row last, don't re-sort by DIL% etc.
            table.setData(displayData).then(() => {
                updateSummary();
                updatePlayButtonStates();
            });
        }

        function startPlayNavigation() {
            const parentRows = getParentRows();
            if (parentRows.length === 0) return;
            isPlayNavigationActive = true;
            currentPlayParentIndex = 0;
            dotExpandedParent = null;
            expandedParent = null;
            showCurrentParentPlayView();
            $('#play-auto').hide();
            $('#play-pause').show();
            updatePlayButtonStates();
        }

        function stopPlayNavigation() {
            isPlayNavigationActive = false;
            currentPlayParentIndex = 0;
            expandedParent = null;
            dotExpandedParent = null;
            $('#play-pause').hide();
            $('#play-auto').show();
            $('#play-backward, #play-forward').prop('disabled', true);
            if (fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(applyFilters);
            } else {
                applyFilters();
            }
        }

        function updatePlayButtonStates() {
            const parentRows = getParentRows();
            $('#play-backward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex >= parentRows.length - 1);
            $('#play-auto').attr('title', isPlayNavigationActive ? 'Show all' : 'Start parent navigation');
            $('#play-pause').attr('title', 'Stop navigation and show all');
        }

        function playNextParent() {
            if (!isPlayNavigationActive) return;
            const parentRows = getParentRows();
            if (currentPlayParentIndex >= parentRows.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            if (!isPlayNavigationActive) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', startPlayNavigation);
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        // ==================== FILTER FUNCTIONS ====================
        
        $(document).on('click', '.manual-dropdown-container .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            $('.manual-dropdown-container').not(container).removeClass('show');
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            const column = $item.data('column');
            
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const label = column === 'dil_percent' ? ' DIL%' : (column === 'avg_cvr' ? ' CVR' : (column === 'avg_gpft' ? ' GPFT%' : ''));
            button.html('').append(statusCircle).append(label);
            container.removeClass('show');
            
            applyFilters();
        });

        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        function applyFilters() {
            // When Play navigation is active, ignore all filters and show only current parent (same as product master)
            if (isPlayNavigationActive) {
                showCurrentParentPlayView();
                return;
            }

            const wasInDotView = !!dotExpandedParent;
            dotExpandedParent = null;

            const doFilters = function() {
                const inventoryFilter = $('#inventory-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const skuParentFilter = $('#sku-parent-filter').val();

                table.clearFilter();

                // SKU/Parent filter
                if (skuParentFilter === 'sku') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                } else if (skuParentFilter === 'parent') {
                    expandedParent = null;
                    buildParentView();
                    return;
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter("inventory", "=", 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter("inventory", ">", 0);
                }

                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['inventory']) || 0;
                        const l30 = parseFloat(data['overall_l30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                        if (dilFilter === 'red') return dil < 16.7;
                        if (dilFilter === 'yellow') return dil >= 16.7 && dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                const cvrRange = $('.column-filter[data-column="avg_cvr"].active')?.data('range') || 'all';
                if (cvrRange !== 'all') {
                    table.addFilter(function(data) {
                        const cvr = parseFloat(data['avg_cvr']) || 0;
                        if (cvrRange === '0') return cvr >= 0 && cvr < 0.01;
                        if (cvrRange === '0.01-1') return cvr >= 0.01 && cvr < 1;
                        if (cvrRange === '1-2') return cvr >= 1 && cvr < 2;
                        if (cvrRange === '2-3') return cvr >= 2 && cvr < 3;
                        if (cvrRange === '3-4') return cvr >= 3 && cvr < 4;
                        if (cvrRange === '0-4') return cvr >= 0 && cvr < 4;
                        if (cvrRange === '4-7') return cvr >= 4 && cvr < 7;
                        if (cvrRange === '7-10') return cvr >= 7 && cvr < 10;
                        if (cvrRange === '10+') return cvr >= 10;
                        return true;
                    });
                }

                const gpftRange = $('.column-filter[data-column="avg_gpft"].active')?.data('range') || 'all';
                if (gpftRange !== 'all') {
                    table.addFilter(function(data) {
                        const gpft = parseFloat(data['avg_gpft']) || 0;
                        if (gpftRange === 'negative') return gpft < 0;
                        if (gpftRange === '0-10') return gpft >= 0 && gpft < 10;
                        if (gpftRange === '10-20') return gpft >= 10 && gpft < 20;
                        if (gpftRange === '20-30') return gpft >= 20 && gpft < 30;
                        if (gpftRange === '30-40') return gpft >= 30 && gpft < 40;
                        if (gpftRange === '40-50') return gpft >= 40 && gpft < 50;
                        if (gpftRange === '50-60') return gpft >= 50 && gpft < 60;
                        if (gpftRange === '50+') return gpft >= 50;
                        return true;
                    });
                }

                const swL30MatchFilter = ($('#sw-l30-match-filter').val() || 'all').toString();
                if (swL30MatchFilter === 'red') {
                    table.addFilter(function(data) {
                        const sw = parseFloat(data.m_l30 ?? 0);
                        const ov = parseFloat(data.overall_l30 ?? 0);
                        return Math.abs(sw - ov) >= 0.01;
                    });
                }

                // Apply SKU and Parent search filters
                const skuVal = $('#sku-search').val();
                if (skuVal) table.addFilter("sku", "like", skuVal);
                const parentVal = $('#parent-search').val();
                if (parentVal) table.addFilter("parent", "like", parentVal);

                updateSummary();
            };

            if (wasInDotView && fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(doFilters);
            } else {
                doFilters();
            }
        }

        $('#inventory-filter, #sku-parent-filter, #sw-l30-match-filter').on('change', function() {
            applyFilters();
        });

        $('#remove-filter-btn').on('click', function() {
            $('#inventory-filter').val('more');
            $('#sku-parent-filter').val('both');
            $('#sku-search').val('');
            $('#parent-search').val('');
            // Reset DIL
            const $allDil = $('.column-filter[data-column="dil_percent"][data-color="all"]');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $allDil.addClass('active');
            $('#dilFilterDropdown').html('').append($allDil.find('.status-circle').clone()).append(' DIL%');
            // Reset CVR
            const $allCvr = $('.column-filter[data-column="avg_cvr"][data-range="all"]');
            $('.column-filter[data-column="avg_cvr"]').removeClass('active');
            $allCvr.addClass('active');
            $('#cvrFilterDropdown').html('').append($allCvr.find('.status-circle').clone()).append(' CVR');
            // Reset GPFT%
            const $allGpft = $('.column-filter[data-column="avg_gpft"][data-range="all"]');
            $('.column-filter[data-column="avg_gpft"]').removeClass('active');
            $allGpft.addClass('active');
            $('#gpftFilterDropdown').html('').append($allGpft.find('.status-circle').clone()).append(' GPFT%');
            $('#sw-l30-match-filter').val('all');
            applyFilters();
        });

        // ==================== SUMMARY FUNCTIONS ====================
        
        function updateSummary() {
            const data = table.getData('active');
            let totalInv = 0, totalL30 = 0, totalDil = 0, dilCount = 0;
            let totalViews = 0, totalCvr = 0, cvrCount = 0;
            let totalPrice = 0, priceCount = 0;
            let totalAmzLmp = 0, amzLmpCount = 0;

            data.forEach(row => {
                totalInv += parseFloat(row['inventory']) || 0;
                totalL30 += parseFloat(row['overall_l30']) || 0;
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                totalViews += parseInt(row['total_views']) || 0;
                totalCvr += parseFloat(row['avg_cvr']) || 0;
                cvrCount++;
                const price = parseFloat(row['avg_price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                if (!row.is_parent_summary) {
                    const amzLmp = parseFloat(row['amazon_lmp_price']) || 0;
                    if (amzLmp > 0) {
                        totalAmzLmp += amzLmp;
                        amzLmpCount++;
                    }
                }
            });

            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgAmzLmp = amzLmpCount > 0 ? totalAmzLmp / amzLmpCount : 0;

            $('#total-inv-badge').text(totalInv.toLocaleString());
            $('#total-l30-badge').text(totalL30.toLocaleString());
            $('#avg-dil-badge').text(avgDil.toFixed(1) + '%');
            $('#total-views-badge').text(totalViews.toLocaleString());
            $('#avg-cvr-badge').text(avgCvr.toFixed(1) + '%');
            $('#avg-price-badge').text('$' + avgPrice.toFixed(2));
            $('#amz-lmp-badge').text('$' + avgAmzLmp.toFixed(2));

            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        }

        // ==================== COLUMN VISIBILITY FUNCTIONS ====================
        
        function buildColumnDropdown() {
            const columns = table.getColumns();
            let html = '';
            
            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;
                if (field && title) {
                    const isVisible = col.isVisible();
                    html += `<li class="dropdown-item">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                            ${title.replace(/<[^>]*>/g, '')}
                        </label>
                    </li>`;
                }
            });
            
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field) visibility[field] = col.isVisible();
            });
            
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) {
                                visibility[field] ? col.show() : col.hide();
                            }
                        });
                    }
                    buildColumnDropdown();
                }
            });
        }

        // ==================== TABLE EVENTS ====================
        
        table.on('tableBuilt', function() {
            buildColumnDropdown();
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function(data) {
            // Ignore dataLoaded triggered by local setData (dot view / parent view / restore)
            if (suppressDataLoadedHandler) {
                suppressDataLoadedHandler = false;
                return;
            }

            // Reorder so "10 FR" group is first, then others A-Z; within group children A-Z then parent row last
            const reordered = reorderDataWith10FRFirst(data);
            fullDataset = reordered;

            suppressDataLoadedHandler = true;
            table.setData(reordered).then(function() {
                applyFilters();
                updateSummary();
            });
        });

        table.on('renderComplete', function() {
            setTimeout(() => updateSummary(), 100);
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const field = e.target.dataset.field;
                const col = table.getColumn(field);
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    saveColumnVisibilityToServer();
                }
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // ==================== EXPORT FUNCTIONS ====================
        
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible());
            
            const headers = visibleColumns.map(col => {
                let title = col.getDefinition().title || col.getField();
                return title.replace(/<[^>]*>/g, '');
            });
            exportData.push(headers);
            
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    if (value === null || value === undefined) value = '';
                    else if (typeof value === 'number') value = parseFloat(value.toFixed(2));
                    else if (typeof value === 'string') value = value.replace(/<[^>]*>/g, '').trim();
                    
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'cvr_master_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });

        // ==================== PRICING MASTER ROLLING L30 CHARTS (SKU-wise: Inv, OV L30, Price, CVR) ====================
        let pricingMasterChartInstance = null;
        let currentPricingChartMetric = 'inv';
        let currentPricingChartSku = '';
        let currentPricingChartParent = '';
        let currentPricingChartAggregate = false;
        let currentPricingChartDays = 30;
        const pricingChartMetricLabels = { inv: 'Inv', ov_l30: 'OV L30', price: 'Price', cvr: 'CVR', dil: 'DIL', amz_price: 'Amz Price', rating: 'Rating', total_views: 'Total Views' };
        const pricingChartRangeLabel = (days) => 'L' + days;

        $(document).on('click', '.summary-chart-badge', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(e.currentTarget).attr('data-metric') || $(e.currentTarget).data('metric');
            if (!metric) return;
            currentPricingChartMetric = metric;
            currentPricingChartSku = '';
            currentPricingChartParent = '';
            currentPricingChartAggregate = true;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Master Analytics - All (Summary) - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            const modal = new bootstrap.Modal(document.getElementById('pricingMasterChartModal'));
            modal.show();
            loadPricingMasterChart();
        });

        $(document).on('click', '.pricing-master-chart-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const metric = $(e.currentTarget).attr('data-metric') || $(e.currentTarget).data('metric');
            const sku = String($(e.currentTarget).attr('data-sku') || $(e.currentTarget).data('sku') || '').trim();
            const parent = String($(e.currentTarget).attr('data-parent') || $(e.currentTarget).data('parent') || '').trim();
            if (!metric) return;
            currentPricingChartAggregate = false;
            const isParentChart = parent !== '' || (sku.indexOf('PARENT ') === 0);
            const displayName = isParentChart ? (parent || sku.replace(/^PARENT\s+/i, '')) : sku;
            if (!isParentChart && !sku) { showToast('SKU not found for chart', 'error'); return; }
            if (isParentChart) {
                currentPricingChartParent = parent || sku.replace(/^PARENT\s+/i, '').trim();
                currentPricingChartSku = '';
            } else {
                currentPricingChartParent = '';
                currentPricingChartSku = sku;
            }
            currentPricingChartMetric = metric;
            currentPricingChartDays = 30;
            $('#pricingMasterChartRangeSelect').val('30');
            const label = pricingChartMetricLabels[metric] || metric;
            $('#pricingMasterChartModalTitle').text('Master Analytics - ' + displayName + (isParentChart ? ' (Parent)' : '') + ' - ' + label + ' (Rolling ' + pricingChartRangeLabel(30) + ')');
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            $('#pricingMasterChartLoading').show();
            const modal = new bootstrap.Modal(document.getElementById('pricingMasterChartModal'));
            modal.show();
            loadPricingMasterChart();
        });

        $(document).on('change', '#pricingMasterChartRangeSelect', function() {
            const days = parseInt($(this).val(), 10);
            if (days === currentPricingChartDays) return;
            currentPricingChartDays = days;
            $('#pricingMasterChartModalTitle').text($('#pricingMasterChartModalTitle').text().replace(/Rolling L\d+/, 'Rolling ' + pricingChartRangeLabel(days)));
            loadPricingMasterChart();
        });

        function loadPricingMasterChart() {
            $('#pricingMasterChartLoading').show();
            $('#pricingMasterChartContainer').hide();
            $('#pricingMasterChartNoData').hide();
            const payload = { metric: currentPricingChartMetric, days: currentPricingChartDays };
            if (currentPricingChartAggregate) {
                payload.aggregate = 1;
            } else if (currentPricingChartParent) {
                payload.parent = currentPricingChartParent;
            } else {
                payload.sku = currentPricingChartSku;
            }
            $.ajax({
                url: '/cvr-master-chart-data',
                method: 'GET',
                data: payload,
                success: function(response) {
                    $('#pricingMasterChartLoading').hide();
                    if (response.success && response.data && response.data.length > 0) {
                        $('#pricingMasterChartContainer').show();
                        renderPricingMasterChart(response.data);
                    } else {
                        $('#pricingMasterChartNoData').show();
                    }
                },
                error: function() {
                    $('#pricingMasterChartLoading').hide();
                    $('#pricingMasterChartNoData').show();
                }
            });
        }

        function renderPricingMasterChart(data) {
            const ctx = document.getElementById('pricingMasterChart');
            if (!ctx) return;
            if (pricingMasterChartInstance) {
                pricingMasterChartInstance.destroy();
                pricingMasterChartInstance = null;
            }
            const labels = data.map(d => d.date);
            const values = data.map(d => parseFloat(d.value) || 0);
            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted = [...values].sort((a, b) => a - b);
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;
            const fmtVal = (v) => {
                if (currentPricingChartMetric === 'price' || currentPricingChartMetric === 'amz_price') return '$' + (Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                if (currentPricingChartMetric === 'cvr' || currentPricingChartMetric === 'dil') return Number(v).toFixed(1) + '%';
                if (currentPricingChartMetric === 'rating') return Number(v).toFixed(1);
                if (currentPricingChartMetric === 'total_views') return Math.round(v).toLocaleString('en-US');
                return Math.round(v).toLocaleString('en-US');
            };
            $('#pricingMasterChartHighest').text(fmtVal(dataMax)).css('color', '#dc3545');
            $('#pricingMasterChartMedian').text(fmtVal(median)).css('color', '#6c757d');
            $('#pricingMasterChartLowest').text(fmtVal(dataMin)).css('color', '#198754');
            const dotColors = values.map((v, i) => {
                if (i === 0) return '#6c757d';
                return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
            });
            const medianLinePlugin = {
                id: 'pricingMedianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    const ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    ctx.save();
                    ctx.setLineDash([6, 4]);
                    ctx.strokeStyle = '#6c757d';
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.moveTo(xScale.left, yPixel);
                    ctx.lineTo(xScale.right, yPixel);
                    ctx.stroke();
                    ctx.restore();
                }
            };
            const valueLabelsPlugin = {
                id: 'pricingValueLabels',
                afterDatasetsDraw(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.font = 'bold 7px Inter, system-ui, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const val = dataset.data[i];
                        const x = point.x;
                        const y = point.y;
                        const offsetY = (i % 2 === 0) ? -7 : -14;
                        ctx.fillStyle = val === 0 ? '#198754' : val > 0 ? '#dc3545' : '#6c757d';
                        ctx.fillText(fmtVal(val), x, y + offsetY);
                    });
                    ctx.restore();
                }
            };
            pricingMasterChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: pricingChartMetricLabels[currentPricingChartMetric] || currentPricingChartMetric,
                        data: values,
                        backgroundColor: 'rgba(108,117,125,0.08)',
                        borderColor: '#adb5bd',
                        borderWidth: 1.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 18, left: 2, right: 2, bottom: 2 } },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            min: yMin,
                            max: yMax,
                            ticks: { font: { size: 9 }, callback: function(value) { return fmtVal(value); } }
                        },
                        x: { ticks: { font: { size: 9 }, maxRotation: 45 } }
                    }
                }
            });
        }
    });
</script>
@endsection
