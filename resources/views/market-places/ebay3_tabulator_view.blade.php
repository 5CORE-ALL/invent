@extends('layouts.vertical', ['title' => 'Ebay 3 Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Image column hover preview (forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

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

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
        }
        
        /* eBay3 specific styling - purple accent */
        .badge.bg-ebay3 {
            background-color: #6f42c1 !important;
        }

        /* Frozen columns need solid background to prevent overlap on horizontal scroll */
        .tabulator .tabulator-header .tabulator-frozen {
            background-color: #00d5d5 !important;
            z-index: 11 !important;
        }
        .tabulator-row .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        .tabulator .tabulator-footer .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        
        /* PARENT row light blue background */
        .tabulator-row.parent-row {
            background-color: #d4f8fc !important;
        }
        .tabulator-row.parent-row .tabulator-frozen {
            background-color: #d4f8fc !important;
        }
        .tabulator-row.parent-row:hover {
            background-color: #bef3f9 !important;
        }
        .tabulator-row.parent-row:hover .tabulator-frozen {
            background-color: #bef3f9 !important;
        }

        /* Hide tree + / − glyphs and box styling; keep control clickable to expand/collapse */
        #ebay3-table .tabulator-data-tree-control {
            background: transparent !important;
            border: none !important;
        }
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-expand,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-expand::after,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-collapse,
        #ebay3-table .tabulator-data-tree-control .tabulator-data-tree-control-collapse::after {
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            min-height: 0 !important;
            overflow: hidden !important;
        }

        .ebay3-variation-dot:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }

        .acos-info-icon {
            transition: color 0.2s;
        }

        .acos-info-icon:hover {
            color: #007bff !important;
        }

        /* Match Ebay 2 summary badge row: single row, shared width, scaled text */
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 1 1 0;
            min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }
        /* TACOS: must beat .ebay2-summary-badge-row > .badge so pill + colors stick */
        #summary-stats .ebay2-summary-badge-row #tacos-percent-badge.summary-badge-tacos {
            background-color: #b91c1c !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border-radius: 0.75rem !important;
            border: none !important;
            -webkit-font-smoothing: antialiased;
        }

        #campaignModal .table {
            font-size: 0.875rem;
        }

        #campaignModal .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            white-space: nowrap;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            height: 60px;
            width: 40px;
            min-width: 40px;
            font-size: 11px;
            vertical-align: middle;
            text-align: center;
            padding: 5px;
        }

        #campaignModal .table td {
            white-space: nowrap;
            vertical-align: middle;
            text-align: center;
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

        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }
        .status-circle.default { background-color: #6c757d; }
        .status-circle.red { background-color: #dc3545; }
        .status-circle.yellow { background-color: #ffc107; }
        .status-circle.green { background-color: #28a745; }
        .status-circle.pink { background-color: #e83e8c; }
        .status-circle.blue { background-color: #0d6efd; }

        .manual-dropdown-container.pricing-filter-item {
            position: relative;
            display: inline-block;
        }
        .manual-dropdown-container.pricing-filter-item .dropdown-menu {
            display: none;
        }
        .manual-dropdown-container.pricing-filter-item.show .dropdown-menu {
            display: block;
        }
        .manual-dropdown-container.pricing-filter-item .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay 3 Analytics',
        'sub_title' => 'Ebay 3 Analytics',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay3 Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="section-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>Section Filter</option>
                        <option value="pricing">Pricing</option>
                        <option value="kw_ads">KW Ads</option>
                        <option value="pmt_ads">PMT Ads</option>
                    </select>

                    <select id="view-mode-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="sku">SKU Only</option>
                        <option value="parent" selected>Parent Only</option>
                        <option value="both">Both (Parent + SKU)</option>
                    </select>

                    <select id="inv-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="ebay-stock-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All E Stock</option>
                        <option value="zero">0 E Stock</option>
                        <option value="more" selected>E Stock &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>All E L30</option>
                        <option value="zero">0 E L30</option>
                        <option value="more">E L30 &gt; 0</option>
                    </select>

                    <select id="variation-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>Variation — All</option>
                        <option value="red">Variation — Red</option>
                        <option value="green">Variation — Green</option>
                    </select>

                    <!-- KW Ads specific filters (hidden by default) -->
                    <select id="kw-utilization-filter" class="form-select form-select-sm kw-ads-filter-item"
                        style="width: auto; display: none;">
                        <option value="all" selected>Utilization</option>
                        <option value="green-green">Green + Green (0)</option>
                        <option value="green-pink">Green + Pink (0)</option>
                        <option value="green-red">Green + Red (0)</option>
                        <option value="pink-green">Pink + Green (0)</option>
                        <option value="pink-pink">Pink + Pink (0)</option>
                        <option value="pink-red">Pink + Red (0)</option>
                        <option value="red-green">Red + Green (0)</option>
                        <option value="red-pink">Red + Pink (0)</option>
                        <option value="red-red">Red + Red (0)</option>
                    </select>

                    <select id="kw-status-filter" class="form-select form-select-sm kw-ads-filter-item"
                        style="width: auto; display: none;">
                        <option value="all">Campaign Status</option>
                        <option value="RUNNING">Running</option>
                        <option value="PAUSED">Paused</option>
                    </select>

                    <select id="kw-nra-filter" class="form-select form-select-sm kw-ads-filter-item"
                        style="width: auto; display: none;">
                        <option value="all">All NRA</option>
                        <option value="NRA">NRA</option>
                        <option value="RA">RA</option>
                        <option value="LATER">LATER</option>
                    </select>

                    <select id="kw-nrl-filter" class="form-select form-select-sm kw-ads-filter-item"
                        style="width: auto; display: none;">
                        <option value="all">All NRL</option>
                        <option value="NRL">NRL</option>
                        <option value="REQ">REQ</option>
                    </select>

                    <select id="kw-sbidm-filter" class="form-select form-select-sm kw-ads-filter-item"
                        style="width: auto; display: none;">
                        <option value="all">SBID M</option>
                        <option value="blank">Blank</option>
                        <option value="data">Data</option>
                    </select>

                    <!-- KW Ads Bulk Actions Dropdown -->
                    <div class="dropdown kw-ads-filter-item" id="kw-bulk-actions-container" style="display: none;">
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button"
                            id="kwBulkActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tasks"></i> Bulk Actions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="kwBulkActionsDropdown">
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="NRA">Mark as NRA</a></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="RA">Mark as RA</a></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="LATER">Mark as LATER</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="PAUSE">Pause Campaigns</a></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="ACTIVATE">Activate Campaigns</a></li>
                        </ul>
                    </div>

                    <!-- PMT Ads Dropdown Filters (inline, hidden by default) -->
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-dilFilterDropdown">
                            <span class="status-circle default"></span> OV DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-dilFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil" data-color="all">
                                <span class="status-circle default"></span> All OV DIL</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-eDilFilterDropdown">
                            <span class="status-circle default"></span> E Dil%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-eDilFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil" data-color="all">
                                <span class="status-circle default"></span> All E Dil</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-pmtClkL7FilterDropdown">
                            <span class="status-circle default"></span> PmtClkL7
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pmtClkL7FilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7" data-color="all">
                                <span class="status-circle default"></span> All PmtClkL7</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-pmtClkL30FilterDropdown">
                            <span class="status-circle default"></span> PmtClkL30
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pmtClkL30FilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30" data-color="all">
                                <span class="status-circle default"></span> All PmtClkL30</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-pftFilterDropdown">
                            <span class="status-circle default"></span> PFT%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pftFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="all">
                                <span class="status-circle default"></span> All PFT</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="blue">
                                <span class="status-circle blue"></span> Blue</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-roiFilterDropdown">
                            <span class="status-circle default"></span> ROI
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-roiFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi" data-color="all">
                                <span class="status-circle default"></span> All ROI</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-tacosFilterDropdown">
                            <span class="status-circle default"></span> TACOS
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-tacosFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="all">
                                <span class="status-circle default"></span> All TACOS</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="blue">
                                <span class="status-circle blue"></span> Blue</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-scvrFilterDropdown">
                            <span class="status-circle default"></span> SCVR
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-scvrFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="all">
                                <span class="status-circle default"></span> All SCVR</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="red">
                                <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="green">
                                <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="pink">
                                <span class="status-circle pink"></span> Pink</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr" data-color="blue">
                                <span class="status-circle blue"></span> Low SCVR</a></li>
                        </ul>
                    </div>

                    <!-- Pricing section filters -->
                    <select id="growth-sign-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;"
                        title="eBay E L30 vs E L60: (L30 − L60) / L60 × 100; L60=0 and L30&gt;0 counts as +100%">
                        <option value="all" selected>All Growth</option>
                        <option value="negative">Negative Only</option>
                        <option value="zero">Zero Only</option>
                        <option value="positive">Positive Only</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex flex-row flex-nowrap align-items-center gap-1 pricing-filter-item" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block; flex-shrink: 0;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block; flex-shrink: 0;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-2">0-2%</option>
                            <option value="2-4">2-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    <select id="roi-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="125-175">125–175%</option>
                        <option value="175-250">175–250%</option>
                        <option value="gt250">&gt; 250%</option>
                    </select>

                    <select id="cvr-trend-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR trend</option>
                        <option value="l60_gt_l30">CVR 60 &gt; CVR 30</option>
                        <option value="l30_gt_l60">CVR 30 &gt; CVR 60</option>
                        <option value="equal">CVR 60 = CVR 30</option>
                    </select>

                    <select id="sprice-filter" class="form-select form-select-sm pricing-filter-item"
                        style="width: auto; display: inline-block;">
                        <option value="all">SPRICE</option>
                        <option value="blank">Blank SPRICE only</option>
                    </select>

                    <!-- DIL Filter -->
                    <div class="manual-dropdown-container pricing-filter-item" id="dil-filter-wrapper">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.66%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.66-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block pricing-filter-item">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="ebay3-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    <button type="button" id="export-section-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export current section (visible columns & filtered data)">
                        <i class="fas fa-file-export"></i> Export
                    </button>

                    <!-- Play / Pause parent navigation (like pricing-master-cvr) -->
                    <div class="btn-group align-items-center ms-2 pricing-filter-item" role="group">
                        <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" title="Play — parents low → high by CVR 30 (SCVR)">
                            <i class="fas fa-play"></i>
                        </button>
                        <button type="button" id="play-pause" class="btn btn-sm btn-primary rounded-circle shadow-sm me-1" style="display: none;" title="Pause - show all">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                </div>

                <!-- KW Ads Statistics (shown only when KW Ads is selected) -->
                <div id="kw-ads-stats" class="mt-2 p-3 bg-light rounded" style="display: none;">
                    <h6 class="mb-3">KW Ads Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" style="color: white; font-weight: bold;">Total Parent SKU: <span id="kw-total-sku-count">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #8b5cf6; color: white; font-weight: bold;">Ebay SKU: <span id="kw-ebay-sku-count">0</span></span>
                        <span class="badge bg-info fs-6 p-2" id="kw-campaign-card" style="color: white; font-weight: bold; cursor: pointer;">Campaign: <span id="kw-campaign-count">0</span></span>
                        <span class="badge bg-danger fs-6 p-2" id="kw-missing-card" style="color: white; font-weight: bold; cursor: pointer;">Missing: <span id="kw-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nra-missing-card" style="background-color: #ffc107; color: black; font-weight: bold; cursor: pointer;">NRA MISSING: <span id="kw-nra-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-zero-inv-card" style="background-color: #f59e0b; color: black; font-weight: bold; cursor: pointer;">Zero E Stock: <span id="kw-zero-inv-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nra-card" style="background-color: #ef4444; color: white; font-weight: bold; cursor: pointer;">NRA: <span id="kw-nra-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nrl-missing-card" style="background-color: #ffc107; color: black; font-weight: bold; cursor: pointer;">NRL MISSING: <span id="kw-nrl-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nrl-card" style="background-color: #ef4444; color: white; font-weight: bold; cursor: pointer;">NRL: <span id="kw-nrl-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-ra-card" style="background-color: #22c55e; color: white; font-weight: bold; cursor: pointer;">RA: <span id="kw-ra-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-paused-card" style="background-color: #ec4899; color: white; font-weight: bold; cursor: pointer;" title="Click to view paused campaigns">PINK DIL PAUSED: <span id="kw-paused-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-7ub-card" style="background-color: #2563eb; color: white; font-weight: bold; cursor: pointer;">7UB: <span id="kw-7ub-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-7ub-1ub-card" style="background-color: #7c3aed; color: white; font-weight: bold; cursor: pointer;">7UB+1UB: <span id="kw-7ub-1ub-count">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #14b8a6; color: white; font-weight: bold;">L30 CLICKS: <span id="kw-l30-clicks">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #0ea5e9; color: white; font-weight: bold;">L30 SPEND: <span id="kw-l30-spend">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #f59e0b; color: black; font-weight: bold;">L30 AD SOLD: <span id="kw-l30-ad-sold">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #8b5cf6; color: white; font-weight: bold;">AVG ACOS: <span id="kw-avg-acos">0</span></span>
                        <span class="badge fs-6 p-2" style="background-color: #10b981; color: white; font-weight: bold;">AVG CVR: <span id="kw-avg-cvr">0</span></span>
                    </div>
                </div>

                <!-- KW Ads Range Filters + INC/DEC SBID Section (hidden by default) -->
                <div id="kw-ads-range-section" style="display: none;">
                    <div class="row g-3 align-items-end pt-2 px-2">
                        <!-- 1UB% Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">1UB% Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-1ub-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-1ub-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- 7UB% Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">7UB% Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-7ub-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-7ub-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- LBid Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">LBid Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-lbid-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-lbid-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- Acos Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">Acos Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-acos-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-acos-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- Views Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">Views Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-views-min" class="form-control form-control-sm" placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-views-max" class="form-control form-control-sm" placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- L7 Views Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">L7 Views Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-l7views-min" class="form-control form-control-sm" placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-l7views-max" class="form-control form-control-sm" placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                    </div>
                    <!-- INC/DEC SBID Section and Action Buttons -->
                    <div class="row g-3 align-items-end pt-3 pb-2 px-2 border-top mt-2">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                <i class="fa-solid fa-calculator me-1" style="color: #64748b;"></i>INC/DEC SBID
                            </label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" id="kw-inc-dec-btn" class="btn btn-warning btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-plus-minus me-1"></i>INC/DEC (By Value)
                                </button>
                                <ul class="dropdown-menu" id="kw-inc-dec-dropdown">
                                    <li><a class="dropdown-item" href="#" data-type="value">By Value</a></li>
                                    <li><a class="dropdown-item" href="#" data-type="percentage">By Percentage</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                <span id="kw-inc-dec-label">Value/Percentage</span>
                            </label>
                            <input type="number" id="kw-inc-dec-input" class="form-control form-control-sm" placeholder="Enter value (e.g., +0.5 or -0.5)" step="0.01" style="border-color: #e2e8f0;">
                        </div>
                        <div class="col-md-2 d-flex gap-2 align-items-end">
                            <button id="kw-apply-inc-dec-btn" class="btn btn-success btn-sm flex-fill">
                                <i class="fa-solid fa-check me-1"></i>Apply
                            </button>
                            <button id="kw-clear-inc-dec-btn" class="btn btn-secondary btn-sm flex-fill">
                                <i class="fa-solid fa-times me-1"></i>Clear Input
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button id="kw-clear-sbid-m-btn" class="btn btn-danger btn-sm w-100">
                                <i class="fa-solid fa-trash me-1"></i>Clear SBID M (Selected)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PMT Ads Range Filters (hidden by default) -->
                <div id="pmt-ads-filter-section" style="display: none;">
                    <!-- Range Filter Section -->
                    <div class="row g-3 align-items-end pt-2 px-2 pb-2">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">T VIEWS Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-t-views-min" class="form-control form-control-sm" placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-t-views-max" class="form-control form-control-sm" placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">L7 Views Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-l7-views-min" class="form-control form-control-sm" placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-l7-views-max" class="form-control form-control-sm" placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">CBID Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-cbid-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-cbid-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">SCVR Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-scvr-min" class="form-control form-control-sm" placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-scvr-max" class="form-control form-control-sm" placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex gap-2 align-items-end">
                            <button id="pmt-apply-range-btn" class="btn btn-primary btn-sm flex-fill">
                                <i class="fa-solid fa-filter me-1"></i>Apply
                            </button>
                            <button id="pmt-clear-range-btn" class="btn btn-secondary btn-sm flex-fill">
                                <i class="fa-solid fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats — same badge set/order as Ebay 2 Analytics -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (E Stock &gt; 0)</h6>
                    <div class="ebay2-summary-badge-row">
                        <span class="badge bg-danger fs-6 p-2 sold-filter-badge" data-filter="zero" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: <span id="zero-sold-count">0</span></span>
                        <span class="badge fs-6 p-2 sold-filter-badge" data-filter="sold" style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;" title="Click to filter sold items">&gt; 0 Sold: <span id="more-sold-count">0</span></span>
                        <span class="badge bg-success fs-6 p-2 d-none" id="total-pft-amt-badge" style="color: black; font-weight: bold;" aria-hidden="true">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Sales: $0</span>
                        <span class="badge bg-warning fs-6 p-2 d-none" id="total-spend-l30-badge" style="color: black; font-weight: bold;" aria-hidden="true">PMT Spend: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge" style="color: black; font-weight: bold;">NPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge" style="color: white; font-weight: bold;">GROI: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="nroi-percent-badge" style="color: black; font-weight: bold;">NROI: 0%</span>
                        <span class="badge fs-6 p-2 summary-badge-tacos" id="tacos-percent-badge">TACOS: 0.0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: white; font-weight: bold;">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-primary fs-6 p-2 d-none" id="total-inv-badge" style="color: black; font-weight: bold;" aria-hidden="true">E Stock: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs">Missing: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="map-count-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter mapped SKUs">Map: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="inv-stock-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter not mapped SKUs">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="ebay3-discount-type-block" class="d-flex align-items-center gap-2">
                            <label class="mb-0 fw-bold">Type:</label>
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 130px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter percentage" step="0.01" min="0" style="width: 150px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div id="ebay3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Campaign Details Modal (ACOS info icon) -->
    <div class="modal fade" id="campaignModal" tabindex="-1" aria-labelledby="campaignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="campaignModalLabel">Campaign Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="campaignModalBody">
                    <!-- Content loaded via JS -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> eBay3 Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fa fa-plus-circle"></i> Add New Competitor</h6>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm">
                                <input type="hidden" id="addCompSku" name="sku">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">eBay Item ID *</label>
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id" required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price" step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping" name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link" placeholder="https://ebay.com/itm/...">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fa fa-plus"></i> Add
                                        </button>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <label class="form-label">Product Title (optional)</label>
                                        <input type="text" class="form-control" id="addCompTitle" name="product_title" placeholder="Product title">
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Competitors List -->
                    <div id="lmpDataList">
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
    const COLUMN_VIS_KEY = "ebay3_tabulator_column_visibility";
    const KW_SPENT = {{ $kwSpent ?? 0 }};
    const PMT_SPENT = {{ $pmtSpent ?? 0 }};
    const TOTAL_ADS_SPENT = KW_SPENT + PMT_SPENT;
    /** Same as AD% column — getEbaythreeMasterAdsPercent() / all-marketplace-master */
    const EBAY3_CHANNEL_ADS_PCT = {{ number_format((float) ($channelAdsPercent ?? 0), 4, '.', '') }};
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    
    // Badge filter state variables
    let zeroSoldFilterActive = false;
    let moreSoldFilterActive = false;
    let missingFilterActive = false;
    let mapFilterActive = false;
    let invStockFilterActive = false;
    // PMT Ads dropdown filter state
    let pmtDropdownFilters = {
        'pmt_ov_dil': 'all',
        'pmt_e_dil': 'all',
        'pmt_clk_l7': 'all',
        'pmt_clk_l30': 'all',
        'pmt_pft': 'all',
        'pmt_roi': 'all',
        'pmt_tacos': 'all',
        'pmt_scvr': 'all'
    };

    // PMT Ads range filter state
    let pmtRangeFilters = {
        't_views': { min: null, max: null },
        'l7_views': { min: null, max: null },
        'cbid': { min: null, max: null },
        'scvr': { min: null, max: null }
    };
    
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
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            toast.classList.add('show');
            toast.style.position = 'fixed';
            toast.style.top = '1rem';
            toast.style.right = '1rem';
            toast.style.zIndex = '10800';
            setTimeout(function() { toast.remove(); }, 5000);
        }
    }

    // Background retry storage key
    const BACKGROUND_RETRY_KEY = 'ebay3_failed_price_pushes';
    const VARIATION_GREEN_KEY = 'ebay3_variation_green_skus';

    function loadVariationGreenSkus() {
        try {
            const raw = localStorage.getItem(VARIATION_GREEN_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            return new Set(Array.isArray(arr) ? arr : []);
        } catch (e) {
            return new Set();
        }
    }

    function persistVariationGreenSkus() {
        try {
            localStorage.setItem(VARIATION_GREEN_KEY, JSON.stringify([...variationGreenSkus]));
        } catch (e) {}
    }

    let variationGreenSkus = loadVariationGreenSkus();
    
    // Save failed SKU to localStorage for background retry
    function saveFailedSkuForRetry(sku, price, retryCount = 0) {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            failedSkus[sku] = {
                sku: sku,
                price: price,
                retryCount: retryCount,
                timestamp: Date.now()
            };
            localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
        } catch (e) {
            console.error('Error saving failed SKU to localStorage:', e);
        }
    }
    
    // Remove SKU from background retry list
    function removeFailedSkuFromRetry(sku) {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            delete failedSkus[sku];
            localStorage.setItem(BACKGROUND_RETRY_KEY, JSON.stringify(failedSkus));
        } catch (e) {
            console.error('Error removing SKU from localStorage:', e);
        }
    }
    
    // Background retry function (runs even after page refresh)
    async function backgroundRetryFailedSkus() {
        try {
            const failedSkus = JSON.parse(localStorage.getItem(BACKGROUND_RETRY_KEY) || '{}');
            const skuKeys = Object.keys(failedSkus);
            
            if (skuKeys.length === 0) return;
            
            console.log(`Found ${skuKeys.length} failed SKU(s) to retry in background`);
            
            for (const sku of skuKeys) {
                const failedData = failedSkus[sku];
                
                // Skip if already retried 5 times
                if (failedData.retryCount >= 5) {
                    console.log(`SKU ${sku} has reached max retries (5), removing from retry list`);
                    removeFailedSkuFromRetry(sku);
                    continue;
                }
                
                // Skip if account is restricted
                let isAccountRestricted = false;
                if (table) {
                    try {
                        const rows = table.getRows();
                        for (let i = 0; i < rows.length; i++) {
                            const rowData = rows[i].getData();
                            if (rowData['(Child) sku'] === sku) {
                                if (rowData.SPRICE_STATUS === 'account_restricted') {
                                    isAccountRestricted = true;
                                }
                                break;
                            }
                        }
                    } catch (e) {
                        // Continue if table check fails
                    }
                }
                
                if (isAccountRestricted) {
                    console.log(`SKU ${sku} is account restricted, skipping background retry`);
                    removeFailedSkuFromRetry(sku);
                    continue;
                }
                
                // Try to find the cell in the table for UI update
                let cell = null;
                if (table) {
                    try {
                        const rows = table.getRows();
                        for (let i = 0; i < rows.length; i++) {
                            const rowData = rows[i].getData();
                            if (rowData['(Child) sku'] === sku) {
                                cell = rows[i].getCell('_accept');
                                break;
                            }
                        }
                    } catch (e) {
                        // Table might not be ready, continue without UI update
                    }
                }
                
                // Retry the price push once (background retry)
                const success = await applyPriceWithRetry(sku, failedData.price, cell, 0, true);
                
                if (!success) {
                    // Increment retry count if still failed
                    failedData.retryCount++;
                    saveFailedSkuForRetry(sku, failedData.price, failedData.retryCount);
                    console.log(`Background retry ${failedData.retryCount}/5 failed for SKU: ${sku}`);
                } else {
                    // Success - already removed from retry list in applyPriceWithRetry
                    // Update table if it's loaded
                    if (table) {
                        setTimeout(() => {
                            table.replaceData();
                        }, 1000);
                    }
                }
                
                // Small delay between SKUs to avoid burst calls
                await new Promise(resolve => setTimeout(resolve, 1000));
            }
        } catch (e) {
            console.error('Error in background retry:', e);
        }
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                if (row) {
                    row.update({ SPRICE_STATUS: 'processing' });
                }
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        let targetRow = row;
                        if (table && table.getRows) {
                            table.getRows().forEach(function(r) {
                                const d = r.getData();
                                if (d['(Child) sku'] === sku) targetRow = r;
                            });
                        }
                        const numSprice = (typeof sprice === 'number' && !isNaN(sprice)) ? sprice : parseFloat(sprice);
                        if (targetRow) {
                            if (numSprice === 0) {
                                targetRow.update({
                                    SPRICE: null,
                                    SPFT: null,
                                    SROI: null,
                                    SGPFT: null,
                                    SPRICE_STATUS: 'saved'
                                });
                            } else {
                                targetRow.update({
                                    SPRICE: numSprice,
                                    SPFT: response.data?.spft || response.spft_percent,
                                    SROI: response.data?.sroi || response.sroi_percent,
                                    SGPFT: response.data?.sgpft || response.sgpft_percent,
                                    SPRICE_STATUS: 'saved',
                                    has_custom_sprice: true
                                });
                            }
                            targetRow.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`, errorMsg);
                        
                        if (retryCount < 1) {
                            console.log(`Retrying SKU ${sku} in 2 seconds...`);
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            console.error(`Max retries reached for SKU ${sku}`);
                            if (row) {
                                row.update({ SPRICE_STATUS: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

    // Apply price with retry logic (for pushing to eBay3)
    async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
            const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
            const row = cell ? cell.getRow() : null;
            const rowData = row ? row.getData() : null;

        // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
        if (isBackgroundRetry) {
            try {
                const response = await $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { sku: sku, price: price }
                });

                if (response.errors && response.errors.length > 0) {
                    throw new Error(response.errors[0].message || 'API error');
                }

                // Success - update UI and remove from retry list
                if (rowData) {
                    rowData.SPRICE_STATUS = 'pushed';
                    row.update(rowData);
                }
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-check-double"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                }
                removeFailedSkuFromRetry(sku);
                return true;
            } catch (e) {
                // Background failure is handled by retryCount in backgroundRetryFailedSkus
                if (rowData) {
                    rowData.SPRICE_STATUS = 'error';
                    row.update(rowData);
                }
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-x"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                }
                return false;
            }
        }

        // Foreground mode (user click): up to 5 immediate retries with spinner UI
            if (retries === 0 && cell && $btn && row) {
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                $btn.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                if (rowData) {
                    rowData.SPRICE_STATUS = 'processing';
                    row.update(rowData);
                }
            }

            try {
                console.log(`🚀 eBay3 API Request - Push Price`, {
                    sku: sku,
                    price: price,
                    url: '/push-ebay3-price-tabulator',
                    timestamp: new Date().toISOString()
                });
                
                const response = await $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { sku: sku, price: price }
                });

                console.log(`✅ eBay3 API Response - Success`, {
                    sku: sku,
                    price: price,
                    response: response,
                    trackingIds: {
                        rlogId: response.rlogId || 'N/A',
                        correlationId: response.correlationId || 'N/A',
                        build: response.build || 'N/A',
                        timestamp: response.timestamp || 'N/A'
                    },
                    requestTimestamp: new Date().toISOString()
                });

                if (response.errors && response.errors.length > 0) {
                    throw new Error(response.errors[0].message || 'API error');
                }

                // Success - update UI
                if (rowData) {
                    rowData.SPRICE_STATUS = 'pushed';
                    row.update(rowData);
                }
                
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-check-double"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                }
                
                if (!isBackgroundRetry) {
                    showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                }
                // Remove from background retry list so we don't keep retrying this SKU
                removeFailedSkuFromRetry(sku);
                return true;
            } catch (xhr) {
                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to apply price';
                const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                const rlogId = xhr.responseJSON?.rlogId || 'N/A';
                
                console.error(`❌ eBay3 API Response - Error (Attempt ${retries + 1})`, {
                    sku: sku,
                    price: price,
                    errorCode: errorCode,
                    errorMessage: errorMsg,
                    trackingIds: {
                        rlogId: xhr.responseJSON?.rlogId || rlogId || 'N/A',
                        correlationId: xhr.responseJSON?.correlationId || 'N/A',
                        build: xhr.responseJSON?.build || 'N/A',
                        timestamp: xhr.responseJSON?.timestamp || 'N/A'
                    },
                    fullResponse: xhr.responseJSON,
                    requestTimestamp: new Date().toISOString()
                });

                // Check if this is an account restriction error (don't retry)
                const isAccountRestricted = errorCode === 'AccountRestricted' || 
                                            errorMsg.includes('ACCOUNT RESTRICTION') ||
                                            errorMsg.includes('account is restricted') ||
                                            errorMsg.includes('embargoed country');
            
            if (isAccountRestricted) {
                // Account restriction - don't retry, mark as account_restricted
                if (rowData) {
                    rowData.SPRICE_STATUS = 'account_restricted';
                    row.update(rowData);
                }
                
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-ban"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #ff6b00; padding: 0;');
                    $btn.attr('title', 'Account restricted - cannot update price');
                }
                
                showToast(`Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`, 'error');
                return false;
            }

                if (retries < 5) {
                    console.log(`Retrying SKU ${sku} in 5 seconds...`);
                    await new Promise(resolve => setTimeout(resolve, 5000));
                return applyPriceWithRetry(sku, price, cell, retries + 1, isBackgroundRetry);
                } else {
                // Final failure - mark error and save for background retry
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'error';
                        row.update(rowData);
                    }
                    
                    if ($btn && cell) {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fa-solid fa-x"></i>');
                        $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                    }
                    
                // Save for background retry (only if not already a background retry)
                saveFailedSkuForRetry(sku, price, 0);
                showToast(`Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`, 'error');
                    
                    return false;
                }
            }
        }

    // Retry function for applying price with up to 5 attempts (Promise-based for Apply All)
    function applyPriceWithRetryPromise(sku, price, maxRetries = 5, delay = 5000) {
        return new Promise((resolve, reject) => {
            let attempt = 0;
            
            function attemptApply() {
                attempt++;
                
                $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        price: price,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.errors && response.errors.length > 0) {
                            const errorMsg = response.errors[0].message || 'Unknown error';
                            const errorCode = response.errors[0].code || '';
                            console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg, 'Code:', errorCode);
                            
                            if (attempt < maxRetries) {
                                console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                                setTimeout(attemptApply, delay);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                reject({ error: true, response: response });
                            }
                        } else {
                            console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                            resolve({ success: true, response: response });
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseText || 'Network error';
                        console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                        
                        if (attempt < maxRetries) {
                            console.log(`Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`);
                            setTimeout(attemptApply, delay);
                        } else {
                            console.error(`Max retries reached for SKU ${sku}`);
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            }
            
            attemptApply();
        });
    }

    // Update selected count display
    function updateSelectedCount() {
        const count = selectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(
            count > 0 || decreaseModeActive || increaseModeActive || samePriceModeActive
        );
    }

    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        if (!table) return;
        
        const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
        
        if (filteredData.length === 0) {
            $('#select-all-checkbox').prop('checked', false);
            return;
        }
        
        const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
        
        const allFilteredSelected = filteredSkus.size > 0 && 
            Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
        
        $('#select-all-checkbox').prop('checked', allFilteredSelected);
    }

    $(document).ready(function() {
        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() {
            if (samePriceModeActive) {
                return;
            }
            const discountType = $(this).val();
            const $input = $('#discount-percentage-input');
            if (discountType === 'percentage') {
                $input.attr('placeholder', 'Enter percentage');
                $input.attr('max', '100');
            } else {
                $input.attr('placeholder', 'Enter value');
                $input.removeAttr('max');
            }
        });

        function syncEbay3DiscountBarForMode() {
            const $inp = $('#discount-percentage-input');
            if (samePriceModeActive) {
                $('#ebay3-discount-type-block').addClass('d-none');
                $('#discount-input-label').text('eBay price:');
                $inp.attr('placeholder', 'Each row — click Apply');
                $inp.prop('disabled', true);
                $inp.removeAttr('max');
                $inp.val('');
            } else {
                $('#ebay3-discount-type-block').removeClass('d-none');
                $('#discount-input-label').text('Value:');
                $inp.prop('disabled', false);
                const type = $('#discount-type-select').val();
                if (type === 'percentage') {
                    $inp.attr('placeholder', 'Enter percentage');
                    $inp.attr('max', '100');
                } else {
                    $inp.attr('placeholder', 'Enter value');
                    $inp.removeAttr('max');
                }
            }
        }

        function syncEbay3PriceModeUi() {
            if (!table || !table.getColumn) {
                return;
            }
            const $btn = $('#ebay3-price-mode-btn');
            const selectColumn = table.getColumn('_select');
            syncEbay3DiscountBarForMode();
            if (decreaseModeActive) {
                $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            if (increaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            if (samePriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                selectColumn.show();
                updateSelectedCount();
                return;
            }
            $btn.removeClass('btn-danger btn-success btn-outline-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Price %');
            selectColumn.hide();
            selectedSkus.clear();
            $('.sku-select-checkbox').prop('checked', false);
            $('#select-all-checkbox').prop('checked', false);
            $('#discount-input-container').hide();
            updateSelectedCount();
            updateSelectAllCheckbox();
        }

        $('#ebay3-price-mode-btn').on('click', function() {
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                decreaseModeActive = true;
            } else if (decreaseModeActive) {
                decreaseModeActive = false;
                increaseModeActive = true;
            } else if (increaseModeActive) {
                increaseModeActive = false;
                samePriceModeActive = true;
            } else {
                samePriceModeActive = false;
            }
            syncEbay3PriceModeUi();
        });

        // Apply all selected prices
        window.applyAllSelectedPrices = function() {
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU to apply prices', 'error');
                return;
            }
            
            const $btn = $('#apply-all-btn');
            if ($btn.length === 0) {
                showToast('Apply All button not found', 'error');
                return;
            }
            
            if ($btn.prop('disabled')) {
                return;
            }
            
            const originalHtml = $btn.html();
            
            // Disable button and show loading state
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
            
            // Get all table data to find SPRICE for selected SKUs
            const tableData = table.getData('all');
            const skusToProcess = [];
            
            // Build list of SKUs with their prices
            selectedSkus.forEach(sku => {
                const row = tableData.find(r => r['(Child) sku'] === sku);
                if (row) {
                    const sprice = parseFloat(row.SPRICE) || 0;
                    if (sprice > 0) {
                        skusToProcess.push({ sku: sku, price: sprice });
                    }
                }
            });
            
            if (skusToProcess.length === 0) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                showToast('No valid prices found for selected SKUs', 'error');
                return;
            }
            
            let successCount = 0;
            let errorCount = 0;
            let currentIndex = 0;
            
            // Process SKUs sequentially (one by one) with delay to avoid rate limiting
            function processNextSku() {
                if (currentIndex >= skusToProcess.length) {
                    // All SKUs processed
                    $btn.prop('disabled', false);
                    
                    if (errorCount === 0) {
                        // All successful
                        $btn.html(`<i class="fas fa-check-double" style="color: #28a745;"></i>`);
                        showToast(`Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`, 'success');
                        
                        // Reset to original state after 3 seconds
                        setTimeout(() => {
                            $btn.html(originalHtml);
                        }, 3000);
                    } else {
                        $btn.html(originalHtml);
                        showToast(`Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                    }
                    return;
                }
                
                const { sku, price } = skusToProcess[currentIndex];
                
                // Find the row and update button to show spinner
                const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                if (row) {
                    const acceptCell = row.getCell('_accept');
                    if (acceptCell) {
                        const $cellElement = $(acceptCell.getElement());
                        const $btnInCell = $cellElement.find('.apply-price-btn');
                        if ($btnInCell.length) {
                            $btnInCell.prop('disabled', true);
                            $btnInCell.html('<i class="fas fa-spinner fa-spin"></i>');
                            $btnInCell.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                        }
                    }
                }
                
                // First save to database (like SPRICE edit does), then push to eBay3
                console.log(`Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`);
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: price,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(saveResponse) {
                        console.log(`SKU ${sku}: Database save successful`, saveResponse);
                        if (saveResponse.error) {
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse.error);
                            errorCount++;
                            
                            // Update row data with error status
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                                
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                        $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                    }
                                }
                            }
                            
                            // Process next SKU
                            currentIndex++;
                            setTimeout(() => {
                                processNextSku();
                            }, 2000);
                            return;
                        }
                        
                        // After saving, push to eBay3 using retry function
                        console.log(`SKU ${sku}: Starting eBay3 price push...`);
                        applyPriceWithRetryPromise(sku, price, 5, 5000)
                            .then((result) => {
                            successCount++;
                                
                                // Update row data with pushed status instantly
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'pushed';
                                row.update(rowData);
                                
                                    // Update button to show green check-double
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-check-double"></i>');
                                        $btnInCell.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                                    }
                                }
                            }
                                
                                // Process next SKU with delay to avoid rate limiting (2 seconds between requests)
                            currentIndex++;
                                setTimeout(() => {
                                    processNextSku();
                                }, 2000);
                            })
                            .catch((error) => {
                            errorCount++;
                                
                                // Update row data with error status
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                                    
                                    // Update button to show error icon
                                    const acceptCell = row.getCell('_accept');
                                    if (acceptCell) {
                                        const $cellElement = $(acceptCell.getElement());
                                        const $btnInCell = $cellElement.find('.apply-price-btn');
                                        if ($btnInCell.length) {
                                            $btnInCell.prop('disabled', false);
                                            $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                            $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                        }
                                    }
                                }
                                
                                // Save for background retry
                                saveFailedSkuForRetry(sku, price, 0);
                                
                                // Process next SKU with delay to avoid rate limiting
                            currentIndex++;
                                setTimeout(() => {
                                    processNextSku();
                                }, 2000);
                        });
                    },
                    error: function(xhr) {
                        console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr.responseJSON || xhr.responseText);
                        errorCount++;
                        
                        // Update row data with error status
                        if (row) {
                            const rowData = row.getData();
                            rowData.SPRICE_STATUS = 'error';
                            row.update(rowData);
                            
                            const acceptCell = row.getCell('_accept');
                            if (acceptCell) {
                                const $cellElement = $(acceptCell.getElement());
                                const $btnInCell = $cellElement.find('.apply-price-btn');
                                if ($btnInCell.length) {
                                    $btnInCell.prop('disabled', false);
                                    $btnInCell.html('<i class="fa-solid fa-x"></i>');
                                    $btnInCell.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                                }
                            }
                        }
                        
                        // Process next SKU
                        currentIndex++;
                        setTimeout(() => {
                            processNextSku();
                        }, 2000);
                    }
                });
            }
            
            // Start processing
            processNextSku();
        };

        function roundToRetailPrice(price) {
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.01).toFixed(2);
        }
        function roundToRetailPrice49(price) {
            const roundedDollar = Math.ceil(price);
            return +(roundedDollar - 0.51).toFixed(2);
        }

        // Apply discount to selected SKUs (Price %: Decrease / Increase / Same Price — aligned with Open Box)
        function applyDiscount() {
            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Price % (Decrease, Increase, or Same Price)', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const rawInput = $('#discount-percentage-input').val();
            const inputValue = parseFloat(String(rawInput || '').replace(',', '.'));
            const discountType = $('#discount-type-select').val();

            if (!samePriceModeActive) {
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
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;
            const appliedAsSamePrice = samePriceModeActive;

            allData.forEach(row => {
                const isParent = row.Parent && row.Parent.startsWith('PARENT');
                if (isParent) return;

                const sku = row['(Child) sku'];
                if (!selectedSkus.has(sku)) return;

                const originalPrice = parseFloat(row['eBay Price']) || 0;
                if (originalPrice <= 0) return;

                let newPriceNum;
                if (samePriceModeActive) {
                    let newSPrice = roundToRetailPrice(originalPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                } else {
                    let newSPrice;
                    if (discountType === 'percentage') {
                        const decimal = inputValue / 100;
                        if (increaseModeActive) {
                            newSPrice = originalPrice * (1 + decimal);
                        } else {
                            newSPrice = originalPrice * (1 - decimal);
                        }
                    } else {
                        if (increaseModeActive) {
                            newSPrice = originalPrice + inputValue;
                        } else {
                            newSPrice = Math.max(0.01, originalPrice - inputValue);
                        }
                    }
                    newSPrice = Math.max(0.01, newSPrice);
                    newSPrice = roundToRetailPrice(newSPrice);
                    if (newSPrice.toFixed(2) === originalPrice.toFixed(2)) {
                        newSPrice = roundToRetailPrice49(newSPrice);
                    }
                    newPriceNum = parseFloat(newSPrice.toFixed(2));
                }

                const originalSPrice = parseFloat(row['SPRICE']) || 0;
                const tableRow = table.getRows().find(r => {
                    const rowData = r.getData();
                    return rowData['(Child) sku'] === sku;
                });

                if (tableRow) {
                    tableRow.update({ SPRICE: newPriceNum, SPRICE_STATUS: 'processing' });
                }

                saveSpriceWithRetry(sku, newPriceNum, tableRow)
                    .then(() => {
                        updatedCount++;
                        if (updatedCount + errorCount === totalSkus) {
                            if (errorCount === 0) {
                                showToast(
                                    appliedAsSamePrice
                                        ? `SPRICE set to eBay price for ${updatedCount} SKU(s)`
                                        : `Discount applied to ${updatedCount} SKU(s)`,
                                    'success'
                                );
                            } else {
                                showToast(
                                    appliedAsSamePrice
                                        ? `Updated ${updatedCount} SKU(s), ${errorCount} failed`
                                        : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                    'error'
                                );
                            }
                        }
                    })
                    .catch(() => {
                        errorCount++;
                        if (tableRow) {
                            tableRow.update({ SPRICE: originalSPrice });
                        }
                        if (updatedCount + errorCount === totalSkus) {
                            showToast(
                                appliedAsSamePrice
                                    ? `Updated ${updatedCount} SKU(s), ${errorCount} failed`
                                    : `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                'error'
                            );
                        }
                    });
            });
        }

        // Clear SPRICE for selected SKUs (use getRows() so tree child rows are included)
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            let clearedCount = 0;
            const allRows = table.getRows();

            allRows.forEach(function(tableRow) {
                const rowData = tableRow.getData();
                const sku = rowData['(Child) sku'] || '';
                if (!sku || sku.toUpperCase().includes('PARENT')) return;
                if (!selectedSkus.has(sku)) return;

                tableRow.update({
                    SPRICE: 0,
                    SPRICE_STATUS: 'processing'
                });

                saveSpriceWithRetry(sku, 0, tableRow)
                    .then(function(response) {
                        clearedCount++;
                        if (clearedCount === selectedSkus.size) {
                            showToast('SPRICE cleared for ' + clearedCount + ' SKU(s)', 'success');
                            // Refetch from server so table shows cleared state without page refresh
                            table.replaceData('/ebay3-data-json?_=' + Date.now()).then(function() {
                                if (typeof allTableData !== 'undefined') {
                                    allTableData = table.getData('all');
                                }
                                applyFilters();
                            }).catch(function(err) {
                                console.error('Reload after clear failed:', err);
                            });
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to clear SPRICE for', sku);
                    });
            });
        }

        // Clear SPRICE for selected SKUs only
        $('#clear-sprice-btn').on('click', function() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }
            clearSpriceForSelected();
        });

        // ==================== Play/Pause parent navigation (like pricing-master-cvr) ====================
        /** CVR 30 for a parent tree row: SCVR from API, else eBay L30 / views. */
        function parentRowCvr30(parentRow) {
            if (!parentRow) return 0;
            const scvr = parseFloat(parentRow['SCVR']);
            if (!isNaN(scvr)) return scvr;
            const views = parseFloat(parentRow.views || 0);
            const l30 = parseFloat(parentRow['eBay L30'] || 0);
            return views > 0 ? (l30 / views) * 100 : 0;
        }

        function getPlayModeParentList() {
            if (isPlayNavigationActive && playModeParentList && playModeParentList.length) {
                return playModeParentList;
            }
            return allTableData;
        }

        /** True when row is marked NR in NR/REQ column (excluded from Play). */
        function ebay3RowNrReqIsNr(row) {
            return !!(row && String(row.nr_req || '').trim() === 'NR');
        }

        /** Rows to show in Play: INV>0, not NR/REQ=NR; parent row only if INV>0 and not NR. */
        function ebay3BuildPlayDisplayData(parentRow) {
            if (!parentRow) return [];
            var rawChildren = (parentRow._children && Array.isArray(parentRow._children)) ? parentRow._children : [];
            var invKids = [];
            if (typeof ebay3Qty === 'function') {
                invKids = rawChildren.filter(function(c) {
                    if (ebay3RowNrReqIsNr(c)) return false;
                    return ebay3Qty(c.INV) > 0;
                });
            } else {
                invKids = rawChildren.filter(function(c) {
                    if (ebay3RowNrReqIsNr(c)) return false;
                    return (parseFloat(c.INV || 0) || 0) > 0;
                });
            }
            var parentInvOk = typeof ebay3Qty === 'function'
                ? ebay3Qty(parentRow.INV) > 0
                : (parseFloat(parentRow.INV || 0) || 0) > 0;
            var parentOkForPlay = parentInvOk && !ebay3RowNrReqIsNr(parentRow);
            return parentOkForPlay ? invKids.concat([parentRow]) : invKids.slice();
        }

        function showCurrentParentPlayView() {
            var parentList = getPlayModeParentList();
            if (!parentList || parentList.length === 0) return;
            if (currentPlayParentIndex < 0) {
                currentPlayParentIndex = 0;
            }

            while (currentPlayParentIndex < parentList.length) {
                var displayData = ebay3BuildPlayDisplayData(parentList[currentPlayParentIndex]);
                if (displayData.length > 0) {
                    table.clearFilter(true);
                    table.setData(displayData).then(function() {
                        updateCalcValues();
                        updateSummary();
                        updatePlayButtonStates();
                    });
                    return;
                }
                currentPlayParentIndex++;
            }

            if (isPlayNavigationActive) {
                showToast('No parent left with inventory — exiting Play', 'warning');
                stopPlayNavigation();
            }
        }

        function startPlayNavigation() {
            if (!allTableData || allTableData.length === 0) {
                showToast('No parent data to navigate', 'warning');
                return;
            }
            playModeParentList = [...allTableData].sort(function(a, b) {
                const da = parentRowCvr30(a);
                const db = parentRowCvr30(b);
                if (da !== db) return da - db;
                const sa = String(a['(Child) sku'] || a['Parent'] || '');
                const sb = String(b['(Child) sku'] || b['Parent'] || '');
                return sa.localeCompare(sb);
            });
            isPlayNavigationActive = true;
            currentPlayParentIndex = 0;
            showCurrentParentPlayView();
            $('#play-auto').hide();
            $('#play-pause').show();
            updatePlayButtonStates();
        }

        function stopPlayNavigation() {
            isPlayNavigationActive = false;
            currentPlayParentIndex = 0;
            playModeParentList = null;
            $('#play-pause').hide();
            $('#play-auto').show();
            $('#play-backward, #play-forward').prop('disabled', true);
            table.setData(allTableData);
            applyFilters();
        }

        function updatePlayButtonStates() {
            const plist = getPlayModeParentList();
            const len = plist && plist.length ? plist.length : 0;
            $('#play-backward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex <= 0);
            $('#play-forward').prop('disabled', !isPlayNavigationActive || currentPlayParentIndex >= len - 1);
            $('#play-auto').attr('title', isPlayNavigationActive ? 'Show all' : 'Play — parents low → high by CVR 30 (SCVR)');
            $('#play-pause').attr('title', 'Pause - show all');
        }

        function playNextParent() {
            const plist = getPlayModeParentList();
            if (!isPlayNavigationActive || !plist || !plist.length) return;
            if (currentPlayParentIndex >= plist.length - 1) return;
            currentPlayParentIndex++;
            showCurrentParentPlayView();
        }

        function playPreviousParent() {
            var parentList = getPlayModeParentList();
            if (!isPlayNavigationActive || !parentList || !parentList.length) return;
            if (currentPlayParentIndex <= 0) return;
            currentPlayParentIndex--;
            while (currentPlayParentIndex >= 0) {
                var displayData = ebay3BuildPlayDisplayData(parentList[currentPlayParentIndex]);
                if (displayData.length > 0) {
                    table.clearFilter(true);
                    table.setData(displayData).then(function() {
                        updateCalcValues();
                        updateSummary();
                        updatePlayButtonStates();
                    });
                    return;
                }
                currentPlayParentIndex--;
            }
            currentPlayParentIndex = 0;
            showCurrentParentPlayView();
        }

        $('#play-auto').on('click', startPlayNavigation);
        $('#play-pause').on('click', stopPlayNavigation);
        $('#play-forward').on('click', playNextParent);
        $('#play-backward').on('click', playPreviousParent);

        // Badge filter click handlers (same pattern as Ebay 2)
        $('.sold-filter-badge[data-filter="zero"]').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreSoldFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('.sold-filter-badge[data-filter="sold"]').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = false;
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = false;
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        $('#inv-stock-badge').on('click', function() {
            invStockFilterActive = !invStockFilterActive;
            missingFilterActive = false;
            mapFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        // Update badge styles based on active filters
        function updateBadgeStyles() {
            if (zeroSoldFilterActive) {
                $('.sold-filter-badge[data-filter="zero"]').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('.sold-filter-badge[data-filter="zero"]').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (moreSoldFilterActive) {
                $('.sold-filter-badge[data-filter="sold"]').css('opacity', '1').css('box-shadow', '0 0 10px rgba(14, 165, 233, 0.75)');
            } else {
                $('.sold-filter-badge[data-filter="sold"]').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (missingFilterActive) {
                $('#missing-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('#missing-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (mapFilterActive) {
                $('#map-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(40, 167, 69, 0.8)');
            } else {
                $('#map-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            if (invStockFilterActive) {
                $('#inv-stock-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(255, 193, 7, 0.8)');
            } else {
                $('#inv-stock-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }
        }

        // Store all unfiltered data for summary calculations
        let allTableData = [];

        // Play/Pause parent navigation (like pricing-master-cvr)
        let isPlayNavigationActive = false;
        let currentPlayParentIndex = 0;
        /** While play is active: top-level parents sorted by CVR 30 (SCVR) ascending. */
        let playModeParentList = null;

        let ebayMpImagePreviewHideTimer = null;
        let ebayMpImagePreviewEl = null;
        function ebayMpRemoveImagePreview() {
            if (ebayMpImagePreviewHideTimer) {
                clearTimeout(ebayMpImagePreviewHideTimer);
                ebayMpImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
            ebayMpImagePreviewEl = null;
        }
        function ebayMpCancelImagePreviewHide() {
            if (ebayMpImagePreviewHideTimer) {
                clearTimeout(ebayMpImagePreviewHideTimer);
                ebayMpImagePreviewHideTimer = null;
            }
        }
        function ebayMpScheduleImagePreviewHide() {
            ebayMpCancelImagePreviewHide();
            ebayMpImagePreviewHideTimer = setTimeout(ebayMpRemoveImagePreview, 220);
        }
        function ebayMpEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.ebayMpPreviewListeners === '1') return;
            wrap.dataset.ebayMpPreviewListeners = '1';
            wrap.addEventListener('mouseenter', ebayMpCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', ebayMpScheduleImagePreviewHide);
        }
        function ebayMpClampPreviewPosition(wrap, clientX, clientY) {
            const pad = 12;
            let left = clientX + pad;
            let top = clientY + pad;
            wrap.style.position = 'fixed';
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
            const rect = wrap.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const m = 8;
            if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
            if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
            if (left < m) left = m;
            if (top < m) top = m;
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
        }
        function ebayMpShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            ebayMpCancelImagePreviewHide();
            const existing = ebayMpImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    ebayMpClampPreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#image-hover-preview').forEach(function(el) { el.remove(); });
            ebayMpImagePreviewEl = null;
            const wrap = document.createElement('div');
            wrap.id = 'image-hover-preview';
            wrap.style.zIndex = '10050';
            wrap.style.pointerEvents = 'auto';
            wrap.style.border = '1px solid #ccc';
            wrap.style.background = '#fff';
            wrap.style.padding = '4px';
            wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
            wrap.style.borderRadius = '6px';
            const big = document.createElement('img');
            big.style.maxWidth = '350px';
            big.style.maxHeight = '350px';
            big.style.display = 'block';
            big.alt = '';
            big.src = fullUrl;
            wrap.appendChild(big);
            ebayMpEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            ebayMpImagePreviewEl = wrap;
            ebayMpClampPreviewPosition(wrap, clientX, clientY);
        }
        
        // Initialize Tabulator
        table = new Tabulator("#ebay3-table", {
            ajaxURL: "/ebay3-data-json",
            ajaxResponse: function(url, params, response) {
                // Store all unfiltered data for summary calculations
                allTableData = response || [];
                console.log('API Response - Total rows:', allTableData.length);
                
                // Calculate total L30 for verification
                let totalL30 = 0;
                let parentCount = 0;
                allTableData.forEach(row => {
                    const sku = row['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) {
                        parentCount++;
                    } else {
                        totalL30 += parseFloat(row['eBay L30'] || 0);
                    }
                });
                console.log('Total eBay3 L30 from API:', totalL30, '(excluding', parentCount, 'PARENT rows)');
                
                return response;
            },
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            dataTree: true,
            dataTreeStartExpanded: false,
            dataTreeChildField: "_children",
            dataTreeFilter: true,
            dataTreeChildColumnCalcs: true,
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{
                column: "Parent",
                dir: "asc"
            }],
            rowFormatter: function(row) {
                const sku = row.getData()['(Child) sku'] || '';
                if (sku.toUpperCase().includes('PARENT')) {
                    row.getElement().classList.add('parent-row');
                }
            },
            columns: [
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    frozen: true,
                    width: 150,
                    visible: false, 
                },
                {
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    frozen: true,
                    width: 50,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isSelected = selectedSkus.has(sku);
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                    }
                },
                {
                    title: "Image",
                    field: "image_path",
                    frozen: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            const u = String(value).replace(/"/g, '&quot;');
                            return '<img src="' + u + '" data-full="' + u + '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                        }
                        return '';
                    },
                    cellMouseOver: function(e, cell) {
                        const img = cell.getElement().querySelector('.hover-thumb');
                        if (!img) return;
                        ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                    },
                    cellMouseMove: function(e, cell) {
                        const preview = ebayMpImagePreviewEl;
                        if (!preview || !document.body.contains(preview)) return;
                        const img = cell.getElement().querySelector('.hover-thumb');
                        const fullUrl = img ? img.getAttribute('data-full') : '';
                        const big = preview.querySelector('img');
                        if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                        ebayMpClampPreviewPosition(preview, e.clientX, e.clientY);
                    },
                    cellMouseOut: function(e, cell) {
                        const related = e.relatedTarget;
                        if (related && typeof related.closest === 'function' && related.closest('#image-hover-preview')) {
                            ebayMpCancelImagePreviewHide();
                            return;
                        }
                        ebayMpScheduleImagePreviewHide();
                    },
                    headerSort: false,
                    width: 80,
                    visible: false
                },
                {
                    title: "Sku",
                    field: "(Child) sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = cell.getValue();
                        const isParent = sku && sku.toUpperCase().startsWith('PARENT');
                        
                        if (isParent) {
                            return `<span style="font-weight: 700;">${sku}</span>`;
                        }
                        
                        let html = `<span>${sku}</span>`;
                        
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        
                        return html;
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "Dil",
                    field: "E Dil%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;
                        
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
                    title: "CVR 60",
                    field: "CVR_60",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "CVR 45",
                    field: "CVR_45",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const val = parseFloat(cell.getValue()) || 0;
                        let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
                {
                    title: "CVR 30",
                    field: "SCVR",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        const aVal = parseFloat(aData.SCVR) || 0;
                        const bVal = parseFloat(bData.SCVR) || 0;
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const val = parseFloat(cell.getValue()) || 0;
                        const cvr60 = parseFloat(rowData.CVR_60) || 0;
                        const tol = 0.1;
                        let arrowHtml = '';
                        const isParent = rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT');
                        if (!isParent) {
                            let arrowColor = '#6c757d';
                            let arrowIcon = 'fa-minus';
                            if (val > cvr60 + tol) {
                                arrowColor = '#28a745';
                                arrowIcon = 'fa-arrow-up';
                            } else if (val < cvr60 - tol) {
                                arrowColor = '#a00211';
                                arrowIcon = 'fa-arrow-down';
                            }
                            arrowHtml = ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                        }
                        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml}`;
                    },
                    width: 65
                },
                {
                    title: "E Stock",
                    field: "eBay Stock",
                    hozAlign: "center",
                    width: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "E L60",
                    field: "eBay L60",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "E L45",
                    field: "eBay L45",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "E L30",
                    field: "eBay L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return Math.round(parseFloat(value) || 0);
                    }
                },
                {
                    title: "Growth",
                    field: "growth_percent",
                    hozAlign: "center",
                    width: 50,
                    sorter: function(a, b, aRow, bRow) {
                        function ebaySalesGrowthPct(row) {
                            const d = row.getData();
                            const l30 = parseFloat(d['eBay L30']) || 0;
                            const l60 = parseFloat(d['eBay L60']) || 0;
                            if (l60 === 0) return l30 > 0 ? 100 : 0;
                            return ((l30 - l60) / l60) * 100;
                        }
                        return ebaySalesGrowthPct(aRow) - ebaySalesGrowthPct(bRow);
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const l30 = parseFloat(rowData['eBay L30']) || 0;
                        const l60 = parseFloat(rowData['eBay L60']) || 0;
                        if (l60 === 0) {
                            if (l30 > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">+100%</span>`;
                            }
                            return '<span style="color: #6c757d;">0%</span>';
                        }
                        const growth = ((l30 - l60) / l60) * 100;
                        const growthRounded = Math.round(growth);
                        let color = '#6c757d';
                        if (growthRounded > 0) color = '#28a745';
                        else if (growthRounded < 0) color = '#dc3545';
                        const sign = growthRounded > 0 ? '+' : '';
                        return `<span style="color: ${color}; font-weight: bold;">${sign}${growthRounded}%</span>`;
                    }
                },
                {
                    title: "Missing",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const skuUpper = ((rowData['(Child) sku'] || '') + '').toUpperCase();
                        const isParentRow = skuUpper.includes('PARENT');
                        const children = rowData._children;

                        function isMissingItemId(itemId) {
                            return !itemId || itemId === null || itemId === '';
                        }

                        // Parent row: M if any child would show M; else green dot (including zero children)
                        if (isParentRow && children && Array.isArray(children)) {
                            if (children.length > 0) {
                                const anyChildMissing = children.some(function(ch) {
                                    return isMissingItemId(ch['eBay_item_id']);
                                });
                                if (anyChildMissing) {
                                    return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                                }
                            }
                            return '<span title="All child SKUs have eBay listing" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#28a745;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.12);"></span>';
                        }

                        // Child / leaf: Missing = no eBay3 item_id
                        const itemId = rowData['eBay_item_id'];
                        if (isMissingItemId(itemId)) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "Variation",
                    field: "_ebay3_variation",
                    hozAlign: "center",
                    width: 76,
                    headerSort: false,
                    headerTooltip: "Click dot: red ↔ green (saved in this browser)",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'] || '';
                        const green = sku && variationGreenSkus.has(sku);
                        const color = green ? '#28a745' : '#dc3545';
                        const title = green ? 'Marked OK — click for red' : 'Default — click for green';
                        const safeSku = String(sku).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                        return '<span class="ebay3-variation-dot" data-sku="' + safeSku + '" title="' + title + '" role="button" tabindex="0" ' +
                            'style="display:inline-block;width:14px;height:14px;border-radius:50%;background:' + color +
                            ';cursor:pointer;vertical-align:middle;box-shadow:0 0 0 1px rgba(0,0,0,0.12);"></span>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                        const sku = cell.getRow().getData()['(Child) sku'];
                        if (!sku) return;
                        if (variationGreenSkus.has(sku)) {
                            variationGreenSkus.delete(sku);
                        } else {
                            variationGreenSkus.add(sku);
                        }
                        persistVariationGreenSkus();
                        cell.getRow().reformat();
                        if (($('#variation-filter').val() || 'all') !== 'all') {
                            applyFilters();
                        }
                    }
                },
                {
                    title: "B/S",
                    field: "buyer_link",
                    hozAlign: "center",
                    width: 70,
                    headerTooltip: "eBay Buyer / Seller links (same source as pricing-master-cvr)",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData.buyer_link || '';
                        const sellerLink = rowData.seller_link || '';
                        if (!buyerLink && !sellerLink) return '-';
                        let html = '';
                        if (buyerLink) {
                            html += '<a href="' + buyerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1" title="Buyer link">B</a>';
                        }
                        if (sellerLink) {
                            html += '<a href="' + sellerLink.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Seller link">S</a>';
                        }
                        return html;
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                        const inv = parseFloat(rowData['INV']) || 0;
                        
                        if (inv > 0 && ebayStock > 0) {
                            if (inv === ebayStock) {
                                return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                            } else {
                                // Show signed difference: +X means INV has X more, -X means INV has X less
                                const diff = inv - ebayStock;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                        }
                        return '';
                    }
                },
               
                {
                    title: "View",
                    field: "views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        if (value >= 30) color = '#28a745';
                        else color = '#a00211';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                    },
                    width: 50
                },
               
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '')) {
                            value = 'REQ';
                        }
                        
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'] || '';
                        
                        return `<select class="form-select form-select-sm nr-req-dropdown" 
                            data-sku="${sku}"
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const lmpPrice = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const totalCompetitors = rowData.lmp_entries_total || 0;
                        const currentPrice = parseFloat(rowData['eBay Price'] || 0);

                        if (!lmpPrice && totalCompetitors === 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }

                        let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                        
                        // Show lowest price OUTSIDE modal
                        if (lmpPrice) {
                            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                            const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                            html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
                        }
                        
                        // Show link to open modal with all competitors
                        if (totalCompetitors > 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${sku}" 
                                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                                <i class="fa fa-eye"></i> View ${totalCompetitors}
                            </a>`;
                        }
                        
                        html += '</div>';
                        return html;
                    },
                    width: 70
                },
                {
                    title: "Prc",
                    field: "eBay Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "GPFT %",
                    field: "GPFT%",
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
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "AD%",
                    field: "AD%",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aKwSpend = parseFloat(aData['kw_spend_L30'] || 0);
                        const bKwSpend = parseFloat(bData['kw_spend_L30'] || 0);
                        
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aKwSpend > 0 && aVal === 0) aVal = 100;
                        if (bKwSpend > 0 && bVal === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const adPercent = parseFloat(value || 0);
                        const sku = rowData["(Child) sku"] || rowData.SKU || rowData.sku || '';
                        const iconHtml = sku ? ` <i class="fas fa-info-circle acos-info-icon" style="cursor: pointer; color: #6c757d; margin-left: 5px;" data-sku="${sku}" title="View Campaign Details"></i>` : '';
                        
                        if (kwSpend > 0 && adPercent === 0) {
                            return `<span style="color: #dc3545; font-weight: 600;">100%</span>${iconHtml}`;
                        }
                        
                        return `${parseFloat(value).toFixed(1)}%${iconHtml}`;
                    },
                    width: 70
                },
                {
                    title: "PFT %",
                    field: "PFT %",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const gpft = parseFloat(rowData['GPFT%'] || 0);
                        const ad = parseFloat(rowData['AD%'] || 0);
                        
                        const percent = gpft - ad;
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: "avg",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 50
                },
                {
                    title: "ROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: "avg",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 65
                },
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const hasCustomSprice = rowData.has_custom_sprice;
                        const currentPrice = parseFloat(rowData['eBay Price']) || 0;
                        const spriceNum = (value != null && value !== '') ? parseFloat(value) : NaN;
                        const sprice = isNaN(spriceNum) ? 0 : spriceNum;
                        
                        if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0) return '';
                        if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice.toFixed(2)) return '';
                        const formattedValue = `$${Number(sprice).toFixed(2)}`;
                        if (hasCustomSprice === false) {
                            return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                        }
                        return formattedValue;
                    },
                    width: 80
                },
                {
                    field: "_accept",
                    hozAlign: "center",
                    headerSort: false,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                            <span>Accept</span>
                            <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay3" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                            </button>
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const sprice = parseFloat(rowData.SPRICE) || 0;
                        const status = rowData.SPRICE_STATUS || null;

                        if (!sprice || sprice === 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }

                        let icon = '<i class="fas fa-check"></i>';
                        let iconColor = '#28a745';
                        let titleText = 'Apply Price to eBay3';

                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            iconColor = '#ffc107';
                            titleText = 'Price pushing in progress...';
                        } else if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price pushed to eBay3 (Double-click to mark as Applied)';
                        } else if (status === 'applied') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price applied to eBay3 (Double-click to change)';
                        } else if (status === 'saved') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'SPRICE saved (Click to push to eBay3)';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-x"></i>';
                            iconColor = '#dc3545';
                            titleText = 'Error applying price to eBay3';
                        } else if (status === 'account_restricted') {
                            icon = '<i class="fa-solid fa-ban"></i>';
                            iconColor = '#ff6b00';
                            titleText = 'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
                        }

                        return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                            ${icon}
                        </button>`;
                    },
                    cellClick: function(e, cell) {
                        const $target = $(e.target);
                        
                        // Handle double-click to change status from 'pushed' to 'applied'
                        if (e.originalEvent && e.originalEvent.detail === 2) {
                            const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                            const currentStatus = $btn.attr('data-status') || '';
                            
                            if (currentStatus === 'pushed') {
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                $.ajax({
                                    url: '/update-ebay3-sprice-status',
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                    },
                                    data: { sku: sku, status: 'applied' },
                                    success: function(response) {
                                        if (response.success) {
                                            table.replaceData();
                                            showToast('Status updated to Applied', 'success');
                                        }
                                    }
                                });
                            }
                            return;
                        }
                        
                        if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                            e.stopPropagation();
                            const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                            const sku = $btn.attr('data-sku') || $btn.data('sku');
                            const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                            const currentStatus = $btn.attr('data-status') || '';
                            
                            if (!sku || !price || price <= 0 || isNaN(price)) {
                                showToast('Invalid SKU or price', 'error');
                                return;
                            }
                            
                            // If status is 'saved' or null, first save SPRICE, then push to eBay3
                            if (currentStatus === 'saved' || !currentStatus) {
                                const row = cell.getRow();
                                row.update({ SPRICE_STATUS: 'processing' });
                                
                                saveSpriceWithRetry(sku, price, row)
                                    .then((response) => {
                                        // After saving, push to eBay3
                                        applyPriceWithRetry(sku, price, cell, 0);
                                    })
                                    .catch((error) => {
                                        row.update({ SPRICE_STATUS: 'error' });
                                        showToast('Failed to save SPRICE', 'error');
                                    });
                            } else {
                                // If already saved, just push to eBay3
                                applyPriceWithRetry(sku, price, cell, 0);
                            }
                        }
                    }
                },
                {
                    title: "S GPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "S PFT",
                    field: "SPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sgpft = parseFloat(rowData.SGPFT || 0);
                        const ad = parseFloat(rowData['AD%'] || 0);
                        
                        const percent = sgpft - ad;
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SROI",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SPEND L30",
                    field: "AD_Spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `
                            <span>$${value.toFixed(2)}</span>
                            <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 90
                },
                {
                    title: "KW SPEND L30",
                    field: "kw_spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 100
                },
                {
                    title: "KW %",
                    field: "kw_percent",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const pmtSpend = parseFloat(rowData['pmt_spend_L30'] || 0);
                        const total = kwSpend + pmtSpend;
                        const percent = total > 0 ? (kwSpend / total) * 100 : 0;
                        return `${percent.toFixed(1)}%`;
                    },
                    width: 70
                },
                {
                    title: "PMT SPEND L30",
                    field: "pmt_spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 100
                },
                {
                    title: "PMT %",
                    field: "pmt_percent",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const pmtSpend = parseFloat(rowData['pmt_spend_L30'] || 0);
                        const total = kwSpend + pmtSpend;
                        const percent = total > 0 ? (pmtSpend / total) * 100 : 0;
                        return `${percent.toFixed(1)}%`;
                    },
                    width: 70
                },
                // ======== KW Ads Columns (hidden by default) ========
                {
                    title: "Missing Ad",
                    field: "kw_hasCampaign",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
                        var hasCampaign = row.kw_campaign_id && row.kw_campaign_id !== '';
                        var nrlValue = row.NRL ? row.NRL.trim() : '';
                        var nraValue = row.NR ? row.NR.trim() : '';
                        var dotColor, title;
                        if (nrlValue === 'NRL' || nraValue === 'NRA') {
                            dotColor = '#ffc107'; title = 'NRL or NRA - Not Required';
                        } else if (hasCampaign) {
                            dotColor = '#28a745'; title = 'Campaign Exists';
                        } else {
                            dotColor = '#dc3545'; title = 'Campaign Missing';
                        }
                        return '<div style="display: flex; align-items: center; justify-content: center;">' +
                            '<span style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: ' + dotColor + ';" title="' + title + '"></span>' +
                            '</div>';
                    },
                    width: 80
                },
                {
                    title: "NRL",
                    field: "NRL",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    formatter: function(cell) {
                        var sku = cell.getRow().getData()['(Child) sku'];
                        var value = cell.getValue() || 'REQ';
                        return `<select class="form-select form-select-sm kw-nrl-dropdown" 
                                    data-sku="${sku}" data-field="NRL"
                                    style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                                </select>`;
                    },
                    cellClick: function(e, cell) { e.stopPropagation(); },
                    width: 70
                },
                {
                    title: "NRA",
                    field: "NR",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    formatter: function(cell) {
                        var rowData = cell.getRow().getData();
                        var sku = rowData['(Child) sku'];
                        var nrlValue = rowData.NRL || 'REQ';
                        var defaultValue = (nrlValue === 'NRL') ? 'NRA' : 'RA';
                        var value = (cell.getValue() || '').trim() || defaultValue;
                        return `<select class="form-select form-select-sm kw-nra-dropdown" 
                                    data-sku="${sku}" data-field="NR"
                                    style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                                </select>`;
                    },
                    cellClick: function(e, cell) { e.stopPropagation(); },
                    width: 70
                },
                {
                    title: "L7 VIEWS",
                    field: "l7_views",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        return parseInt(cell.getValue() || 0).toLocaleString();
                    },
                    width: 70
                },
                {
                    title: "CVR",
                    field: "kw_cvr",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        return parseFloat(cell.getValue() || 0).toFixed(1) + '%';
                    },
                    width: 70
                },
                {
                    title: "BGT",
                    field: "kw_campaignBudgetAmount",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        return parseFloat(cell.getValue() || 0).toFixed(0);
                    },
                    width: 60
                },
                {
                    title: "SBGT",
                    field: "kw_sbgt",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        var rowData = cell.getRow().getData();
                        var acos = parseFloat(rowData.kw_acos || 0);
                        var budget = parseFloat(rowData.kw_campaignBudgetAmount || 0);
                        var suggestedBudget = budget;
                        if (acos > 0 && acos <= 15) suggestedBudget = budget * 1.2;
                        else if (acos > 15 && acos <= 25) suggestedBudget = budget * 1.1;
                        else if (acos > 25 && acos <= 35) suggestedBudget = budget;
                        else if (acos > 35 && acos <= 50) suggestedBudget = budget * 0.9;
                        else if (acos > 50) suggestedBudget = budget * 0.8;
                        return suggestedBudget.toFixed(0);
                    },
                    width: 60
                },
                {
                    title: "ACOS",
                    field: "kw_acos",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        var rowData = cell.getRow().getData();
                        var kwSpend = parseFloat(rowData.kw_spend_L30 || 0);
                        var acos = parseFloat(cell.getValue() || 0);
                        var td = cell.getElement();
                        td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                        if (kwSpend === 0) {
                            return '<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">-' +
                                '<i class="fa-solid fa-circle-info" style="color: #6c757d; font-size: 12px;" title="No spend data"></i></div>';
                        }
                        if (acos > 0 && acos <= 15) td.classList.add('green-bg');
                        else if (acos > 15 && acos <= 25) td.classList.add('pink-bg');
                        else if (acos > 25) td.classList.add('red-bg');
                        return acos > 0 ? acos.toFixed(1) + '%' : '-';
                    },
                    width: 70
                },
                {
                    title: "KW CLICKS",
                    field: "kw_clicks",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        return parseInt(cell.getValue() || 0).toLocaleString();
                    },
                    width: 70
                },
                {
                    title: "KW AD SOLD",
                    field: "kw_ad_sold",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        return parseInt(cell.getValue() || 0).toLocaleString();
                    },
                    width: 70
                },
                {
                    title: "7UB%",
                    field: "kw_l7_spend",
                    hozAlign: "center",
                    visible: false,
                    sorter: function(a, b, aRow, bRow) {
                        var aData = aRow.getData();
                        var bData = bRow.getData();
                        var aUb7 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(aData.kw_l7_spend || 0) / (parseFloat(aData.kw_campaignBudgetAmount) * 7)) * 100 : 0;
                        var bUb7 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(bData.kw_l7_spend || 0) / (parseFloat(bData.kw_campaignBudgetAmount) * 7)) * 100 : 0;
                        return aUb7 - bUb7;
                    },
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
                        var l7_spend = parseFloat(row.kw_l7_spend) || 0;
                        var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                        var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                        var td = cell.getElement();
                        td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                        if (ub7 >= 66 && ub7 <= 99) td.classList.add('green-bg');
                        else if (ub7 > 99) td.classList.add('pink-bg');
                        else if (ub7 < 66 && budget > 0) td.classList.add('red-bg');
                        return ub7.toFixed(1) + '%';
                    },
                    width: 50
                },
                {
                    title: "1UB%",
                    field: "kw_l1_spend",
                    hozAlign: "center",
                    visible: false,
                    sorter: function(a, b, aRow, bRow) {
                        var aData = aRow.getData();
                        var bData = bRow.getData();
                        var aUb1 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(aData.kw_l1_spend || 0) / parseFloat(aData.kw_campaignBudgetAmount)) * 100 : 0;
                        var bUb1 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(bData.kw_l1_spend || 0) / parseFloat(bData.kw_campaignBudgetAmount)) * 100 : 0;
                        return aUb1 - bUb1;
                    },
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
                        var l1_spend = parseFloat(row.kw_l1_spend) || 0;
                        var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                        var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                        var td = cell.getElement();
                        td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                        if (ub1 >= 66 && ub1 <= 99) td.classList.add('green-bg');
                        else if (ub1 > 99) td.classList.add('pink-bg');
                        else if (ub1 < 66 && budget > 0) td.classList.add('red-bg');
                        return ub1.toFixed(1) + '%';
                    },
                    width: 50
                },
                {
                    title: "L7 CPC",
                    field: "kw_l7_cpc",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        var value = parseFloat(cell.getValue() || 0);
                        return value > 0 ? value.toFixed(2) : '-';
                    },
                    width: 70
                },
                {
                    title: "L1 CPC",
                    field: "kw_l1_cpc",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        var value = parseFloat(cell.getValue() || 0);
                        return value > 0 ? value.toFixed(2) : '-';
                    },
                    width: 70
                },
                {
                    title: "LBID",
                    field: "kw_last_sbid",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var value = cell.getValue();
                        if (!value || value === '' || value === '0' || value === 0) return '-';
                        return parseFloat(value).toFixed(2);
                    },
                    width: 70
                },
                {
                    title: "SBID",
                    field: "kw_sbid_calc",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rowData = cell.getRow().getData();
                        var nraValue = rowData.NR ? rowData.NR.trim() : "";
                        if (nraValue === 'NRA') return '-';
                        var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                        var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                        var budget = parseFloat(rowData.kw_campaignBudgetAmount) || 0;
                        var inv = parseFloat(rowData.INV || 0);
                        var price = parseFloat(rowData['eBay Price'] || 0);
                        var ub7 = budget > 0 ? (parseFloat(rowData.kw_l7_spend) || 0) / (budget * 7) * 100 : 0;
                        var ub1 = budget > 0 ? (parseFloat(rowData.kw_l1_spend) || 0) / budget * 100 : 0;
                        var lastSbidRaw = rowData.kw_last_sbid;
                        var lastSbid = (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw === '0') ? 0 : parseFloat(lastSbidRaw) || 0;
                        function getUbColor(ub) { return ub >= 66 && ub <= 99 ? 'green' : (ub > 99 ? 'pink' : 'red'); }
                        var ub7Color = getUbColor(ub7); var ub1Color = getUbColor(ub1);
                        if (ub7Color !== ub1Color) return '-';
                        var sbid = 0;
                        if (ub7 > 99 && ub1 > 99) {
                            if (l1Cpc > 0) sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                            else if (l7Cpc > 0) sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                            if (sbid === 0) return '-';
                            return sbid.toFixed(2);
                        }
                        var isOver = ub7 > 99 && ub1 > 99;
                        var isUnder = !isOver && budget > 0 && ub7 < 66 && ub1 < 66 && inv > 0;
                        if (isOver) {
                            if (l1Cpc > 1.25) sbid = Math.floor(l1Cpc * 0.80 * 100) / 100;
                            else if (l1Cpc > 0) sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                            if (price < 20 && sbid > 0.20) sbid = 0.20;
                        } else if (isUnder) {
                            var baseBid = lastSbid > 0 ? lastSbid : (l1Cpc > 0 ? l1Cpc : (l7Cpc > 0 ? l7Cpc : 0));
                            if (baseBid > 0) {
                                if (ub1 < 33) sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                else if (ub1 >= 33 && ub1 < 66) sbid = Math.floor(baseBid * 1.10 * 100) / 100;
                                else sbid = Math.floor(baseBid * 100) / 100;
                            }
                        } else {
                            if (l1Cpc > 0) sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                            else if (l7Cpc > 0) sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                        }
                        return sbid > 0 ? sbid.toFixed(2) : '-';
                    },
                    width: 70
                },
                {
                    title: "SBID M",
                    field: "kw_sbid_m",
                    hozAlign: "center",
                    visible: false,
                    editor: "input",
                    editorParams: { elementAttributes: { maxlength: "10" } },
                    formatter: function(cell) {
                        var value = cell.getValue();
                        if (!value || value === '' || value === '0' || value === 0) return '-';
                        return parseFloat(value).toFixed(2);
                    },
                    width: 70
                },
                {
                    title: "APR BID",
                    field: "kw_apr_bid",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rowData = cell.getRow().getData();
                        var apprSbid = rowData.kw_apprSbid || '';
                        if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) {
                            return `<div style="display: flex; justify-content: center; align-items: center;">
                                <i class="fa-solid fa-circle-check kw-update-bid-icon" style="color: #28a745; font-size: 20px; cursor: default;" title="Bid pushed: ${apprSbid}"></i>
                            </div>`;
                        } else {
                            return `<div style="display: flex; justify-content: center; align-items: center;">
                                <i class="fa-solid fa-check kw-update-bid-icon" style="color: #6c757d; font-size: 18px; cursor: pointer;" title="Click to push bid"></i>
                            </div>`;
                        }
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('kw-update-bid-icon')) {
                            e.stopPropagation();
                            var rowData = cell.getRow().getData();
                            var sku = rowData['(Child) sku'];
                            var sbidM = rowData.kw_sbid_m || '';
                            var apprSbid = rowData.kw_apprSbid || '';
                            if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) return;
                            if (!sbidM || sbidM === '' || sbidM === '0' || parseFloat(sbidM) === 0) {
                                alert('Please enter a value in SBID M column first'); return;
                            }
                            if (confirm('Are you sure you want to push bid ' + sbidM + ' for SKU ' + sku + '?')) {
                                $.ajax({
                                    url: '/update-ebay3-keywords-bid-price',
                                    method: 'PUT',
                                    data: { sku: sku, bid: sbidM, _token: '{{ csrf_token() }}' },
                                    success: function(response) {
                                        if (response.success) {
                                            cell.getRow().update({kw_apprSbid: sbidM});
                                            cell.reformat();
                                        } else { alert('Error: ' + (response.message || 'Failed to update bid')); }
                                    },
                                    error: function() { alert('Error: Failed to update bid'); }
                                });
                            }
                        }
                    },
                    width: 70
                },
                {
                    title: "Status",
                    field: "kw_campaignStatus",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
                        var campaignId = row.kw_campaign_id || '';
                        var status = row.kw_campaignStatus || 'PAUSED';
                        var isEnabled = status === 'RUNNING';
                        if (!campaignId || campaignId === '' || campaignId === null || campaignId === undefined) {
                            return '<span style="color: #999;">-</span>';
                        }
                        return `<div class="form-check form-switch d-flex justify-content-center">
                            <input class="form-check-input kw-campaign-status-toggle" 
                                   type="checkbox" role="switch" 
                                   data-campaign-id="${campaignId}"
                                   ${isEnabled ? 'checked' : ''}
                                   style="cursor: pointer; width: 3rem; height: 1.5rem;">
                        </div>`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('kw-campaign-status-toggle')) e.stopPropagation();
                    },
                    width: 80
                },
                // === PMT Ads Columns ===
                {
                    title: "PMT CBID",
                    field: "pmt_cbid",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().bid_percentage;
                        if (val === null || val === undefined || val === '') return '-';
                        return parseFloat(val).toFixed(2);
                    },
                    width: 80
                },
                {
                    title: "PMT ES BID",
                    field: "pmt_es_bid",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().suggested_bid;
                        if (val === null || val === undefined || val === '') return '-';
                        return parseFloat(val).toFixed(2);
                    },
                    width: 80
                },
                {
                    title: "PMT S BID",
                    field: "pmt_s_bid",
                    hozAlign: "center",
                    visible: false,
                    headerSortStartingDir: "desc",
                    sorter: function(a, b, aRow, bRow) {
                        var getVal = function(rd) {
                            var sold = parseInt(rd['eBay L30'], 10) || 0;
                            var es = parseFloat(rd.suggested_bid) || 0;
                            var v;
                            if (sold === 0) v = es;
                            else if (sold >= 1 && sold <= 5) v = 10;
                            else if (sold > 5) v = 8;
                            else v = es;
                            v = Math.min(v, 15);
                            return v > 0 ? v : (es > 0 ? es : 0);
                        };
                        var aVal = getVal(aRow.getData());
                        var bVal = getVal(bRow.getData());
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var sold = parseInt(rd['eBay L30'], 10) || 0;
                        var es = parseFloat(rd.suggested_bid) || 0;
                        var v;
                        // PMT S BID rules: L30 sold = 0 → ESbid; 1-5 → 10; >5 → 8; else → ESbid; cap at 15
                        if (sold === 0) v = es;
                        else if (sold >= 1 && sold <= 5) v = 10;
                        else if (sold > 5) v = 8;
                        else v = es;
                        v = Math.min(v, 15);
                        return v > 0 ? Number(v).toFixed(2) : (es > 0 ? es.toFixed(2) : '-');
                    },
                    width: 80
                },
                {
                    title: "PMT T Views",
                    field: "pmt_t_views",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().pmt_own_views;
                        return val ? parseInt(val).toLocaleString() : '0';
                    },
                    width: 80
                },
                {
                    title: "PMT L7 Views",
                    field: "pmt_l7_views",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().pmt_own_l7_views;
                        return val ? parseInt(val).toLocaleString() : '0';
                    },
                    width: 80
                },
                {
                    title: "PMT SCVR",
                    field: "pmt_scvr",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var views = parseFloat(rd.pmt_own_views) || 0;
                        var ebayL30 = parseFloat(rd.pmt_own_ebay_l30) || 0;
                        if (views <= 0) return '0.00%';
                        var scvr = (ebayL30 / views) * 100;
                        var color = '#6c757d';
                        if (scvr <= 4) color = 'red';
                        else if (scvr > 4 && scvr <= 7) color = '#daa520';
                        else if (scvr > 7 && scvr <= 10) color = 'green';
                        else color = '#E83E8C';
                        return '<span style="color:' + color + '; font-weight: 600;">' + scvr.toFixed(2) + '%</span>';
                    },
                    width: 80
                },
                {
                    title: "PMT ClkL7",
                    field: "pmt_clicks_l7",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().pmt_own_clicks_l7;
                        return val ? parseInt(val).toLocaleString() : '0';
                    },
                    width: 80
                },
                {
                    title: "PMT Clk30",
                    field: "pmt_clicks_l30",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var val = cell.getRow().getData().pmt_own_clicks_l30;
                        return val ? parseInt(val).toLocaleString() : '0';
                    },
                    width: 80
                },
                {
                    title: "PMT PFT",
                    field: "pmt_pft",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var gpft = parseFloat(rd['GPFT%']) || 0;
                        var val = gpft / 100;
                        var color = val >= 0 ? '#198754' : '#dc3545';
                        return '<span style="color:' + color + '">' + (val * 100).toFixed(2) + '%</span>';
                    },
                    width: 70
                },
                {
                    title: "PMT ROI",
                    field: "pmt_roi",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var roi = parseFloat(rd['ROI%']) || 0;
                        var color = roi >= 0 ? '#198754' : '#dc3545';
                        return '<span style="color:' + color + '">' + Math.round(roi) + '%</span>';
                    },
                    width: 70
                },
                {
                    title: "PMT TPFT%",
                    field: "pmt_tpft",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var gpft = parseFloat(rd['GPFT%']) || 0;
                        var adUpdates = parseFloat(rd.ad_updates) || 0;
                        var cbid = parseFloat(rd.bid_percentage) || 0;
                        var tpft = (gpft / 100) + (adUpdates / 100) - cbid;
                        var color = tpft >= 0 ? '#198754' : '#dc3545';
                        return '<span style="color:' + color + '">' + tpft.toFixed(2) + '</span>';
                    },
                    width: 80
                },
                {
                    title: "PMT TROI%",
                    field: "pmt_troi",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var rd = cell.getRow().getData();
                        var roi = parseFloat(rd['ROI%']) || 0;
                        var adUpdates = parseFloat(rd.ad_updates) || 0;
                        var cbid = parseFloat(rd.bid_percentage) || 0;
                        var troi = (roi / 100) + (adUpdates / 100) - cbid;
                        var color = troi >= 0 ? '#198754' : '#dc3545';
                        return '<span style="color:' + color + '">' + troi.toFixed(2) + '</span>';
                    },
                    width: 80
                },
                {
                    title: "PMT NRL",
                    field: "pmt_nrl",
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        var sku = cell.getRow().getData()['(Child) sku'];
                        var value = cell.getRow().getData().NRL || 'REQ';
                        var bgColor = value === 'NRL' ? '#dc3545' : '#28a745';
                        var label = value === 'NRL' ? 'NRL' : 'REQ';
                        return '<select class="form-select form-select-sm pmt-nrl-dropdown" data-sku="' + sku + '" data-field="NRL" style="min-width: 70px; background-color: ' + bgColor + '; color: #fff; padding: 2px 4px; font-size: 11px; border: none; border-radius: 4px;"><option value="REQ" ' + (value === 'REQ' ? 'selected' : '') + '>REQ</option><option value="NRL" ' + (value === 'NRL' ? 'selected' : '') + '>NRL</option></select>';
                    },
                    cellClick: function(e, cell) { e.stopPropagation(); },
                    width: 80
                }
            ]
        });

        /** Tabulator dataTree always draws parent before children; move each parent row after its last visible descendant. */
        function reorderEbay3ParentRowsBelowSkus() {
            if (!table) return;
            if (isPlayNavigationActive) return;
            if (($('#view-mode-filter').val() || '') === 'sku') return;

            var roots;
            try {
                roots = document.querySelectorAll('#ebay3-table .tabulator-row.parent-row.tabulator-tree-level-0');
            } catch (e) {
                return;
            }

            roots.forEach(function(level0) {
                var lastDescEl = null;
                var walker = level0.nextElementSibling;
                while (walker && !walker.classList.contains('tabulator-tree-level-0')) {
                    lastDescEl = walker;
                    walker = walker.nextElementSibling;
                }
                if (lastDescEl && lastDescEl.parentNode === level0.parentNode) {
                    lastDescEl.after(level0);
                }
            });
        }

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("(Child) sku", "like", value);
        });

        /** Parent + all child SKUs in the same eBay3 tree group (for NR cascade). */
        function ebay3GetNrCascadeSkus(primarySku) {
            var key = (primarySku || '').toString().trim();
            if (!key || !Array.isArray(allTableData) || !allTableData.length) {
                return [key];
            }
            for (var i = 0; i < allTableData.length; i++) {
                var pRow = allTableData[i];
                var pSku = ((pRow['(Child) sku'] || '') + '').trim();
                if (pSku.toUpperCase().indexOf('PARENT') === -1) {
                    continue;
                }
                var kids = pRow._children;
                if (!kids || !Array.isArray(kids) || kids.length === 0) {
                    if (pSku === key) {
                        return [pSku];
                    }
                    continue;
                }
                var match = (pSku === key) || kids.some(function(c) {
                    return (((c['(Child) sku'] || '') + '').trim() === key);
                });
                if (match) {
                    var out = [pSku];
                    kids.forEach(function(c) {
                        var cs = ((c['(Child) sku'] || '') + '').trim();
                        if (cs) {
                            out.push(cs);
                        }
                    });
                    return [...new Set(out)];
                }
            }
            if (typeof table !== 'undefined' && table && typeof table.searchRows === 'function') {
                var hits = table.searchRows('(Child) sku', '=', key);
                if (hits.length > 0) {
                    var pdata = hits[0].getData();
                    var parentVal = ((pdata.Parent || '') + '').trim();
                    if (parentVal) {
                        for (var j = 0; j < allTableData.length; j++) {
                            var pr = allTableData[j];
                            var ps = ((pr['(Child) sku'] || '') + '').trim();
                            if (ps.toUpperCase().indexOf('PARENT') === -1) {
                                continue;
                            }
                            if (((pr.Parent || '') + '').trim() !== parentVal) {
                                continue;
                            }
                            var ch2 = pr._children;
                            var out2 = [ps];
                            if (ch2 && Array.isArray(ch2)) {
                                ch2.forEach(function(c) {
                                    var cs = ((c['(Child) sku'] || '') + '').trim();
                                    if (cs) {
                                        out2.push(cs);
                                    }
                                });
                            }
                            return [...new Set(out2)];
                        }
                    }
                }
            }
            return [key];
        }

        function ebay3DeepUpdateNrReqForSkus(skuList, val) {
            var set = {};
            skuList.forEach(function(s) { set[s] = true; });
            function walk(rows) {
                if (!rows || !rows.length) {
                    return;
                }
                rows.forEach(function(r) {
                    if (set[r['(Child) sku']]) {
                        r.nr_req = val;
                    }
                    if (r._children && r._children.length) {
                        walk(r._children);
                    }
                });
            }
            walk(allTableData);
        }

        function ebay3UpdateVisibleRowsNrReq(skuList, val) {
            skuList.forEach(function(s) {
                var sku = (s || '').toString().trim();
                if (!sku) return;
                var rows = table.searchRows('(Child) sku', '=', sku);
                rows.forEach(function(r) {
                    r.update({ nr_req: val });
                });
            });
        }

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = ($select.attr('data-sku') || $select.data('sku') || '').toString().trim();

            if (!sku) {
                console.error('Could not find SKU in dropdown data attribute');
                showToast('Could not find SKU', 'error');
                return;
            }

            const skusToSave = (value === 'NR')
                ? ebay3GetNrCascadeSkus(sku)
                : [sku];

            console.log('Saving NR/REQ for SKU(s):', skusToSave, 'Value:', value);

            const token = '{{ csrf_token() }}';
            const saveOne = function(s) {
                return $.ajax({
                    url: '/listing_ebaythree/save-status',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        _token: token,
                        sku: s,
                        nr_req: value
                    }
                });
            };

            const saveOk = function(response) {
                return response && (response.status === 'success' || response.status === true);
            };

            const onSuccess = function() {
                if (value === 'NR') {
                    ebay3DeepUpdateNrReqForSkus(skusToSave, 'NR');
                    ebay3UpdateVisibleRowsNrReq(skusToSave, 'NR');
                } else {
                    ebay3DeepUpdateNrReqForSkus([sku], value);
                    ebay3UpdateVisibleRowsNrReq([sku], value);
                }
                const message = value === 'REQ'
                    ? 'REQ updated'
                    : (value === 'NR'
                        ? (skusToSave.length > 1 ? ('NR applied to parent and ' + (skusToSave.length - 1) + ' SKU(s)') : 'NR updated')
                        : 'Status cleared');
                showToast(message, 'success');
            };

            const onFail = function(xhr, labelSku) {
                console.error('Failed to save NR/REQ for', labelSku, 'Error:', xhr && xhr.responseText);
                showToast('Failed to save NR/REQ for ' + (labelSku || sku), 'error');
            };

            var idx = 0;
            function saveNext() {
                if (idx >= skusToSave.length) {
                    onSuccess();
                    return;
                }
                var s = skusToSave[idx++];
                saveOne(s)
                    .done(function(response) {
                        if (saveOk(response)) {
                            saveNext();
                        } else {
                            showToast((response && response.message) || 'Failed to save status', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        onFail(xhr, s);
                    });
            }

            saveNext();
        });

        table.on('cellEdited', function(cell) {
            var row = cell.getRow();
            var data = row.getData();
            var field = cell.getColumn().getField();
            var value = cell.getValue();

            if (field === 'SPRICE') {
                row.update({ SPRICE_STATUS: 'processing' });
                
                saveSpriceWithRetry(data['(Child) sku'], value, row)
                    .then((response) => {
                        showToast('SPRICE saved successfully', 'success');
                    })
                    .catch((error) => {
                        showToast('Failed to save SPRICE', 'error');
                    });
            } else if (field === 'Listed' || field === 'Live') {
                $.ajax({
                    url: '/ebay3/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['(Child) sku'],
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        showToast(field + ' status updated successfully', 'success');
                    },
                    error: function(error) {
                        showToast('Failed to update ' + field + ' status', 'error');
                    }
                });
            }
        });

        /** Parse Shop/PM stock (INV) for row filters: numbers, comma thousands, trim, no false "0" from bad parse. */
        function ebay3Qty(v) {
            if (v == null || v === '' || v === false) {
                return 0;
            }
            if (typeof v === 'number' && Number.isFinite(v)) {
                return v;
            }
            var s = String(v).replace(/,/g, '').replace(/[\s\u00A0]+/g, '').trim();
            if (s === '' || s === '—' || s === '-' || s === 'N/A' || s === 'n/a') {
                return 0;
            }
            var n = parseFloat(s);
            return Number.isFinite(n) ? n : 0;
        }

        // Apply filters
        function applyFilters() {
            if (isPlayNavigationActive) {
                showCurrentParentPlayView();
                return;
            }

            const viewModeFilter = $('#view-mode-filter').val();
            const invFilter = $('#inv-filter').val() || 'more';
            const ebayStockFilter = $('#ebay-stock-filter').val();
            const el30Filter = $('#el30-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const roiFilter = $('#roi-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const cvrTrendFilter = $('#cvr-trend-filter').val();
            const spriceFilter = $('#sprice-filter').val();
            const variationFilter = $('#variation-filter').val() || 'all';
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

            table.clearFilter(true);
            
            // Disable tree mode for SKU-only view
            if (viewModeFilter === 'sku') {
                // Flatten the tree for SKU-only view
                const flatData = [];
                allTableData.forEach(parent => {
                    if (parent._children && Array.isArray(parent._children)) {
                        // Add only child rows, skip parent
                        flatData.push(...parent._children);
                    } else {
                        // If no children, check if it's not a parent row
                        const sku = parent['(Child) sku'] || '';
                        if (!sku.toUpperCase().includes('PARENT')) {
                            flatData.push(parent);
                        }
                    }
                });
                table.setData(flatData);
            } else {
                // Restore original tree data for parent or both mode
                table.setData(allTableData);
            }

            // View Mode Filter - controls parent/SKU/both visibility
            if (viewModeFilter === 'parent') {
                // Show only parent rows, hide child rows
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    return sku.toUpperCase().includes('PARENT');
                });
            }
            // If 'both' is selected, no additional filter needed
            // If 'sku' is selected, data is already filtered above

            if (invFilter === 'zero') {
                table.addFilter(function(data) {
                    if (!data) {
                        return false;
                    }
                    return ebay3Qty(data.INV) === 0;
                });
            } else if (invFilter === 'more') {
                table.addFilter(function(data) {
                    if (!data) {
                        return false;
                    }
                    return ebay3Qty(data.INV) > 0;
                });
            }

            if (ebayStockFilter === 'zero') {
                table.addFilter(function(data) {
                    const estock = parseFloat(data['eBay Stock'] || data['E Stock'] || 0) || 0;
                    return estock === 0;
                });
            } else if (ebayStockFilter === 'more') {
                table.addFilter(function(data) {
                    const estock = parseFloat(data['eBay Stock'] || data['E Stock'] || 0) || 0;
                    return estock > 0;
                });
            }

            if (el30Filter === 'zero') {
                table.addFilter(function(data) {
                    return (parseFloat(data['eBay L30'] || 0) || 0) === 0;
                });
            } else if (el30Filter === 'more') {
                table.addFilter(function(data) {
                    return (parseFloat(data['eBay L30'] || 0) || 0) > 0;
                });
            }

            const growthSign = $('#growth-sign-filter').val();
            if (growthSign && growthSign !== 'all') {
                table.addFilter(function(data) {
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    const l60 = parseFloat(data['eBay L60']) || 0;
                    let growth = 0;
                    if (l60 > 0) {
                        growth = ((l30 - l60) / l60) * 100;
                    } else if (l30 > 0) {
                        growth = 100;
                    }
                    const g = Math.round(growth);
                    if (growthSign === 'negative') return g < 0;
                    if (growthSign === 'zero') return g === 0;
                    if (growthSign === 'positive') return g > 0;
                    return true;
                });
            }

            // Skip other filters for PARENT rows in tree mode
            if (nrlFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows
                    const sku = data['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) return true;
                    
                    if (nrlFilter === 'REQ') {
                        return data.nr_req === 'REQ';
                    } else if (nrlFilter === 'NR') {
                        return data.nr_req === 'NR';
                    }
                    return true;
                });
            }

            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const gpft = parseFloat(data['GPFT%']) || 0;
                    
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                    if (gpftFilter === '50plus') return gpft >= 50;
                    return true;
                });
            }

            if (roiFilter !== 'all') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) return true;
                    const roiVal = parseFloat(data['ROI%']) || 0;
                    if (roiFilter === 'lt40') return roiVal < 40;
                    if (roiFilter === 'gt250') return roiVal > 250;
                    const [min, max] = roiFilter.split('-').map(Number);
                    return roiVal >= min && roiVal <= max;
                });
            }

            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const views = parseFloat(data.views || 0);
                    const l30 = parseFloat(data['eBay L30'] || 0);
                    const cvr = views > 0 ? (l30 / views) * 100 : 0;
                    
                    const cvrRounded = Math.round(cvr * 100) / 100;
                    
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-2') return cvrRounded > 0 && cvrRounded <= 2;
                    if (cvrFilter === '2-4') return cvrRounded > 2 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // CVR trend filter: CVR 60 vs CVR 30
            if (cvrTrendFilter !== 'all') {
                const cvrTrendTol = 0.1;
                table.addFilter(function(data) {
                    const cvr30 = parseFloat(data['SCVR'] || 0);
                    const cvr60 = parseFloat(data['CVR_60'] || 0);
                    if (cvrTrendFilter === 'l60_gt_l30') return cvr60 > cvr30 + cvrTrendTol;
                    if (cvrTrendFilter === 'l30_gt_l60') return cvr30 > cvr60 + cvrTrendTol;
                    if (cvrTrendFilter === 'equal') return Math.abs(cvr60 - cvr30) <= cvrTrendTol;
                    return true;
                });
            }

            if (spriceFilter === 'blank') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    const sprice = data.SPRICE;
                    if (sprice == null || sprice === '') return true;
                    const num = parseFloat(sprice);
                    return isNaN(num) || num <= 0;
                });
            }

            // DIL% filter (OV dil: L30 / INV) — same bands as before
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;

                    const inv = parseFloat(data.INV) || 0;
                    const l30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // ======== KW Ads Section Filters ========
            var sectionFilter = $('#section-filter').val() || 'all';
            if (sectionFilter === 'kw_ads') {
                // KW Utilization filter
                var kwUtilFilter = $('#kw-utilization-filter').val();
                if (kwUtilFilter && kwUtilFilter !== 'all') {
                    table.addFilter(function(data) {
                        var budget = parseFloat(data.kw_campaignBudgetAmount) || 0;
                        if (budget === 0) return false;
                        var ub7 = (parseFloat(data.kw_l7_spend) || 0) / (budget * 7) * 100;
                        var ub1 = (parseFloat(data.kw_l1_spend) || 0) / budget * 100;
                        var ub7Color = ub7 >= 66 && ub7 <= 99 ? 'green' : (ub7 > 99 ? 'pink' : 'red');
                        var ub1Color = ub1 >= 66 && ub1 <= 99 ? 'green' : (ub1 > 99 ? 'pink' : 'red');
                        return (ub7Color + '-' + ub1Color) === kwUtilFilter;
                    });
                }

                // KW Campaign Status filter
                var kwStatusFilter = $('#kw-status-filter').val();
                if (kwStatusFilter && kwStatusFilter !== 'all') {
                    table.addFilter(function(data) {
                        return (data.kw_campaignStatus || '') === kwStatusFilter;
                    });
                }

                // KW NRA filter
                var kwNraFilter = $('#kw-nra-filter').val();
                if (kwNraFilter && kwNraFilter !== 'all') {
                    table.addFilter(function(data) {
                        var nra = (data.NR || '').trim();
                        if (kwNraFilter === 'NRA') return nra === 'NRA';
                        if (kwNraFilter === 'RA') return nra === 'RA' || nra === '' || nra === null;
                        if (kwNraFilter === 'LATER') return nra === 'LATER';
                        return true;
                    });
                }

                // KW NRL filter
                var kwNrlFilter = $('#kw-nrl-filter').val();
                if (kwNrlFilter && kwNrlFilter !== 'all') {
                    table.addFilter(function(data) {
                        var nrl = (data.NRL || '').trim();
                        if (kwNrlFilter === 'NRL') return nrl === 'NRL';
                        if (kwNrlFilter === 'REQ') return nrl !== 'NRL';
                        return true;
                    });
                }

                // KW SBID M filter
                var kwSbidmFilter = $('#kw-sbidm-filter').val();
                if (kwSbidmFilter && kwSbidmFilter !== 'all') {
                    table.addFilter(function(data) {
                        var sbidM = data.kw_sbid_m;
                        if (kwSbidmFilter === 'blank') return !sbidM || sbidM === '' || sbidM === '0' || parseFloat(sbidM) === 0;
                        if (kwSbidmFilter === 'data') return sbidM && sbidM !== '' && sbidM !== '0' && parseFloat(sbidM) > 0;
                        return true;
                    });
                }

                // KW Range filters
                var kw1ubMin = parseFloat($('#kw-range-1ub-min').val()) || null;
                var kw1ubMax = parseFloat($('#kw-range-1ub-max').val()) || null;
                if (kw1ubMin !== null || kw1ubMax !== null) {
                    table.addFilter(function(data) {
                        var budget = parseFloat(data.kw_campaignBudgetAmount) || 0;
                        var ub1 = budget > 0 ? (parseFloat(data.kw_l1_spend) || 0) / budget * 100 : 0;
                        if (kw1ubMin !== null && ub1 < kw1ubMin) return false;
                        if (kw1ubMax !== null && ub1 > kw1ubMax) return false;
                        return true;
                    });
                }
                var kw7ubMin = parseFloat($('#kw-range-7ub-min').val()) || null;
                var kw7ubMax = parseFloat($('#kw-range-7ub-max').val()) || null;
                if (kw7ubMin !== null || kw7ubMax !== null) {
                    table.addFilter(function(data) {
                        var budget = parseFloat(data.kw_campaignBudgetAmount) || 0;
                        var ub7 = budget > 0 ? (parseFloat(data.kw_l7_spend) || 0) / (budget * 7) * 100 : 0;
                        if (kw7ubMin !== null && ub7 < kw7ubMin) return false;
                        if (kw7ubMax !== null && ub7 > kw7ubMax) return false;
                        return true;
                    });
                }
                var kwLbidMin = parseFloat($('#kw-range-lbid-min').val()) || null;
                var kwLbidMax = parseFloat($('#kw-range-lbid-max').val()) || null;
                if (kwLbidMin !== null || kwLbidMax !== null) {
                    table.addFilter(function(data) {
                        var lbid = parseFloat(data.kw_last_sbid) || 0;
                        if (kwLbidMin !== null && lbid < kwLbidMin) return false;
                        if (kwLbidMax !== null && lbid > kwLbidMax) return false;
                        return true;
                    });
                }
                var kwAcosMin = parseFloat($('#kw-range-acos-min').val()) || null;
                var kwAcosMax = parseFloat($('#kw-range-acos-max').val()) || null;
                if (kwAcosMin !== null || kwAcosMax !== null) {
                    table.addFilter(function(data) {
                        var acos = parseFloat(data.kw_acos) || 0;
                        if (kwAcosMin !== null && acos < kwAcosMin) return false;
                        if (kwAcosMax !== null && acos > kwAcosMax) return false;
                        return true;
                    });
                }
                var kwViewsMin = parseFloat($('#kw-range-views-min').val()) || null;
                var kwViewsMax = parseFloat($('#kw-range-views-max').val()) || null;
                if (kwViewsMin !== null || kwViewsMax !== null) {
                    table.addFilter(function(data) {
                        var views = parseFloat(data.views) || 0;
                        if (kwViewsMin !== null && views < kwViewsMin) return false;
                        if (kwViewsMax !== null && views > kwViewsMax) return false;
                        return true;
                    });
                }
                var kwL7ViewsMin = parseFloat($('#kw-range-l7views-min').val()) || null;
                var kwL7ViewsMax = parseFloat($('#kw-range-l7views-max').val()) || null;
                if (kwL7ViewsMin !== null || kwL7ViewsMax !== null) {
                    table.addFilter(function(data) {
                        var l7Views = parseFloat(data.l7_views) || 0;
                        if (kwL7ViewsMin !== null && l7Views < kwL7ViewsMin) return false;
                        if (kwL7ViewsMax !== null && l7Views > kwL7ViewsMax) return false;
                        return true;
                    });
                }
            }

            // ======== PMT Ads Section Filters ========
            if ($('#section-filter').val() === 'pmt_ads') {
                // PMT Dropdown color filters
                for (var pmtKey in pmtDropdownFilters) {
                    var pmtColor = pmtDropdownFilters[pmtKey];
                    if (pmtColor === 'all') continue;

                    (function(filterKey, filterColor) {
                        table.addFilter(function(data) {
                            var val, color;

                            if (filterKey === 'pmt_ov_dil') {
                                var inv = parseFloat(data['INV']) || 0;
                                var l30 = parseFloat(data['L30']) || 0;
                                val = inv === 0 ? 0 : (l30 / inv) * 100;
                                color = val >= 50 ? 'pink' : (val >= 25 ? 'green' : (val >= 16.66 ? 'yellow' : 'red'));
                            } else if (filterKey === 'pmt_e_dil') {
                                var inv2 = parseFloat(data['INV']) || 0;
                                var eL30 = parseFloat(data['eBay L30']) || 0;
                                val = inv2 === 0 ? 0 : (eL30 / inv2) * 100;
                                color = val >= 50 ? 'pink' : (val >= 25 ? 'green' : (val >= 16.66 ? 'yellow' : 'red'));
                            } else if (filterKey === 'pmt_clk_l7') {
                                val = parseFloat(data['pmt_own_clicks_l7']) || 0;
                                color = val > 0 ? 'green' : 'red';
                            } else if (filterKey === 'pmt_clk_l30') {
                                val = parseFloat(data['pmt_own_clicks_l30']) || 0;
                                color = val > 0 ? 'green' : 'red';
                            } else if (filterKey === 'pmt_pft') {
                                val = parseFloat(data['PFT %']) || 0;
                                var pftPct = val * 100;
                                if (pftPct < 0) color = 'red';
                                else if (pftPct >= 0 && pftPct < 10) color = 'yellow';
                                else if (pftPct >= 10 && pftPct < 20) color = 'blue';
                                else if (pftPct >= 20 && pftPct < 40) color = 'green';
                                else color = 'pink';
                            } else if (filterKey === 'pmt_roi') {
                                val = parseFloat(data['ROI%']) || 0;
                                var roiPct = val * 100;
                                if (roiPct < 0) color = 'red';
                                else if (roiPct >= 0 && roiPct < 50) color = 'yellow';
                                else if (roiPct >= 50 && roiPct < 100) color = 'green';
                                else color = 'pink';
                            } else if (filterKey === 'pmt_tacos') {
                                val = parseFloat(data['AD%']) || 0;
                                if (val <= 0) color = 'pink';
                                else if (val > 0 && val <= 5) color = 'green';
                                else if (val > 5 && val <= 10) color = 'blue';
                                else if (val > 10 && val <= 20) color = 'yellow';
                                else color = 'red';
                            } else if (filterKey === 'pmt_scvr') {
                                var views3 = parseFloat(data.pmt_own_views || 0);
                                var eL303 = parseFloat(data.pmt_own_ebay_l30 || 0);
                                val = views3 > 0 ? (eL303 / views3) * 100 : 0;
                                if (val === 0) color = 'red';
                                else if (val > 0 && val <= 1) color = 'yellow';
                                else if (val > 1 && val <= 3) color = 'green';
                                else if (val > 3) color = 'pink';
                                if (filterColor === 'blue') {
                                    return val <= 0.5;
                                }
                            }

                            return color === filterColor;
                        });
                    })(pmtKey, pmtColor);
                }

                // PMT Range filters
                var hasPmtRange = Object.values(pmtRangeFilters).some(function(f) { return f.min !== null || f.max !== null; });
                if (hasPmtRange) {
                    table.addFilter(function(data) {
                        var tViews = parseFloat(data.pmt_own_views) || 0;
                        var l7Views = parseFloat(data.pmt_own_l7_views) || 0;
                        var cbid = parseFloat(data.bid_percentage) || 0;
                        var views4 = parseFloat(data.pmt_own_views || 0);
                        var eL304 = parseFloat(data.pmt_own_ebay_l30 || 0);
                        var scvrVal = views4 > 0 ? (eL304 / views4) * 100 : 0;

                        var vals = {
                            't_views': tViews, 'l7_views': l7Views,
                            'cbid': cbid, 'scvr': scvrVal
                        };

                        for (var key in pmtRangeFilters) {
                            var rf = pmtRangeFilters[key];
                            if (rf.min !== null && vals[key] < rf.min) return false;
                            if (rf.max !== null && vals[key] > rf.max) return false;
                        }
                        return true;
                    });
                }
            }

            // 0 Sold filter (based on eBay L30) - triggered by badge click
            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 === 0;
                });
            }

            // > 0 Sold filter (based on eBay L30) - triggered by badge click
            if (moreSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 > 0;
                });
            }

            // Missing filter - show SKUs missing in eBay
            if (missingFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const itemId = data['eBay_item_id'];
                    // Missing: SKU exists in ProductMaster but not in eBay3 (no item_id)
                    return !itemId || itemId === null || itemId === '';
                });
            }

            // Variation column state: default red; green = user-marked in this browser
            if (variationFilter === 'red') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (!sku) return true;
                    return !variationGreenSkus.has(sku);
                });
            } else if (variationFilter === 'green') {
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    if (!sku) return true;
                    return variationGreenSkus.has(sku);
                });
            }

            // Map filter - show SKUs where INV = eBay Stock
            if (mapFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayStock = parseFloat(data['eBay Stock']) || 0;
                    const inv = parseFloat(data['INV']) || 0;
                    return inv > 0 && ebayStock > 0 && inv === ebayStock;
                });
            }

            // N Map filter - show SKUs where INV != eBay Stock (not mapped)
            if (invStockFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayStock = parseFloat(data['eBay Stock']) || 0;
                    const inv = parseFloat(data['INV']) || 0;
                    // Show both: INV > Stock AND Stock > INV
                    return inv > 0 && ebayStock > 0 && inv !== ebayStock;
                });
            }

            updateCalcValues();
            updateSummary();
            setTimeout(function() {
                updateSelectAllCheckbox();
            }, 100);
        }

        $('#view-mode-filter, #inv-filter, #ebay-stock-filter, #el30-filter, #variation-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter').on('change', function() {
            applyFilters();
            if ($('#section-filter').val() === 'kw_ads') {
                updateKwAdsStats();
            }
        });

        $('#growth-sign-filter').on('change', function() {
            applyFilters();
            if ($('#section-filter').val() === 'kw_ads') {
                updateKwAdsStats();
            }
        });

        // ======== Section Filter: show/hide column groups ========
        var pricingOnlyColumns = [
            'image_path', 'Missing', '_ebay3_variation', 'eBay Stock', 'MAP', 'nr_req', 'CVR_60', 'CVR_45', 'SCVR',
            'GPFT%', 'AD%', 'PFT %', 'ROI%',
            'lmp_price', 'SPRICE', '_accept', 'SGPFT', 'SPFT', 'SROI'
        ];
        var kwAdsOnlyColumns = [
            'kw_hasCampaign', 'NRL', 'NR', 'l7_views', 'kw_cvr',
            'kw_campaignBudgetAmount', 'kw_sbgt', 'kw_acos', 'kw_clicks',
            'kw_spend_L30', 'kw_ad_sold', 'kw_l7_spend', 'kw_l1_spend', 'kw_l7_cpc', 'kw_l1_cpc',
            'kw_last_sbid', 'kw_sbid_calc', 'kw_sbid_m', 'kw_apr_bid', 'kw_campaignStatus'
        ];
        var pmtAdsOnlyColumns = [
            'pmt_cbid', 'pmt_es_bid', 'pmt_s_bid', 'pmt_t_views', 'pmt_l7_views',
            'pmt_scvr', 'pmt_clicks_l7', 'pmt_clicks_l30',
            'pmt_pft', 'pmt_roi', 'pmt_tpft', 'pmt_troi', 'pmt_nrl'
        ];
        var sharedColumns = [
            '_select', 'INV', 'L30', 'E Dil%', 'eBay Price', 'eBay L60', 'eBay L45', 'eBay L30', 'growth_percent', 'views'
        ];

        function applySectionColumnVisibility(sectionVal) {
            if (sectionVal === 'all' || sectionVal === 'pricing') {
                kwAdsOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                pmtAdsOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                pricingOnlyColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
                sharedColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
            } else if (sectionVal === 'kw_ads') {
                pricingOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                pmtAdsOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                kwAdsOnlyColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
                sharedColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
            } else if (sectionVal === 'pmt_ads') {
                pricingOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                kwAdsOnlyColumns.forEach(function(col) { try { table.hideColumn(col); } catch(e) {} });
                pmtAdsOnlyColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
                sharedColumns.forEach(function(col) { try { table.showColumn(col); } catch(e) {} });
            }
            table.redraw(true);
        }

        // Store previous view mode before PMT Ads switches it
        var prevViewModeBeforePmt = null;
        var prevViewModeBeforeKw = null;

        $('#section-filter').on('change', function() {
            var sectionVal = $(this).val();
            applySectionColumnVisibility(sectionVal);

            if ((decreaseModeActive || increaseModeActive || samePriceModeActive) && table && table.getColumn) {
                try {
                    var selectCol = table.getColumn('_select');
                    if (selectCol) selectCol.show();
                } catch (e) {}
            }

            if (sectionVal === 'all' || sectionVal === 'pricing') {
                // Restore view mode if returning from PMT Ads or KW Ads
                if (prevViewModeBeforePmt !== null) {
                    $('#view-mode-filter').val(prevViewModeBeforePmt);
                    prevViewModeBeforePmt = null;
                } else if (prevViewModeBeforeKw !== null) {
                    $('#view-mode-filter').val(prevViewModeBeforeKw);
                    prevViewModeBeforeKw = null;
                }
                // Hide KW Ads stats & filters, show pricing filters & Summary stats
                $('#kw-ads-stats').hide();
                $('#kw-ads-range-section').hide();
                $('.kw-ads-filter-item').hide();
                $('#pmt-ads-filter-section').hide();
                $('.pmt-ads-filter-item').hide();
                $('#dil-filter-wrapper').show();
                $('.pricing-filter-item').css('display', '');
                $('#summary-stats').show();
                $('#view-mode-filter').show();
                applyFilters();
            } else if (sectionVal === 'kw_ads') {
                // Restore view mode if returning from PMT Ads
                if (prevViewModeBeforePmt !== null) {
                    $('#view-mode-filter').val(prevViewModeBeforePmt);
                    prevViewModeBeforePmt = null;
                }
                // Save current view mode and force Parent Only (like ebay-3/utilized page)
                if (prevViewModeBeforeKw === null) {
                    prevViewModeBeforeKw = $('#view-mode-filter').val();
                }
                $('#view-mode-filter').val('parent');
                $('#view-mode-filter').hide();
                // Show KW Ads filters, hide pricing filters & Summary stats
                $('#kw-ads-stats').show();
                $('#kw-ads-range-section').show();
                $('.kw-ads-filter-item').css('display', 'inline-block');
                $('#pmt-ads-filter-section').hide();
                $('.pmt-ads-filter-item').hide();
                $('#dil-filter-wrapper').show();
                $('.pricing-filter-item').hide();
                $('#summary-stats').hide();
                applyFilters();
                updateKwAdsStats();
            } else if (sectionVal === 'pmt_ads') {
                // Restore view mode if returning from KW Ads
                if (prevViewModeBeforeKw !== null) {
                    prevViewModeBeforeKw = null;
                }
                // Save current view mode and force Parent Only (like ebay-3/pmt/ads page)
                if (prevViewModeBeforePmt === null) {
                    prevViewModeBeforePmt = $('#view-mode-filter').val();
                }
                $('#view-mode-filter').val('parent');
                $('#view-mode-filter').hide();
                // Hide KW Ads stats & filters, show PMT filters
                $('#kw-ads-stats').hide();
                $('#kw-ads-range-section').hide();
                $('.kw-ads-filter-item').hide();
                $('.pricing-filter-item').hide();
                $('#summary-stats').hide();
                $('#dil-filter-wrapper').hide();
                $('.pmt-ads-filter-item').css('display', 'inline-block');
                $('#pmt-ads-filter-section').show();
                applyFilters();
            }
        });

        // Flatten one tree branch for export (parent row then all _children), without mutating source objects
        function ebay3FlattenTreeBranchForExport(rowData, out) {
            if (!rowData || typeof rowData !== 'object') {
                return;
            }
            var kids = rowData._children;
            var rowCopy = {};
            Object.keys(rowData).forEach(function(k) {
                if (k !== '_children') {
                    rowCopy[k] = rowData[k];
                }
            });
            out.push(rowCopy);
            if (Array.isArray(kids) && kids.length) {
                kids.forEach(function(child) {
                    ebay3FlattenTreeBranchForExport(child, out);
                });
            }
        }

        function ebay3CsvEscapeCell(val) {
            if (val === null || val === undefined) {
                return '';
            }
            if (typeof val === 'object') {
                try {
                    val = JSON.stringify(val);
                } catch (e) {
                    val = String(val);
                }
            } else {
                val = String(val);
            }
            if (/[",\r\n]/.test(val)) {
                return '"' + val.replace(/"/g, '""') + '"';
            }
            return val;
        }

        function ebay3VisibleExportColumns() {
            var cols = [];
            table.getColumns().forEach(function(col) {
                try {
                    if (!col.isVisible()) {
                        return;
                    }
                    var def = col.getDefinition();
                    if (def.download === false) {
                        return;
                    }
                    var f = def.field;
                    if (f === undefined || f === null || f === '' || f === '_select') {
                        return;
                    }
                    var t = def.title !== undefined && def.title !== null ? String(def.title) : String(f);
                    cols.push({ field: f, title: t });
                } catch (e) {}
            });
            return cols;
        }

        function ebay3DownloadManualCsv(filename, rows, cols) {
            var lines = [];
            lines.push(cols.map(function(c) {
                return ebay3CsvEscapeCell(c.title);
            }).join(','));
            rows.forEach(function(row) {
                lines.push(cols.map(function(c) {
                    return ebay3CsvEscapeCell(row[c.field]);
                }).join(','));
            });
            var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(a.href);
        }

        // Export button: download CSV for current section (visible columns + filtered data)
        $('#export-section-btn').on('click', function() {
            var sectionVal = $('#section-filter').val() || 'all';
            var dateStr = new Date().toISOString().slice(0, 10);
            var filename = 'ebay3_' + sectionVal + '_export_' + dateStr + '.csv';
            try {
                var viewMode = $('#view-mode-filter').val();

                // "Both (Parent + SKU)": Tabulator CSV only outputs displayed rows (collapsed tree = no SKUs).
                // Also, parent rows use summed INV/metrics and pass filters while many child SKUs fail the same
                // row-level filters — so filtering every descendant drops all SKU lines. Export from row data:
                // one CSV row per parent, then all nested _children, for each table.getRows("active") root.
                if (viewMode === 'both' && table) {
                    var flatRows = [];
                    table.getRows('active').forEach(function(rc) {
                        ebay3FlattenTreeBranchForExport(rc.getData(), flatRows);
                    });
                    var exportCols = ebay3VisibleExportColumns();
                    if (!exportCols.length) {
                        throw new Error('No visible columns to export');
                    }
                    ebay3DownloadManualCsv(filename, flatRows, exportCols);
                } else {
                    table.download('csv', filename, { downloadRowRange: 'active' });
                }

                if (typeof showToast === 'function') {
                    showToast('success', 'Export started');
                }
            } catch (e) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Export failed: ' + (e.message || e));
                } else {
                    alert('Export failed: ' + (e.message || e));
                }
            }
        });

        // KW Ads filter change handlers
        $('#kw-utilization-filter, #kw-status-filter, #kw-nra-filter, #kw-nrl-filter, #kw-sbidm-filter').on('change', function() {
            applyFilters();
        });

        // KW Ads Range filter event listeners
        $('#kw-range-1ub-min, #kw-range-1ub-max, #kw-range-7ub-min, #kw-range-7ub-max, #kw-range-lbid-min, #kw-range-lbid-max, #kw-range-acos-min, #kw-range-acos-max, #kw-range-views-min, #kw-range-views-max, #kw-range-l7views-min, #kw-range-l7views-max').on('keyup change', function() {
            applyFilters();
        });

        // INC/DEC SBID dropdown
        $('#kw-inc-dec-dropdown .dropdown-item').on('click', function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            if (type === 'value') {
                $('#kw-inc-dec-btn').html('<i class="fa-solid fa-plus-minus me-1"></i>INC/DEC (By Value)');
                $('#kw-inc-dec-label').text('Value/Percentage');
                $('#kw-inc-dec-input').attr('placeholder', 'Enter value (e.g., +0.5 or -0.5)');
            } else {
                $('#kw-inc-dec-btn').html('<i class="fa-solid fa-plus-minus me-1"></i>INC/DEC (By %)');
                $('#kw-inc-dec-label').text('Percentage');
                $('#kw-inc-dec-input').attr('placeholder', 'Enter % (e.g., +10 or -10)');
            }
        });

        // Apply INC/DEC SBID
        $('#kw-apply-inc-dec-btn').on('click', function() {
            var inputVal = parseFloat($('#kw-inc-dec-input').val());
            if (isNaN(inputVal) || inputVal === 0) {
                alert('Please enter a valid value');
                return;
            }
            var isPercentage = $('#kw-inc-dec-btn').text().includes('By %');
            var selectedRows = table.getSelectedRows();
            if (selectedRows.length === 0) {
                // Apply to all visible rows
                selectedRows = table.getRows("active");
            }
            selectedRows.forEach(function(row) {
                var rowData = row.getData();
                var currentSbidM = parseFloat(rowData.kw_sbid_m) || 0;
                // Get SBID calc value if no manual value
                if (currentSbidM === 0) {
                    var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                    var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                    currentSbidM = l1Cpc > 0 ? l1Cpc : (l7Cpc > 0 ? l7Cpc : 0);
                }
                if (currentSbidM > 0) {
                    var newVal;
                    if (isPercentage) {
                        newVal = currentSbidM * (1 + inputVal / 100);
                    } else {
                        newVal = currentSbidM + inputVal;
                    }
                    newVal = Math.max(0.01, Math.round(newVal * 100) / 100);
                    row.update({kw_sbid_m: newVal.toFixed(2)});
                }
            });
        });

        // Clear INC/DEC input
        $('#kw-clear-inc-dec-btn').on('click', function() {
            $('#kw-inc-dec-input').val('');
        });

        // Clear SBID M for selected rows
        $('#kw-clear-sbid-m-btn').on('click', function() {
            var selectedRows = table.getSelectedRows();
            if (selectedRows.length === 0) {
                alert('Please select rows first');
                return;
            }
            if (confirm('Clear SBID M for ' + selectedRows.length + ' selected rows?')) {
                selectedRows.forEach(function(row) {
                    row.update({kw_sbid_m: ''});
                });
            }
        });

        // KW NRL dropdown change handler
        $(document).on('change', '.kw-nrl-dropdown', function() {
            var $select = $(this);
            var value = $select.val();
            var sku = $select.data('sku');
            $.ajax({
                url: '/update-ebay3-nr-data',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', sku: sku, field: 'NRL', value: value },
                success: function(response) {
                    if (response.success) {
                        showToast('NRL updated for ' + sku, 'success');
                        updateKwAdsStats();
                    } else {
                        showToast('Error updating NRL', 'error');
                    }
                },
                error: function() { showToast('Error updating NRL', 'error'); }
            });
        });

        // KW NRA dropdown change handler
        $(document).on('change', '.kw-nra-dropdown', function() {
            var $select = $(this);
            var value = $select.val();
            var sku = $select.data('sku');
            $.ajax({
                url: '/update-ebay3-nr-data',
                method: 'POST',
                data: { _token: '{{ csrf_token() }}', sku: sku, field: 'NR', value: value },
                success: function(response) {
                    if (response.success) {
                        showToast('NRA updated for ' + sku, 'success');
                        updateKwAdsStats();
                    } else {
                        showToast('Error updating NRA', 'error');
                    }
                },
                error: function() { showToast('Error updating NRA', 'error'); }
            });
        });

        // KW Bulk Actions
        $(document).on('click', '.kw-bulk-action-item', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            var selectedRows = table.getSelectedRows();
            if (selectedRows.length === 0) {
                alert('Please select rows first');
                return;
            }
            var skus = selectedRows.map(function(row) { return row.getData()['(Child) sku']; });
            if (action === 'NRA' || action === 'RA' || action === 'LATER') {
                if (confirm('Mark ' + selectedRows.length + ' SKUs as ' + action + '?')) {
                    skus.forEach(function(sku) {
                        $.ajax({
                            url: '/update-ebay3-nr-data',
                            method: 'POST',
                            data: { _token: '{{ csrf_token() }}', sku: sku, field: 'NR', value: action },
                            success: function() {},
                            error: function() {}
                        });
                    });
                    showToast(selectedRows.length + ' SKUs marked as ' + action, 'success');
                    setTimeout(function() { table.replaceData(); }, 1000);
                }
            }
        });

        // ======== PMT Ads Event Handlers ========
        // PMT column dropdown filter click handler
        $(document).on('click', '.pmt-column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $item = $(this);
            var column = $item.data('column');
            var color = $item.data('color');
            var dropdown = $item.closest('.manual-dropdown-container');
            var button = dropdown.find('> button');

            // Update active state
            dropdown.find('.pmt-column-filter').removeClass('active');
            $item.addClass('active');

            // Update button to show selected color dot + label
            var statusCircle = $item.find('.status-circle').clone();
            var origLabel = dropdown.find('> button').attr('data-label') || button.text().trim();
            if (!dropdown.find('> button').attr('data-label')) {
                dropdown.find('> button').attr('data-label', button.text().trim());
            }
            button.html('').append(statusCircle).append(' ' + origLabel);

            // Update filter state
            pmtDropdownFilters[column] = color;
            dropdown.removeClass('show');
            dropdown.find('.dropdown-menu').removeClass('show');
            applyFilters();
        });

        // PMT Ads range filter Apply button
        $('#pmt-apply-range-btn').on('click', function() {
            var v;
            v = $('#pmt-t-views-min').val(); pmtRangeFilters['t_views'].min = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-t-views-max').val(); pmtRangeFilters['t_views'].max = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-l7-views-min').val(); pmtRangeFilters['l7_views'].min = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-l7-views-max').val(); pmtRangeFilters['l7_views'].max = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-cbid-min').val(); pmtRangeFilters['cbid'].min = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-cbid-max').val(); pmtRangeFilters['cbid'].max = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-scvr-min').val(); pmtRangeFilters['scvr'].min = v !== '' ? parseFloat(v) : null;
            v = $('#pmt-scvr-max').val(); pmtRangeFilters['scvr'].max = v !== '' ? parseFloat(v) : null;
            applyFilters();
        });

        // PMT Ads range filter Clear button
        $('#pmt-clear-range-btn').on('click', function() {
            $('#pmt-t-views-min, #pmt-t-views-max, #pmt-l7-views-min, #pmt-l7-views-max, #pmt-cbid-min, #pmt-cbid-max, #pmt-scvr-min, #pmt-scvr-max').val('');
            pmtRangeFilters = {
                't_views': { min: null, max: null },
                'l7_views': { min: null, max: null },
                'cbid': { min: null, max: null },
                'scvr': { min: null, max: null }
            };
            // Reset dropdown filters too
            for (var k in pmtDropdownFilters) {
                pmtDropdownFilters[k] = 'all';
            }
            // Reset dropdown button appearances
            $('.pmt-ads-filter-item.manual-dropdown-container').each(function() {
                var btn = $(this).find('> button');
                var origLabel = btn.attr('data-label') || btn.text().trim();
                btn.html('<span class="status-circle default"></span> ' + origLabel);
                $(this).find('.pmt-column-filter').removeClass('active');
            });
            applyFilters();
        });

        // PMT NRL dropdown change handler
        $(document).on('change', '.pmt-nrl-dropdown', function() {
            var $select = $(this);
            var sku = $select.data('sku');
            var value = $select.val();
            var bgColor = value === 'NRL' ? '#dc3545' : '#28a745';
            $select.css({ 'background-color': bgColor, 'color': '#fff' });

            $.ajax({
                url: '/update-ebay3-nr-data',
                method: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: JSON.stringify({ sku: sku, field: 'NRL', value: value }),
                success: function(response) {
                    if (response.success) {
                        showToast('NRL updated', 'success');
                        var rows = table.searchRows('(Child) sku', '=', sku);
                        if (rows.length > 0) {
                            rows[0].update({ NRL: value });
                        }
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save NRL', 'error');
                }
            });
        });

        // DIL% (pricing): parent `.show` + custom CSS (see `.manual-dropdown-container.pricing-filter-item`)
        $(document).on('click', '.manual-dropdown-container.pricing-filter-item > .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            $('.manual-dropdown-container.pricing-filter-item').not(container).removeClass('show');
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter[data-column="dil_percent"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $item = $(this);
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn').first();
            container.find('.column-filter[data-column="dil_percent"]').removeClass('active');
            $item.addClass('active');
            const statusCircle = $item.find('.status-circle').clone();
            button.html('').append(statusCircle).append(' DIL%');
            container.removeClass('show');
            applyFilters();
        });

        // PMT Ads: Bootstrap-style `.dropdown-menu.show` (containers are not `.pricing-filter-item`)
        $(document).on('click', '.pmt-ads-filter-item.manual-dropdown-container > .dropdown-toggle', function(e) {
            e.stopPropagation();
            var $menu = $(this).siblings('.dropdown-menu');
            $('.pmt-ads-filter-item .dropdown-menu').not($menu).removeClass('show');
            $menu.toggleClass('show');
        });

        $(document).on('click', function() {
            $('.manual-dropdown-container.pricing-filter-item').removeClass('show');
            $('.pmt-ads-filter-item .dropdown-menu').removeClass('show');
        });

        // Update KW Ads Statistics
        function updateKwAdsStats() {
            if (typeof table === 'undefined' || !table) return;
            
            var allData = table.getData('all');
            var totalSkuCount = 0;
            var ebaySkuCount = 0;
            var campaignCount = 0;
            var missingCount = 0;
            var nraMissingCount = 0;
            var nrlMissingCount = 0;
            var zeroInvCount = 0;
            var nraCount = 0;
            var nrlCount = 0;
            var raCount = 0;
            var ub7Count = 0;
            var ub7Ub1Count = 0;
            var pausedCount = 0;
            var totalClicks = 0;
            var totalSpend = 0;
            var totalAdSold = 0;
            var totalAcos = 0;
            var acosItems = 0;
            var totalCvr = 0;
            var cvrItems = 0;

            var comboCounts = {
                'green-green': 0, 'green-pink': 0, 'green-red': 0,
                'pink-green': 0, 'pink-pink': 0, 'pink-red': 0,
                'red-green': 0, 'red-pink': 0, 'red-red': 0
            };
            var processedSkusForCombo = new Set();
            var processedSkusTotal = new Set();
            var processedSkusEbay = new Set();
            var processedSkusForNra = new Set();
            var processedSkusForNrl = new Set();
            var processedSkusForCampaign = new Set();
            var processedSkusForMissing = new Set();
            var processedSkusForNraMissing = new Set();
            var processedSkusForNrlMissing = new Set();
            var processedSkusForZeroInv = new Set();

            var invFilterVal = $('#inv-filter').val() || 'more';
            var ebayStockFilterVal = $('#ebay-stock-filter').val() || 'more';
            var el30FilterVal = $('#el30-filter').val() || 'all';
            var growthSignKw = $('#growth-sign-filter').val() || 'all';

            allData.forEach(function(row) {
                var sku = row['(Child) sku'] || '';
                if (!sku) return;

                var estock = parseFloat(row['eBay Stock'] || row['E Stock'] || 0) || 0;
                var ebayL30ForFilter = parseFloat(row['eBay L30'] || 0) || 0;
                var ebayPrice = parseFloat(row['eBay Price'] || 0);
                var ebayItemId = row.eBay_item_id || '';
                var hasCampaign = row.kw_campaign_id && row.kw_campaign_id !== '';
                var nraValue = row.NR ? row.NR.trim() : '';
                var nrlValue = row.NRL ? row.NRL.trim() : '';

                // Total SKU count (unique)
                if (!processedSkusTotal.has(sku)) {
                    processedSkusTotal.add(sku);
                    totalSkuCount++;
                }

                // eBay SKU count
                if (!processedSkusEbay.has(sku)) {
                    processedSkusEbay.add(sku);
                    if (ebayItemId || ebayPrice > 0) ebaySkuCount++;
                }

                // Zero E Stock count
                if (estock <= 0 && !processedSkusForZeroInv.has(sku)) {
                    processedSkusForZeroInv.add(sku);
                    zeroInvCount++;
                }

                var shopifyInv = typeof ebay3Qty === 'function' ? ebay3Qty(row['INV']) : (parseFloat(row['INV'] || 0) || 0);
                if (invFilterVal === 'zero') { if (shopifyInv !== 0) return; }
                else if (invFilterVal === 'more') { if (shopifyInv <= 0) return; }

                if (ebayStockFilterVal === 'zero') { if (estock > 0) return; }
                else if (ebayStockFilterVal === 'more') { if (estock <= 0) return; }

                if (el30FilterVal === 'zero') {
                    if (ebayL30ForFilter !== 0) return;
                } else if (el30FilterVal === 'more') {
                    if (ebayL30ForFilter <= 0) return;
                }

                var l60g = parseFloat(row['eBay L60']) || 0;
                var growthRawKw = 0;
                if (l60g > 0) {
                    growthRawKw = ((ebayL30ForFilter - l60g) / l60g) * 100;
                } else if (ebayL30ForFilter > 0) {
                    growthRawKw = 100;
                }
                var gRoundKw = Math.round(growthRawKw);
                if (growthSignKw && growthSignKw !== 'all') {
                    if (growthSignKw === 'negative' && gRoundKw >= 0) return;
                    if (growthSignKw === 'zero' && gRoundKw !== 0) return;
                    if (growthSignKw === 'positive' && gRoundKw <= 0) return;
                }

                // NRA / RA counts
                if (!processedSkusForNra.has(sku)) {
                    processedSkusForNra.add(sku);
                    if (nraValue === 'NRA') nraCount++;
                    else raCount++;
                }

                // NRL count
                if (!processedSkusForNrl.has(sku)) {
                    processedSkusForNrl.add(sku);
                    if (nrlValue === 'NRL') nrlCount++;
                }

                // Campaign / Missing counts
                if (hasCampaign) {
                    if (!processedSkusForCampaign.has(sku)) {
                        processedSkusForCampaign.add(sku);
                        campaignCount++;

                        var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                        var l7Spend = parseFloat(row.kw_l7_spend) || 0;
                        var l1Spend = parseFloat(row.kw_l1_spend) || 0;
                        var ub7 = budget > 0 ? (l7Spend / (budget * 7)) * 100 : 0;
                        var ub1 = budget > 0 ? (l1Spend / budget) * 100 : 0;

                        if (ub7 >= 66 && ub7 <= 99) ub7Count++;
                        if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) ub7Ub1Count++;

                        if (!processedSkusForCombo.has(sku) && budget > 0) {
                            processedSkusForCombo.add(sku);
                            var ub7Color = ub7 >= 66 && ub7 <= 99 ? 'green' : (ub7 > 99 ? 'pink' : 'red');
                            var ub1Color = ub1 >= 66 && ub1 <= 99 ? 'green' : (ub1 > 99 ? 'pink' : 'red');
                            var combo = ub7Color + '-' + ub1Color;
                            if (comboCounts.hasOwnProperty(combo)) comboCounts[combo]++;
                        }

                        var status = row.kw_campaignStatus || '';
                        if (status === 'PAUSED') pausedCount++;

                        totalClicks += parseInt(row.kw_clicks || 0);
                        totalSpend += parseFloat(row.kw_spend_L30 || 0);
                        totalAdSold += parseInt(row.kw_ad_sold || 0);

                        var kwSpend = parseFloat(row.kw_spend_L30 || 0);
                        if (kwSpend > 0) { totalAcos += parseFloat(row.kw_acos || 0); acosItems++; }
                        var clicks = parseInt(row.kw_clicks || 0);
                        if (clicks > 0) { totalCvr += parseFloat(row.kw_cvr || 0); cvrItems++; }
                    }
                } else {
                    if (!processedSkusForMissing.has(sku)) {
                        processedSkusForMissing.add(sku);
                        if (nrlValue !== 'NRL' && nraValue !== 'NRA') {
                            missingCount++;
                        } else {
                            if (nrlValue === 'NRL' && !processedSkusForNrlMissing.has(sku)) {
                                processedSkusForNrlMissing.add(sku);
                                nrlMissingCount++;
                            }
                            if (nraValue === 'NRA' && !processedSkusForNraMissing.has(sku)) {
                                processedSkusForNraMissing.add(sku);
                                nraMissingCount++;
                            } else if (nrlValue === 'NRL' && !processedSkusForNraMissing.has(sku)) {
                                processedSkusForNraMissing.add(sku);
                                nraMissingCount++;
                            }
                        }
                    }
                }
            });

            // Update DOM
            $('#kw-total-sku-count').text(totalSkuCount.toLocaleString());
            $('#kw-ebay-sku-count').text(ebaySkuCount.toLocaleString());
            $('#kw-campaign-count').text(campaignCount.toLocaleString());
            $('#kw-missing-count').text(missingCount.toLocaleString());
            $('#kw-nra-missing-count').text(nraMissingCount.toLocaleString());
            $('#kw-nrl-missing-count').text(nrlMissingCount.toLocaleString());
            $('#kw-zero-inv-count').text(zeroInvCount.toLocaleString());
            $('#kw-nra-count').text(nraCount.toLocaleString());
            $('#kw-nrl-count').text(nrlCount.toLocaleString());
            $('#kw-ra-count').text(raCount.toLocaleString());
            $('#kw-7ub-count').text(ub7Count.toLocaleString());
            $('#kw-7ub-1ub-count').text(ub7Ub1Count.toLocaleString());
            $('#kw-l30-clicks').text(totalClicks.toLocaleString());
            $('#kw-l30-spend').text(Math.round(totalSpend).toLocaleString());
            $('#kw-l30-ad-sold').text(totalAdSold.toLocaleString());
            $('#kw-avg-acos').text(acosItems > 0 ? (totalAcos / acosItems).toFixed(2) + '%' : '0%');
            $('#kw-avg-cvr').text(cvrItems > 0 ? Math.round(totalCvr / cvrItems) + '%' : '0%');
            $('#kw-paused-count').text(pausedCount.toLocaleString());

            // Update utilization filter dropdown with counts
            var comboLabels = {
                'green-green': 'Green + Green', 'green-pink': 'Green + Pink', 'green-red': 'Green + Red',
                'pink-green': 'Pink + Green', 'pink-pink': 'Pink + Pink', 'pink-red': 'Pink + Red',
                'red-green': 'Red + Green', 'red-pink': 'Red + Pink', 'red-red': 'Red + Red'
            };
            $('#kw-utilization-filter option').each(function() {
                var val = $(this).val();
                if (val !== 'all' && comboLabels[val] !== undefined) {
                    $(this).text(comboLabels[val] + ' (' + comboCounts[val] + ')');
                }
            });
        }

        // Update calc values
        function updateCalcValues() {
            const data = table.getData("active");
            let totalSales = 0;
            let totalProfit = 0;
            let sumLp = 0;
            
            data.forEach(row => {
                const profit = parseFloat(row['Total_pft']) || 0;
                const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                if (profit > 0 && salesL30 > 0) {
                    totalProfit += profit;
                    totalSales += salesL30;
                }
                sumLp += parseFloat(row['LP_productmaster']) || 0;
            });
        }

        // Update summary badges — same metrics/order as Ebay 2 (E Stock gate uses eBay Stock with E Stock fallback)
        function updateSummary() {
            const data = table.getData('active');

            let totalKwSpendL30 = 0;
            let totalPmtSpendL30 = 0;
            let totalPftAmt = 0;
            let totalSalesAmt = 0;
            let totalLpAmt = 0;
            let totalFbaInv = 0;
            let zeroSoldCount = 0;
            let moreSoldCount = 0;
            let missingCount = 0;
            let mapCount = 0;
            let invStockCount = 0;

            data.forEach(row => {
                const estock = parseFloat(row['eBay Stock'] || row['E Stock'] || 0) || 0;
                const ebayL30 = parseFloat(row['eBay L30'] || 0);

                if (estock > 0) {
                    totalPftAmt += parseFloat(row['Total_pft'] || 0);
                    totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                    totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                    totalFbaInv += estock;
                    totalKwSpendL30 += parseFloat(row['kw_spend_L30'] || 0);
                    totalPmtSpendL30 += parseFloat(row['pmt_spend_L30'] || 0);
                    if (ebayL30 === 0) {
                        zeroSoldCount++;
                    } else {
                        moreSoldCount++;
                    }
                }

                const itemId = row['eBay_item_id'];
                if (!itemId || itemId === null || itemId === '') {
                    missingCount++;
                }

                const ebayStock = parseFloat(row['eBay Stock']) || 0;
                const inv = parseFloat(row['INV']) || 0;
                if (inv > 0 && ebayStock > 0 && inv === ebayStock) {
                    mapCount++;
                }
                if (inv > 0 && ebayStock > 0 && inv !== ebayStock) {
                    invStockCount++;
                }
            });

            let totalWeightedPrice = 0;
            let totalL30 = 0;
            let totalViews = 0;
            data.forEach(row => {
                if (parseFloat(row['eBay Stock'] || row['E Stock'] || 0) > 0) {
                    const price = parseFloat(row['eBay Price'] || 0);
                    const l30 = parseFloat(row['eBay L30'] || 0);
                    totalWeightedPrice += price * l30;
                    totalL30 += l30;
                    totalViews += parseFloat(row.views || 0);
                }
            });
            const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
            const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;

            const totalAdSpendL30 = totalKwSpendL30 + totalPmtSpendL30;
            const tacosPercent = EBAY3_CHANNEL_ADS_PCT;
            const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;
            const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
            const npftPercent = avgGpft - tacosPercent;
            const nroiPercent = groiPercent - tacosPercent;

            $('#zero-sold-count').text(zeroSoldCount.toLocaleString());
            $('#more-sold-count').text(moreSoldCount.toLocaleString());
            $('#total-pft-amt-badge').text('Total PFT: $' + Math.round(totalPftAmt).toLocaleString());
            $('#total-sales-amt-badge').text('Sales: $' + Math.round(totalSalesAmt).toLocaleString());
            $('#total-spend-l30-badge').text('Ad Spend (KW+PMT): $' + Math.round(totalAdSpendL30).toLocaleString());
            $('#avg-gpft-badge').text('GPFT: ' + Math.round(avgGpft) + '%');
            $('#avg-pft-badge').text('NPFT: ' + Math.round(npftPercent) + '%');
            $('#groi-percent-badge').text('GROI: ' + Math.round(groiPercent) + '%');
            $('#nroi-percent-badge').text('NROI: ' + Math.round(nroiPercent) + '%');
            $('#tacos-percent-badge').text('TACOS: ' + tacosPercent.toFixed(1) + '%');
            $('#avg-price-badge').text('Price: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('CVR: ' + Math.round(avgCVR) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#total-inv-badge').text('E Stock: ' + Math.round(totalFbaInv).toLocaleString());

            $('#missing-count-badge').text('Missing: ' + missingCount);
            $('#map-count-badge').text('Map: ' + mapCount);
            $('#inv-stock-badge').text('N Map: ' + invStockCount);
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            if (!menu) return;
            menu.innerHTML = '';

            fetch('/ebay3-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
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
                });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

            fetch('/ebay3-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/ebay3-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) {
                            col.hide();
                        }
                    });
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            // Apply initial section filter column visibility (hide KW Ads columns on load)
            applySectionColumnVisibility($('#section-filter').val() || 'all');
            syncEbay3PriceModeUi();
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
            applyFilters();
            
            // Set up periodic background retry check (every 30 seconds)
            setInterval(() => {
                backgroundRetryFailedSkus();
            }, 30000);
        });

        table.on('dataLoaded', function() {
            updateCalcValues();
            updateSummary();
            requestAnimationFrame(function() {
                reorderEbay3ParentRowsBelowSkus();
            });
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('dataTreeRowExpanded', function() {
            requestAnimationFrame(function() {
                reorderEbay3ParentRowsBelowSkus();
            });
        });

        table.on('renderComplete', function() {
            reorderEbay3ParentRowsBelowSkus();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        // Row checkbox: add/remove SKU from selectedSkus
        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).attr('data-sku') || $(this).data('sku');
            if (!sku) return;
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Select-all checkbox: add/remove all filtered (non-parent) SKUs
        $(document).on('change', '#select-all-checkbox', function() {
            const checked = $(this).prop('checked');
            const filteredData = table.getData('active').filter(function(row) {
                return !(row.Parent && row.Parent.startsWith('PARENT'));
            });
            const filteredSkus = filteredData.map(function(row) { return row['(Child) sku']; }).filter(Boolean);
            if (checked) {
                filteredSkus.forEach(function(sku) { selectedSkus.add(sku); });
            } else {
                filteredSkus.forEach(function(sku) { selectedSkus.delete(sku); });
            }
            table.getRows().forEach(function(row) {
                row.reformat();
            });
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Toggle column from dropdown
        (function() {
            var colMenu = document.getElementById("column-dropdown-menu");
            if (colMenu) {
                colMenu.addEventListener("change", function(e) {
                    if (e.target.type === 'checkbox') {
                        const field = e.target.value;
                        const col = table.getColumn(field);
                        if (e.target.checked) {
                            col.show();
                        } else {
                            col.hide();
                        }
                        saveColumnVisibilityToServer();
                    }
                });
            }
            var showAllBtn = document.getElementById("show-all-columns-btn");
            if (showAllBtn) {
                showAllBtn.addEventListener("click", function() {
                    table.getColumns().forEach(col => {
                        col.show();
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
                });
            }
        })();

        // Toggle SPEND L30 breakdown columns
        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-spendL30-btn")) {
                let colsToToggle = ["kw_spend_L30", "kw_percent", "pmt_spend_L30", "pmt_percent"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
                
                saveColumnVisibilityToServer();
                buildColumnDropdown();
            }

            // Copy SKU to clipboard
            if (e.target.classList.contains("copy-sku-btn")) {
                const sku = e.target.getAttribute("data-sku");
                
                navigator.clipboard.writeText(sku).then(function() {
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                }).catch(function(err) {
                    const textarea = document.createElement('textarea');
                    textarea.value = sku;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                });
            }
        });
        
        // LMP Modal function
        window.showLmpModal = function(lmpEntries) {
            let modalHtml = `
                <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="lmpModalLabel">Lowest Marketplace Prices</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Price</th>
                                            <th>Title</th>
                                            <th>Seller</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;
            
            lmpEntries.forEach(function(entry) {
                const price = entry.price ? '$' + parseFloat(entry.price).toFixed(2) : '-';
                const title = entry.title || '-';
                const seller = entry.seller || '-';
                const link = entry.link || '#';
                
                modalHtml += `
                    <tr>
                        <td><strong>${price}</strong></td>
                        <td>${title}</td>
                        <td>${seller}</td>
                        <td><a href="${link}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> View</a></td>
                    </tr>
                `;
            });
            
            modalHtml += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // This function is deprecated - using new enhanced LMP modal
        };
        
        // Global variable to store current LMP data
        let currentLmpData = {
            sku: null,
            competitors: [],
            lowestPrice: null
        };

        // Load Competitors Modal Function
        function loadEbayCompetitorsModal(sku) {
            $('#lmpSku').text(sku);
            
            // Pre-fill form with SKU
            $('#addCompSku').val(sku);
            $('#addCompItemId').val('');
            $('#addCompPrice').val('');
            $('#addCompShipping').val('');
            $('#addCompLink').val('');
            $('#addCompTitle').val('');
            
            $('#lmpModal').modal('show');
            
            // Show loading state
            $('#lmpDataList').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading competitors...</p>
                </div>
            `);
            
            // Fetch LMP data
            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: { sku: sku },
                success: function(response) {
                    if (response.success && response.competitors && response.competitors.length > 0) {
                        currentLmpData.sku = sku;
                        currentLmpData.competitors = response.competitors;
                        currentLmpData.lowestPrice = response.lowest_price;
                        
                        renderEbayCompetitorsList(response.competitors, response.lowest_price);
                    } else {
                        $('#lmpDataList').html(`
                            <div class="alert alert-warning">
                                <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                            </div>
                        `);
                    }
                },
                error: function(xhr) {
                    console.error('Error loading competitors:', xhr);
                    $('#lmpDataList').html(`
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                        </div>
                    `);
                }
            });
        }

        // Render Competitors List Function
        function renderEbayCompetitorsList(competitors, lowestPrice) {
            if (!competitors || competitors.length === 0) {
                $('#lmpDataList').html(`
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No competitors found for this SKU
                    </div>
                `);
                return;
            }
            
            let html = '<div class="table-responsive"><table class="table table-striped table-hover">';
            html += `
                <thead class="table-dark">
                    <tr>
                        <th>Item ID</th>
                        <th>Price</th>
                        <th>Shipping</th>
                        <th>Total</th>
                        <th>Title</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
            `;
            
            competitors.forEach(function(item) {
                const isLowest = item.total_price === lowestPrice;
                const rowClass = isLowest ? 'table-success' : '';
                const badge = isLowest ? '<span class="badge bg-success ms-2">Lowest</span>' : '';
                const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
                
                html += `
                    <tr class="${rowClass}">
                        <td><code>${item.item_id}</code></td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                        <td><strong>$${parseFloat(item.total_price).toFixed(2)}</strong> ${badge}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.title || 'N/A'}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay"><i class="fa fa-external-link"></i></a>
                                <button class="btn btn-sm btn-danger delete-ebay-lmp-btn" data-id="${item.id}" data-item-id="${item.item_id}" data-price="${item.total_price}" title="Delete this competitor"><i class="fa fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }

        // View Competitors Modal Event Listener
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            loadEbayCompetitorsModal(sku);
        });

        // Add Competitor Form Submission
        $('#addCompetitorForm').on('submit', function(e) {
            e.preventDefault();
            
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
            
            $.ajax({
                url: '/ebay-lmp-add',
                method: 'POST',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                data: {
                    sku: $('#addCompSku').val(),
                    item_id: $('#addCompItemId').val(),
                    price: $('#addCompPrice').val(),
                    shipping_cost: $('#addCompShipping').val() || 0,
                    product_link: $('#addCompLink').val(),
                    product_title: $('#addCompTitle').val()
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor added successfully', 'success');
                        $('#addCompItemId, #addCompPrice, #addCompShipping, #addCompLink, #addCompTitle').val('');
                        loadEbayCompetitorsModal($('#addCompSku').val());
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.error || 'Failed to add competitor', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // Delete Competitor Button Click
        $(document).on('click', '.delete-ebay-lmp-btn', function() {
            const $btn = $(this);
            const id = $btn.data('id');
            const itemId = $btn.data('item-id');
            const price = $btn.data('price');
            
            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) return;
            
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/ebay-lmp-delete',
                method: 'POST',
                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
                data: { id: id },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');
                        loadEbayCompetitorsModal(currentLmpData.sku);
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to delete competitor', 'error');
                    }
                },
                error: function(xhr) {
                    showToast(xhr.responseJSON?.error || 'Failed to delete competitor', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // ACOS Info Icon Click Handler – show KW/PMT campaign modal
        $(document).on('click', '.acos-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (!sku) {
                showToast('SKU not found', 'error');
                return;
            }
            $('#campaignModalLabel').text('Campaign Details - ' + sku);
            $('#campaignModalBody').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
            $('#campaignModal').modal('show');

            $.ajax({
                url: '/ebay3-campaign-data-by-sku',
                type: 'GET',
                data: { sku: sku },
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    function getAcosColorClass(acos) {
                        if (acos === 0) return '';
                        if (acos < 7) return 'pink-bg';
                        if (acos >= 7 && acos <= 14) return 'green-bg';
                        if (acos > 14) return 'red-bg';
                        return '';
                    }
                    function fmt(val, decimals) {
                        if (val == null || val === '' || (typeof val === 'number' && isNaN(val))) return '-';
                        return Number(val).toFixed(decimals || 0);
                    }
                    function fmtPct(val) {
                        if (val == null || val === '' || (typeof val === 'number' && isNaN(val))) return '-';
                        return Number(val).toFixed(0) + '%';
                    }
                    function fmtBid(val) {
                        if (val == null || val === '' || val === '0') return '-';
                        const n = parseFloat(val);
                        return (n > 0) ? n.toFixed(2) : '-';
                    }
                    function getUbColorClass(ub) {
                        if (ub == null || ub === '' || (typeof ub === 'number' && isNaN(ub))) return '';
                        const n = parseFloat(ub);
                        if (n >= 66 && n <= 99) return 'green-bg';
                        if (n > 99) return 'pink-bg';
                        return 'red-bg';
                    }

                    let html = '';

                    if (response.kw_campaigns && response.kw_campaigns.length > 0) {
                        response.kw_campaigns.forEach(function(c) {
                            const acos = parseFloat(c.acos || 0);
                            html += '<h5 class="mb-3">KW Campaign - ' + (c.campaign_name || 'N/A') + '</h5>';
                            html += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm">';
                            html += '<thead><tr><th>BGT</th><th>SBGT</th><th>ACOS</th><th>Clicks</th><th>Ad Spend</th><th>Ad Sales</th><th>Ad Sold</th>';
                            html += '<th>AD CVR</th><th>7UB%</th><th>1UB%</th><th>L7CPC</th><th>L1CPC</th><th>L BID</th><th>SBID</th></tr></thead><tbody><tr>';
                            html += '<td>' + fmt(c.bgt, 0) + '</td><td>' + fmt(c.sbgt, 0) + '</td>';
                            html += '<td class="' + getAcosColorClass(acos) + '">' + fmtPct(acos) + '</td>';
                            html += '<td>' + fmt(c.clicks) + '</td><td>' + fmt(c.ad_spend, 2) + '</td><td>' + fmt(c.ad_sales, 2) + '</td><td>' + fmt(c.ad_sold) + '</td>';
                            html += '<td>' + fmtPct(c.ad_cvr) + '</td>';
                            html += '<td class="' + getUbColorClass(c['7ub']) + '">' + (c['7ub'] != null ? fmtPct(c['7ub']) : '-') + '</td>';
                            html += '<td class="' + getUbColorClass(c['1ub']) + '">' + (c['1ub'] != null ? fmtPct(c['1ub']) : '-') + '</td>';
                            html += '<td>' + (c.l7cpc != null && !isNaN(c.l7cpc) ? fmt(c.l7cpc, 2) : '-') + '</td><td>' + (c.l1cpc != null && !isNaN(c.l1cpc) ? fmt(c.l1cpc, 2) : '-') + '</td>';
                            html += '<td>' + fmtBid(c.l_bid) + '</td><td>' + (c.sbid != null && c.sbid > 0 ? fmt(c.sbid, 2) : '-') + '</td>';
                            html += '</tr></tbody></table></div>';
                        });
                    } else {
                        html += '<h5 class="mb-3">KW Campaigns</h5><p class="text-muted">No KW campaigns found</p>';
                    }

                    function calcSbid(l7Views, esBid) {
                        const l7 = Number(l7Views || 0) || 0;
                        const es = parseFloat(esBid) || 0;
                        let v;
                        if (l7 >= 0 && l7 < 50) v = es;
                        else if (l7 >= 50 && l7 < 100) v = 9;
                        else if (l7 >= 100 && l7 < 150) v = 8;
                        else if (l7 >= 150 && l7 < 200) v = 7;
                        else if (l7 >= 200 && l7 < 250) v = 6;
                        else if (l7 >= 250 && l7 < 300) v = 5;
                        else if (l7 >= 300 && l7 < 350) v = 4;
                        else if (l7 >= 350 && l7 < 400) v = 3;
                        else if (l7 >= 400) v = 2;
                        else v = es;
                        return Math.min(v, 15);
                    }
                    // SCVR coloring – same rule as ebay/pmp/ads getCvrColor
                    function getScvrColor(scvr) {
                        if (scvr == null || scvr === '' || (typeof scvr === 'number' && isNaN(scvr))) return '#6c757d';
                        const percent = parseFloat(scvr);
                        if (percent <= 4) return 'red';
                        if (percent > 4 && percent <= 7) return 'yellow';
                        if (percent > 7 && percent <= 10) return 'green';
                        return '#E83E8C';
                    }
                    if (response.pt_campaigns && response.pt_campaigns.length > 0) {
                        response.pt_campaigns.forEach(function(c) {
                            // Use backend calculated s_bid if available, otherwise calculate in frontend
                            const sBid = (c.s_bid !== null && c.s_bid !== undefined) ? c.s_bid : calcSbid(c.l7_views, c.es_bid);
                            const scvrVal = c.scvr != null ? parseFloat(c.scvr) : null;
                            const scvrHtml = scvrVal != null && !isNaN(scvrVal)
                                ? '<span style="color:' + getScvrColor(scvrVal) + '; font-weight: 600;">' + fmt(scvrVal, 1) + '%</span>'
                                : '-';
                            html += '<h5 class="mb-3">PMT Campaign - ' + (c.campaign_name || 'N/A') + '</h5>';
                            html += '<div class="table-responsive mb-4"><table class="table table-bordered table-sm">';
                            html += '<thead><tr><th>CBID</th><th>ES BID</th><th>S BID</th><th>T VIEWS</th><th>L7 VIEWS</th><th>SCVR</th></tr></thead><tbody><tr>';
                            html += '<td>' + fmt(c.cbid, 2) + '</td><td>' + fmt(c.es_bid, 2) + '</td><td>' + fmt(sBid, 2) + '</td>';
                            html += '<td>' + fmt(c.t_views, 0) + '</td><td>' + fmt(c.l7_views, 0) + '</td><td>' + scvrHtml + '</td>';
                            html += '</tr></tbody></table></div>';
                        });
                    } else {
                        html += '<h5 class="mb-3">PMT Campaigns</h5><p class="text-muted">No PMT campaigns found</p>';
                    }

                    if (!(response.kw_campaigns && response.kw_campaigns.length > 0) && !(response.pt_campaigns && response.pt_campaigns.length > 0)) {
                        html = '<p class="text-muted">No campaigns found for this SKU</p>';
                    }
                    $('#campaignModalBody').html(html);
                },
                error: function(xhr) {
                    const err = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to load campaign data';
                    $('#campaignModalBody').html('<div class="alert alert-danger">' + err + '</div>');
                }
            });
        });
    });
</script>
@endsection

