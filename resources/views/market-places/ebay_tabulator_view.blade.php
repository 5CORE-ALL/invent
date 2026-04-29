@extends('layouts.vertical', ['title' => 'eBay Data', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        body {
            zoom: 0.9;
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

        /* Hide default pagination counter from footer */
        .tabulator-footer .tabulator-page-counter {
            display: none !important;
        }

        /* Pagination page buttons styling */
        .tabulator-footer .tabulator-paginator .tabulator-page {
            border: 1px solid #dee2e6;
            padding: 4px 10px;
            margin: 0 1px;
            border-radius: 4px;
            font-size: 13px;
            color: #333;
            background: #fff;
            cursor: pointer;
            min-width: 32px;
            text-align: center;
        }

        .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e9ecef;
        }

        .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
            font-weight: 600;
        }

        .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .tabulator-footer {
            border-top: 1px solid #dee2e6;
            padding: 6px 10px;
            background: #f8f9fa;
        }

        .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tabulator-footer .tabulator-page-size {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 4px 6px;
            font-size: 13px;
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

        /* Parent row light blue background */
        .tabulator-row.ebay-parent-row,
        .tabulator-row.ebay-parent-row .tabulator-cell {
            background-color: #b3e5fc !important;
        }

        /* Play / Pause parent navigation (same as product-master) */
        .time-navigation-group {
            margin-left: 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Status circle for DIL filter */
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
            background-color: #ffc107;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        .status-circle.blue {
            background-color: #0d6efd;
        }

        /* TACOS summary badge: white bold uppercase on brick red (matches marketplace dashboard) */
        #summary-stats #tacos-percent-badge.summary-badge-tacos {
            background-color: #b91c1c !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            border-radius: 0.75rem;
            border: none;
        }

        /* Summary badges: same layout as Ebay 2 Analytics — one row, equal flex share, scaled text */
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

        /* Image column hover preview (same pattern as forecast.analysis) */
        #image-hover-preview {
            transition: opacity 0.2s ease;
            pointer-events: auto;
            z-index: 10050;
        }

        #summary-stats .ebay2-summary-badge-row>.badge {
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

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 5px 0;
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, .175);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .manual-dropdown-container .dropdown-item.active {
            background-color: #e9ecef;
            font-weight: 600;
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'eBay Data',
        'sub_title' => 'Tabulator view — pricing, ads, and inventory',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="section-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>Section Filter</option>
                        <option value="pricing">Pricing</option>
                        <option value="kw_ads">KW Ads</option>
                        <option value="pmt_ads">PMT Ads</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All E Stock</option>
                        <option value="zero">0 E Stock</option>
                        <option value="more" selected>E Stock &gt; 0</option>
                    </select>

                    <select id="el30-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all" selected>All E L30</option>
                        <option value="zero">0 E L30</option>
                        <option value="more">E L30 &gt; 0</option>
                    </select>

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
                        <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="kwBulkActionsDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tasks"></i> Bulk Actions
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="kwBulkActionsDropdown">
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="NRA">Mark as
                                    NRA</a></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="RA">Mark as RA</a>
                            </li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="LATER">Mark as
                                    LATER</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="PAUSE">Pause
                                    Campaigns</a></li>
                            <li><a class="dropdown-item kw-bulk-action-item" href="#" data-action="ACTIVATE">Activate
                                    Campaigns</a></li>
                        </ul>
                    </div>

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

                    <div class="d-flex flex-column gap-1 pricing-filter-item" style="width: auto;">
                        <select id="gpft-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
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
                            style="width: auto; display: inline-block;">
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
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.66%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.66-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- PMT Ads Dropdown Filters (inline, hidden by default) -->
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-dilFilterDropdown">
                            <span class="status-circle default"></span> OV DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-dilFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil"
                                    data-color="all">
                                    <span class="status-circle default"></span> All OV DIL</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_ov_dil"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-eDilFilterDropdown">
                            <span class="status-circle default"></span> E Dil%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-eDilFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil"
                                    data-color="all">
                                    <span class="status-circle default"></span> All E Dil</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_e_dil"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button"
                            id="pmt-pmtClkL7FilterDropdown">
                            <span class="status-circle default"></span> PmtClkL7
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pmtClkL7FilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7"
                                    data-color="all">
                                    <span class="status-circle default"></span> All PmtClkL7</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l7"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button"
                            id="pmt-pmtClkL30FilterDropdown">
                            <span class="status-circle default"></span> PmtClkL30
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pmtClkL30FilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30"
                                    data-color="all">
                                    <span class="status-circle default"></span> All PmtClkL30</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_clk_l30"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-pftFilterDropdown">
                            <span class="status-circle default"></span> PFT%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-pftFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="all">
                                    <span class="status-circle default"></span> All PFT</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="blue">
                                    <span class="status-circle blue"></span> Blue</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_pft"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-roiFilterDropdown">
                            <span class="status-circle default"></span> ROI
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-roiFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi"
                                    data-color="all">
                                    <span class="status-circle default"></span> All ROI</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_roi"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-tacosFilterDropdown">
                            <span class="status-circle default"></span> TACOS
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-tacosFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="all">
                                    <span class="status-circle default"></span> All TACOS</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="blue">
                                    <span class="status-circle blue"></span> Blue</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_tacos"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                        </ul>
                    </div>
                    <div class="dropdown manual-dropdown-container pmt-ads-filter-item" style="display: none;">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="pmt-scvrFilterDropdown">
                            <span class="status-circle default"></span> SCVR
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="pmt-scvrFilterDropdown">
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="all">
                                    <span class="status-circle default"></span> All SCVR</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="red">
                                    <span class="status-circle red"></span> Red</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="green">
                                    <span class="status-circle green"></span> Green</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="pink">
                                    <span class="status-circle pink"></span> Pink</a></li>
                            <li><a class="dropdown-item pmt-column-filter" href="#" data-column="pmt_scvr"
                                    data-color="blue">
                                    <span class="status-circle blue"></span> Low SCVR</a></li>
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

                    <button id="ebay-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                        title="Cycle: Off → Decrease → Increase → Same Price → Off">
                        <i class="fas fa-exchange-alt"></i> Price %
                    </button>

                    <button type="button" class="btn btn-sm btn-success pricing-filter-item" data-bs-toggle="modal"
                        data-bs-target="#exportModal">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    <button type="button" class="btn btn-sm btn-primary pricing-filter-item" data-bs-toggle="modal"
                        data-bs-target="#importModal">
                        <i class="fas fa-upload"></i> Import Ratings
                    </button>

                    <a href="{{ url('/ebay-ratings-sample') }}" class="btn btn-sm btn-info pricing-filter-item">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                </div>

                <!-- KW Ads Statistics (shown only when KW Ads is selected) -->
                <div id="kw-ads-stats" class="mt-2 p-3 bg-light rounded" style="display: none;">
                    <h6 class="mb-3">KW Ads Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" style="color: white; font-weight: bold;">Total SKU: <span
                                id="kw-total-sku-count">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #8b5cf6; color: white; font-weight: bold;">Ebay SKU: <span
                                id="kw-ebay-sku-count">0</span></span>
                        <span class="badge bg-info fs-6 p-2" id="kw-campaign-card"
                            style="color: white; font-weight: bold; cursor: pointer;">Campaign: <span
                                id="kw-campaign-count">0</span></span>
                        <span class="badge bg-danger fs-6 p-2" id="kw-missing-card"
                            style="color: white; font-weight: bold; cursor: pointer;">Missing: <span
                                id="kw-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nra-missing-card"
                            style="background-color: #ffc107; color: black; font-weight: bold; cursor: pointer;">NRA
                            MISSING: <span id="kw-nra-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-zero-inv-card"
                            style="background-color: #f59e0b; color: black; font-weight: bold; cursor: pointer;">Zero E
                            Stock: <span id="kw-zero-inv-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nra-card"
                            style="background-color: #ef4444; color: white; font-weight: bold; cursor: pointer;">NRA: <span
                                id="kw-nra-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nrl-missing-card"
                            style="background-color: #ffc107; color: black; font-weight: bold; cursor: pointer;">NRL
                            MISSING: <span id="kw-nrl-missing-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-nrl-card"
                            style="background-color: #ef4444; color: white; font-weight: bold; cursor: pointer;">NRL: <span
                                id="kw-nrl-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-ra-card"
                            style="background-color: #22c55e; color: white; font-weight: bold; cursor: pointer;">RA: <span
                                id="kw-ra-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-7ub-card"
                            style="background-color: #2563eb; color: white; font-weight: bold; cursor: pointer;">7UB: <span
                                id="kw-7ub-count">0</span></span>
                        <span class="badge fs-6 p-2" id="kw-7ub-1ub-card"
                            style="background-color: #7c3aed; color: white; font-weight: bold; cursor: pointer;">7UB+1UB:
                            <span id="kw-7ub-1ub-count">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #14b8a6; color: white; font-weight: bold;">L30 CLICKS: <span
                                id="kw-l30-clicks">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #0ea5e9; color: white; font-weight: bold;">L30 SPEND: <span
                                id="kw-l30-spend">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #f59e0b; color: black; font-weight: bold;">L30 AD SOLD: <span
                                id="kw-l30-ad-sold">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #8b5cf6; color: white; font-weight: bold;">AVG ACOS: <span
                                id="kw-avg-acos">0</span></span>
                        <span class="badge fs-6 p-2"
                            style="background-color: #10b981; color: white; font-weight: bold;">AVG CVR: <span
                                id="kw-avg-cvr">0</span></span>
                        <span class="badge bg-danger fs-6 p-2" id="kw-paused-card"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to view paused campaigns">PINK DIL PAUSED: <span
                                id="kw-paused-count">0</span></span>
                    </div>
                </div>

                <!-- Summary Stats (layout matches Ebay 2 Analytics summary row) -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (INV &gt; 0)</h6>
                    <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                        <!-- Sold Filter Badges (Clickable) -->
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter 0 sold items (INV>0)">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge"
                            style="background-color: #b6e0fe; color: #0f172a; font-weight: 700; cursor: pointer;"
                            title="Click to filter items with sales (INV>0)">> 0 Sold: 0</span>

                        <!-- Financial Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge"
                            style="color: black; font-weight: bold;">Sales: $0</span>

                        <!-- Percentage Metrics -->
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge"
                            style="color: black; font-weight: bold;">GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge"
                            style="color: black; font-weight: bold;">NPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="groi-percent-badge"
                            style="color: white; font-weight: bold;">GROI: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="nroi-percent-badge"
                            style="color: black; font-weight: bold;">NROI: 0%</span>
                        <span class="badge fs-6 p-2 summary-badge-tacos" id="tacos-percent-badge">TACOS: 0.0%</span>

                        <!-- eBay Metrics -->
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge"
                            style="color: black; font-weight: bold;">Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge"
                            style="color: white; font-weight: bold;">CVR: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge"
                            style="color: black; font-weight: bold;">Views: 0</span>

                        <!-- Badge Filters -->
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge"
                            style="color: white; font-weight: bold; cursor: pointer;"
                            title="Click to filter missing SKUs (INV>0)">Missing: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="ebay-discount-type-block" class="d-flex align-items-center gap-2">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-selected-btn" class="btn btn-sm btn-danger">
                            <i class="fa fa-trash"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <!-- KW Ads Range Filters + INC/DEC SBID Section (hidden by default) -->
                <div id="kw-ads-range-section" style="display: none;">
                    <div class="row g-3 align-items-end pt-2 px-2">
                        <!-- 1UB% Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">1UB%
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-1ub-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-1ub-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- 7UB% Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">7UB%
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-7ub-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-7ub-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- LBid Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">LBid
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-lbid-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-lbid-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- Acos Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">Acos
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-acos-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-acos-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- Views Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">Views
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-views-min" class="form-control form-control-sm"
                                    placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-views-max" class="form-control form-control-sm"
                                    placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <!-- L7 Views Filter -->
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">L7
                                Views Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="kw-range-l7views-min" class="form-control form-control-sm"
                                    placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="kw-range-l7views-max" class="form-control form-control-sm"
                                    placeholder="Max" step="1" style="border-color: #e2e8f0;">
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
                                <button type="button" id="kw-inc-dec-btn" class="btn btn-warning btn-sm dropdown-toggle"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-plus-minus me-1"></i>INC/DEC (By Value)
                                </button>
                                <ul class="dropdown-menu" id="kw-inc-dec-dropdown">
                                    <li><a class="dropdown-item" href="#" data-type="value">By Value</a></li>
                                    <li><a class="dropdown-item" href="#" data-type="percentage">By Percentage</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">
                                <span id="kw-inc-dec-label">Value/Percentage</span>
                            </label>
                            <input type="number" id="kw-inc-dec-input" class="form-control form-control-sm"
                                placeholder="Enter value (e.g., +0.5 or -0.5)" step="0.01"
                                style="border-color: #e2e8f0;">
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
                        <div class="col-md-4 d-flex gap-2 align-items-end" id="kw-sbid-action-btns"
                            style="display: none !important;">
                            <button id="kw-apr-all-sbid-btn" class="btn btn-info btn-sm flex-fill">
                                <i class="fa-solid fa-check-double me-1"></i>APR ALL SBID
                            </button>
                            <button id="kw-save-all-sbid-m-btn" class="btn btn-success btn-sm flex-fill">
                                <i class="fa-solid fa-save me-1"></i>SAVE ALL SBID M
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PMT Ads Range Filters (hidden by default) -->
                <div id="pmt-ads-filter-section" style="display: none;">
                    <!-- Range Filter Section -->
                    <div class="row g-3 align-items-end pt-2 px-2 pb-2">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">T
                                VIEWS Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-t-views-min" class="form-control form-control-sm"
                                    placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-t-views-max" class="form-control form-control-sm"
                                    placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">L7
                                Views Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-l7-views-min" class="form-control form-control-sm"
                                    placeholder="Min" step="1" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-l7-views-max" class="form-control form-control-sm"
                                    placeholder="Max" step="1" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">CBID
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-cbid-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-cbid-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold mb-2" style="color: #475569; font-size: 0.8125rem;">SCVR
                                Range</label>
                            <div class="d-flex gap-2">
                                <input type="number" id="pmt-scvr-min" class="form-control form-control-sm"
                                    placeholder="Min" step="0.01" style="border-color: #e2e8f0;">
                                <input type="number" id="pmt-scvr-max" class="form-control form-control-sm"
                                    placeholder="Max" step="0.01" style="border-color: #e2e8f0;">
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

                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Parent / SKU dropdown + Play/Pause (same as product-master) + SKU Search + Pagination -->
                    <div
                        style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 8px 12px; background: #fff; border-bottom: 1px solid #e5e7eb;">
                        <div class="d-flex align-items-center gap-1">
                            <label for="view-type-filter" class="form-label mb-0 text-nowrap small"
                                style="font-size: 13px;">View:</label>
                            <select id="view-type-filter" class="form-select form-select-sm"
                                style="width: 100px; font-size: 13px;">
                                <option value="all">All</option>
                                <option value="parent">Parent</option>
                                <option value="sku">SKU</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <label for="parent-sku-dropdown" class="form-label mb-0 text-nowrap small"
                                style="font-size: 13px;">Parent / SKU:</label>
                            <select id="parent-sku-dropdown" class="form-select form-select-sm"
                                style="width: 220px; font-size: 13px;">
                                <option value="">All (show all)</option>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 200px; position: relative;">
                            <i class="fa fa-search"
                                style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 13px;"></i>
                            <input type="text" id="sku-search" class="form-control form-control-sm"
                                style="padding-left: 32px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px;"
                                placeholder="Search by campaign name or SKU...">
                        </div>
                        <span id="custom-pagination-counter"
                            style="font-size: 13px; color: #555; white-space: nowrap;"></span>
                    </div>
                    <div class="d-flex align-items-center mb-2" style="padding: 0 12px;">
                        <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-light rounded-circle"
                                title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-light rounded-circle"
                                title="Show all products" style="display: none;">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-light rounded-circle"
                                title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-light rounded-circle"
                                title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay-table" style="flex: 1;"></div>
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
                        <i class="fa fa-shopping-cart"></i> eBay Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
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
                                        <input type="text" class="form-control" id="addCompItemId" name="item_id"
                                            required placeholder="e.g., 123456789012">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price *</label>
                                        <input type="number" class="form-control" id="addCompPrice" name="price"
                                            step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Shipping</label>
                                        <input type="number" class="form-control" id="addCompShipping"
                                            name="shipping_cost" step="0.01" min="0" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Product Link</label>
                                        <input type="url" class="form-control" id="addCompLink" name="product_link"
                                            placeholder="https://ebay.com/itm/...">
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
                                        <input type="text" class="form-control" id="addCompTitle"
                                            name="product_title" placeholder="Product title">
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

    <!-- SKU Metrics Chart Modal -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Metrics Chart for <span id="modalSkuName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date Range:</label>
                        <select id="sku-chart-days-filter" class="form-select form-select-sm"
                            style="width: auto; display: inline-block;">
                            <option value="7">Last 7 Days</option>
                            <option value="14">Last 14 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                        </select>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-info" style="display: none;">
                        No historical data available for this SKU. Data will appear after running the metrics collection
                        command.
                    </div>
                    <div style="height: 400px;">
                        <canvas id="skuMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Column Selection Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Columns to Export</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="select-all-export-columns">
                            <i class="fa fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" id="deselect-all-export-columns">
                            <i class="fa fa-square"></i> Deselect All
                        </button>
                    </div>
                    <div id="export-columns-list"
                        style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                        <!-- Columns will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirm-export-btn">
                        <i class="fa fa-file-excel"></i> Export Selected Columns
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import eBay Ratings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file"
                                accept=".csv" required>
                            <div class="form-text">Upload a CSV file with columns: sku, rating (0-5)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">
                            <i class="fa fa-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "ebay_tabulator_column_visibility";
        /** Channel Ads% — getEbayMasterAdsPercent() / all-marketplace-master (same as AD% on each row) */
        const EBAY_CHANNEL_ADS_PCT = {{ number_format((float) ($channelAdsPercent ?? 0), 4, '.', '') }};
        /** App base path (XAMPP subdir / public): root-relative "/ebay-data-json" would 404 */
        const EBAY_DATA_JSON_URL = @json(url('/ebay-data-json'));
        let skuMetricsChart = null;
        let currentSku = null;
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let samePriceModeActive = false;
        let selectedSkus = new Set(); // Track selected SKUs across all pages

        /**
         * Child SKUs on the current pagination page only (respects filters + SKU Count).
         * Never return all filtered rows: always slice [start, start + pageSize) over the active/filtered set.
         */
        function ebayCurrentPageChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            const notParent = function(d) {
                return !isParent(d);
            };
            var page = 1;
            var pageSize = 100;
            try {
                page = table.getPage();
                pageSize = table.getPageSize();
            } catch (e) {
                /* ignore */ }
            if (page < 1) page = 1;
            const start = Math.max(0, (page - 1) * pageSize);
            const end = start + pageSize;

            var totalActive = null;
            try {
                if (typeof table.getDataCount === 'function') {
                    totalActive = table.getDataCount('active');
                }
            } catch (e) {
                /* ignore */ }

            var activeData = [];
            try {
                activeData = table.getData('active') || [];
            } catch (e) {
                /* ignore */ }

            // Full filtered dataset in memory → paginate (works with filters)
            if (activeData.length > 0) {
                var fullActiveSet = totalActive == null || activeData.length === totalActive;
                var longEnough = activeData.length >= end;
                if (fullActiveSet || longEnough) {
                    return activeData.slice(start, end).filter(notParent);
                }
                if (activeData.length <= pageSize && start === 0) {
                    return activeData.filter(notParent);
                }
            }

            try {
                var activeRows = table.getRows('active') || [];
                if (activeRows.length > 0) {
                    if (totalActive == null || activeRows.length === totalActive || activeRows.length >= end) {
                        return activeRows.slice(start, end).map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                    if (activeRows.length <= pageSize && start === 0) {
                        return activeRows.map(function(r) {
                            return r.getData();
                        }).filter(notParent);
                    }
                }
            } catch (e2) {
                /* ignore */ }

            return [];
        }

        // Play / Pause parent navigation (same as product-master)
        let productUniqueParents = [];
        let isProductNavigationActive = false;
        let currentProductParentIndex = -1;

        function ebayParentKey(p) {
            var s = (p || '').toString().trim();
            if (s.toUpperCase().startsWith('PARENT')) return s.replace(/^PARENT\s+/i, '').trim();
            return s;
        }

        // KW Ads range filter state
        let kwRangeFilters = {
            '1ub': {
                min: null,
                max: null
            },
            '7ub': {
                min: null,
                max: null
            },
            'lbid': {
                min: null,
                max: null
            },
            'acos': {
                min: null,
                max: null
            },
            'views': {
                min: null,
                max: null
            },
            'l7_views': {
                min: null,
                max: null
            }
        };
        let kwRangeFilterTimeout = null;
        let kwIncDecType = 'value'; // 'value' or 'percentage'

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
            't_views': {
                min: null,
                max: null
            },
            'l7_views': {
                min: null,
                max: null
            },
            'cbid': {
                min: null,
                max: null
            },
            'scvr': {
                min: null,
                max: null
            }
        };
        let pmtRangeFilterTimeout = null;

        // Badge filter state variables
        let zeroSoldFilterActive = false;
        let moreSoldFilterActive = false;
        let missingFilterActive = false;

        /**
         * When any narrowing filter/search is on, header "select all" should include every filtered row (all pages).
         * Default table state (E Stock &gt; 0, REQ only, etc.) = current page only.
         */
        function ebaySelectAllUsesFullFilteredSet() {
            if (typeof isProductNavigationActive !== 'undefined' && isProductNavigationActive) return true;
            if (($('#sku-search').val() || '').trim() !== '') return true;
            if (($('#parent-sku-dropdown').val() || '') !== '') return true;
            if (($('#view-type-filter').val() || 'all') !== 'all') return true;
            if (($('#inventory-filter').val() || 'more') !== 'more') return true;
            if (($('#el30-filter').val() || 'all') !== 'all') return true;
            if (($('#nrl-filter').val() || 'REQ') !== 'REQ') return true;
            if (($('#gpft-filter').val() || 'all') !== 'all') return true;
            if (($('#roi-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-filter').val() || 'all') !== 'all') return true;
            if (($('#cvr-trend-filter').val() || 'all') !== 'all') return true;
            if (($('#sprice-filter').val() || 'all') !== 'all') return true;
            if (($('#growth-sign-filter').val() || 'all') !== 'all') return true;
            var dil = 'all';
            try {
                dil = $('.column-filter[data-column="dil_percent"].active').data('color') || 'all';
            } catch (eDil) {
                /* ignore */ }
            if (dil !== 'all') return true;
            if (zeroSoldFilterActive || moreSoldFilterActive || missingFilterActive) return true;

            var sec = $('#section-filter').val();
            if (sec === 'kw_ads') {
                if (($('#kw-utilization-filter').val() || 'all') !== 'all') return true;
                if (($('#kw-status-filter').val() || 'all') !== 'all') return true;
                if (($('#kw-nra-filter').val() || 'all') !== 'all') return true;
                if (($('#kw-nrl-filter').val() || 'all') !== 'all') return true;
                if (($('#kw-sbidm-filter').val() || 'all') !== 'all') return true;
                if (Object.values(kwRangeFilters).some(function(f) {
                        return f.min !== null || f.max !== null;
                    })) return true;
            }
            if (sec === 'pmt_ads') {
                if (Object.values(pmtDropdownFilters).some(function(c) {
                        return c !== 'all';
                    })) return true;
                if (Object.values(pmtRangeFilters).some(function(f) {
                        return f.min !== null || f.max !== null;
                    })) return true;
            }
            return false;
        }

        /** All filtered child rows (every page), excluding parent summary rows. */
        function ebayAllFilteredChildRowsForSelection() {
            if (!table) return [];
            const isParent = function(d) {
                return d && d.Parent && String(d.Parent).toUpperCase().startsWith('PARENT');
            };
            try {
                return (table.getData('active') || []).filter(function(d) {
                    return !isParent(d);
                });
            } catch (e) {
                return [];
            }
        }

        function ebayRowsForHeaderSelectAll() {
            return ebaySelectAllUsesFullFilteredSet() ?
                ebayAllFilteredChildRowsForSelection() :
                ebayCurrentPageChildRowsForSelection();
        }

        // Single toast: accepts showToast(message, type) or showToast(type, message)
        function showToast(a, b) {
            var type, message;
            if (['success', 'error', 'info', 'warning'].indexOf(String(a)) !== -1 && typeof b === 'string') {
                type = a;
                message = b;
            } else {
                message = a;
                type = b || 'info';
            }
            var container = document.querySelector('.toast-container');
            if (!container) return;
            var bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' :
                'info'));
            var toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-' + bg + ' border-0';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + (message || '') +
                '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
            container.appendChild(toast);
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                new bootstrap.Toast(toast).show();
                toast.addEventListener('hidden.bs.toast', function() {
                    toast.remove();
                });
            } else {
                toast.classList.add('show');
                toast.style.position = 'fixed';
                toast.style.top = '1rem';
                toast.style.right = '1rem';
                toast.style.zIndex = '10800';
                setTimeout(function() {
                    toast.remove();
                }, 5000);
            }
        }

        // SKU-specific chart
        function initSkuMetricsChart() {
            const canvas = document.getElementById('skuMetricsChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }
            const ctx = canvas.getContext('2d');
            skuMetricsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                            label: 'Price (USD)',
                            data: [],
                            borderColor: '#FF0000',
                            backgroundColor: 'rgba(255, 0, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'Views',
                            data: [],
                            borderColor: '#0000FF',
                            backgroundColor: 'rgba(0, 0, 255, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y',
                            tension: 0.4
                        },
                        {
                            label: 'CVR%',
                            data: [],
                            borderColor: '#008000',
                            backgroundColor: 'rgba(0, 128, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1',
                            tension: 0.4
                        },
                        {
                            label: 'AD%',
                            data: [],
                            borderColor: '#FFD700',
                            backgroundColor: 'rgba(255, 215, 0, 0.1)',
                            borderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y1',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'eBay SKU Metrics',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    let value = context.parsed.y || 0;

                                    // Format based on dataset label
                                    if (label.includes('Price')) {
                                        return label + ': $' + value.toFixed(2);
                                    } else if (label.includes('Views')) {
                                        return label + ': ' + value.toLocaleString();
                                    } else if (label.includes('CVR')) {
                                        return label + ': ' + value.toFixed(1) + '%';
                                    } else if (label.includes('AD')) {
                                        return label + ': ' + Math.round(value) + '%';
                                    }
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Price/Views',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    size: 11
                                },
                                callback: function(value, index, values) {
                                    if (values.length > 0 && Math.max(...values.map(v => v.value)) < 1000) {
                                        return '$' + value.toFixed(0);
                                    }
                                    return value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Percent (%)',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                font: {
                                    size: 11
                                },
                                callback: function(value) {
                                    return value.toFixed(0) + '%';
                                }
                            }
                        }
                    }
                }
            });
        }

        function loadSkuMetricsData(sku, days = 30) {
            console.log('Loading metrics data for SKU:', sku, 'Days:', days);
            fetch(`/ebay-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Metrics data received:', data);
                    if (skuMetricsChart) {
                        if (!data || data.length === 0) {
                            console.warn('No data returned for SKU:', sku);
                            $('#chart-no-data-message').show();
                            skuMetricsChart.data.labels = [];
                            skuMetricsChart.data.datasets.forEach(dataset => {
                                dataset.data = [];
                            });
                            skuMetricsChart.options.plugins.title.text = 'eBay Metrics';
                            skuMetricsChart.update();
                            return;
                        }

                        $('#chart-no-data-message').hide();
                        skuMetricsChart.options.plugins.title.text = `eBay Metrics (${days} Days)`;
                        skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                        skuMetricsChart.data.datasets[0].data = data.map(d => d.price || 0);
                        skuMetricsChart.data.datasets[1].data = data.map(d => d.views || 0);
                        skuMetricsChart.data.datasets[2].data = data.map(d => d.cvr_percent || 0);
                        skuMetricsChart.data.datasets[3].data = data.map(d => d.ad_percent || 0);
                        skuMetricsChart.update('active');
                        console.log('Chart updated successfully with', data.length, 'data points');
                    }
                })
                .catch(error => {
                    console.error('Error loading SKU metrics data:', error);
                    alert('Error loading metrics data. Please check console for details.');
                });
        }

        $(document).ready(function() {
            // Initialize SKU-specific chart
            initSkuMetricsChart();

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

            function syncEbayDiscountBarForMode() {
                const $inp = $('#discount-percentage-input');
                if (samePriceModeActive) {
                    $('#ebay-discount-type-block').addClass('d-none');
                    $('#discount-input-label').text('eBay price:');
                    $inp.attr('placeholder', 'Each row — click Apply');
                    $inp.prop('disabled', true);
                    $inp.removeAttr('max');
                    $inp.val('');
                } else {
                    $('#ebay-discount-type-block').removeClass('d-none');
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

            function syncEbayPriceModeUi() {
                if (!table || !table.getColumn) {
                    return;
                }
                const $btn = $('#ebay-price-mode-btn');
                const selectColumn = table.getColumn('_select');
                syncEbayDiscountBarForMode();
                if (decreaseModeActive) {
                    $btn.removeClass('btn-secondary btn-success btn-outline-primary').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                    selectColumn.show();
                    return;
                }
                if (increaseModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-outline-primary').addClass('btn-success')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                    selectColumn.show();
                    return;
                }
                if (samePriceModeActive) {
                    $btn.removeClass('btn-secondary btn-danger btn-success').addClass('btn-outline-primary')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                    selectColumn.show();
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

            $('#ebay-price-mode-btn').on('click', function() {
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
                syncEbayPriceModeUi();
            });

            // Select all checkbox handler (matching Amazon approach)
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');

                // With filters/search: all matching rows (all pages). Default state: current page only.
                const filteredData = ebayRowsForHeaderSelectAll();

                // Add or remove those SKUs from the selected set
                filteredData.forEach(row => {
                    const sku = row['(Child) sku'];
                    if (sku) {
                        if (isChecked) {
                            selectedSkus.add(sku);
                        } else {
                            selectedSkus.delete(sku);
                        }
                    }
                });

                // Update all visible checkboxes
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });

                updateSelectedCount();
            });

            // Individual checkbox handler
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                if ($(this).prop('checked')) {
                    selectedSkus.add(sku);
                } else {
                    selectedSkus.delete(sku);
                }
                updateSelectedCount();
                updateSelectAllCheckbox();
            });

            // Helper: round to retail (.99 endings)
            function roundToRetailPrice(price) {
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.01).toFixed(2);
            }
            // Helper: round to retail (.49 endings) — use when .99 would match current price so S PRC stays visible
            function roundToRetailPrice49(price) {
                const roundedDollar = Math.ceil(price);
                return +(roundedDollar - 0.51).toFixed(2);
            }

            // Apply discount button
            $('#apply-discount-btn').on('click', function() {
                applyDiscount();
            });

            // Apply discount on Enter key
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) {
                    applyDiscount();
                }
            });

            // Badge click handlers for filtering
            $('#zero-sold-count-badge').on('click', function() {
                zeroSoldFilterActive = !zeroSoldFilterActive;
                moreSoldFilterActive = false;
                applyFilters();
            });

            $('#more-sold-count-badge').on('click', function() {
                moreSoldFilterActive = !moreSoldFilterActive;
                zeroSoldFilterActive = false;
                applyFilters();
            });

            $('#missing-count-badge').on('click', function() {
                missingFilterActive = !missingFilterActive;
                applyFilters();
            });

            // Clear SPRICE button handler (in selection container)
            $('#clear-sprice-selected-btn').on('click', function() {
                if (confirm('Are you sure you want to clear SPRICE for selected SKUs?')) {
                    clearSpriceForSelected();
                }
            });

            // DIL filter click handler
            $(document).on('click', '.column-filter', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $item = $(this);
                const $container = $item.closest('.manual-dropdown-container');
                const button = $container.find('button').first();

                $container.find('.column-filter').removeClass('active');
                $item.addClass('active');

                const statusCircle = $item.find('.status-circle').clone();
                button.html('').append(statusCircle).append(' DIL%');

                $container.removeClass('show');
                applyFilters();
            });

            // Apply All button handler
            $(document).on('click', '#apply-all-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                window.applyAllSelectedPrices();
            });

            // SKU chart days filter
            $('#sku-chart-days-filter').on('change', function() {
                const days = $(this).val();
                if (currentSku) {
                    if (skuMetricsChart) {
                        skuMetricsChart.options.plugins.title.text = `eBay Metrics (${days} Days)`;
                        skuMetricsChart.update();
                    }
                    loadSkuMetricsData(currentSku, days);
                }
            });

            // Update selected count display
            function updateSelectedCount() {
                const count = selectedSkus.size;
                const currentSection = $('#section-filter').val() || 'pricing';
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                // Show pricing discount bar when SKUs selected or when Price % mode is active
                if (currentSection === 'kw_ads') {
                    $('#discount-input-container').hide();
                } else {
                    $('#discount-input-container').toggle(count > 0 || decreaseModeActive || increaseModeActive ||
                        samePriceModeActive);
                }
                // Show/hide KW Ads SBID action buttons only in KW Ads section
                if (currentSection === 'kw_ads' && count > 0) {
                    $('#kw-sbid-action-btns').attr('style', 'display: flex !important;');
                    $('#kw-bulk-actions-container').css('display', 'inline-block');
                } else {
                    $('#kw-sbid-action-btns').attr('style', 'display: none !important;');
                    if (currentSection !== 'kw_ads') {
                        $('#kw-bulk-actions-container').hide();
                    }
                }
            }

            // Update select all checkbox state (matching Amazon approach)
            function updateSelectAllCheckbox() {
                if (!table) return;

                const filteredData = ebayRowsForHeaderSelectAll();

                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }

                // Get all filtered SKUs
                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));

                // Check if all filtered SKUs are selected
                const allFilteredSelected = filteredSkus.size > 0 &&
                    Array.from(filteredSkus).every(sku => selectedSkus.has(sku));

                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }

            // Background retry storage key
            const BACKGROUND_RETRY_KEY = 'ebay_failed_price_pushes';

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

                        // Skip if account is restricted (check status in table if available)
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

            // Retry function for saving SPRICE (only 1 retry for eBay)
            function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
                return new Promise((resolve, reject) => {
                    // Update status to processing
                    if (row) {
                        row.update({
                            SPRICE_STATUS: 'processing'
                        });
                    }

                    $.ajax({
                        url: '/ebay-one/save-sprice',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            sprice: sprice
                        },
                        success: function(response) {
                            // Re-find row by SKU so we update the current row (avoids blank S PRC if table redrew)
                            let targetRow = row;
                            if (table && table.getRows) {
                                table.getRows().forEach(function(r) {
                                    if (r.getData()['(Child) sku'] === sku) targetRow =
                                        r;
                                });
                            }
                            const numSprice = typeof sprice === 'number' && !isNaN(sprice) ?
                                sprice : parseFloat(sprice);
                            if (targetRow) {
                                targetRow.update({
                                    SPRICE: numSprice,
                                    SPFT: response.spft_percent != null ? response
                                        .spft_percent : 0,
                                    SROI: response.sroi_percent != null ? response
                                        .sroi_percent : 0,
                                    SGPFT: response.sgpft_percent != null ? response
                                        .sgpft_percent : 0,
                                    SPRICE_STATUS: numSprice > 0 ? 'saved' : null,
                                    has_custom_sprice: numSprice > 0
                                });
                                targetRow.reformat();
                            }
                            resolve(response);
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.error || xhr.responseText ||
                                'Failed to save SPRICE';
                            console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`,
                                errorMsg);

                            // Only retry once (retryCount < 1)
                            if (retryCount < 1) {
                                console.log(`Retrying SKU ${sku} in 2 seconds...`);
                                setTimeout(() => {
                                    saveSpriceWithRetry(sku, sprice, row, retryCount +
                                            1)
                                        .then(resolve)
                                        .catch(reject);
                                }, 2000);
                            } else {
                                console.error(`Max retries reached for SKU ${sku}`);
                                // Update status to error
                                if (row) {
                                    row.update({
                                        SPRICE_STATUS: 'error'
                                    });
                                }
                                reject({
                                    error: true,
                                    xhr: xhr
                                });
                            }
                        }
                    });
                });
            }

            // Apply price with retry logic (for pushing to eBay)
            async function applyPriceWithRetry(sku, price, cell, retries = 0, isBackgroundRetry = false) {
                const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
                const row = cell ? cell.getRow() : null;
                const rowData = row ? row.getData() : null;

                // Background mode: single attempt, no internal recursion (global max 5 handled via retryCount)
                if (isBackgroundRetry) {
                    try {
                        const response = await $.ajax({
                            url: '/push-ebay-price-tabulator',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                sku: sku,
                                price: price
                            }
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
                // Set initial loading state (only if cell exists)
                if (retries === 0 && cell && $btn && row) {
                    $btn.prop('disabled', true);
                    $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                    $btn.attr('style',
                    'border: none; background: none; color: #ffc107; padding: 0;'); // Yellow text, no background
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'processing';
                        row.update(rowData);
                    }
                }

                try {
                    const response = await $.ajax({
                        url: '/push-ebay-price-tabulator',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            sku: sku,
                            price: price
                        }
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
                        $btn.attr('style',
                        'border: none; background: none; color: #28a745; padding: 0;'); // Green text, no background
                    }

                    if (!isBackgroundRetry) {
                        showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                    }

                    return true;
                } catch (xhr) {
                    const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr
                        .responseJSON?.message || 'Failed to apply price';
                    const errorCode = xhr.responseJSON?.errors?.[0]?.code || '';
                    console.error(`Attempt ${retries + 1} for SKU ${sku} failed:`, errorMsg);

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
                            $btn.attr('style',
                            'border: none; background: none; color: #ff6b00; padding: 0;'); // Orange text for restriction
                            $btn.attr('title', 'Account restricted - cannot update price');
                        }

                        showToast(
                            `Account restriction detected for SKU: ${sku}. Please resolve account restrictions in eBay before updating prices.`,
                            'error');
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
                            $btn.attr('style',
                            'border: none; background: none; color: #dc3545; padding: 0;'); // Red text, no background
                        }

                        // Save for background retry (only if not already a background retry)
                        saveFailedSkuForRetry(sku, price, 0);
                        showToast(
                            `Failed to apply price for SKU: ${sku} after multiple retries. Will retry in background (max 5 times).`,
                            'error');

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
                            url: '/push-ebay-price-tabulator',
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
                                    const errorMsg = response.errors[0].message ||
                                        'Unknown error';
                                    const errorCode = response.errors[0].code || '';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                        errorMsg, 'Code:', errorCode);

                                    if (attempt < maxRetries) {
                                        console.log(
                                            `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                            );
                                        setTimeout(attemptApply, delay);
                                    } else {
                                        console.error(`Max retries reached for SKU ${sku}`);
                                        reject({
                                            error: true,
                                            response: response
                                        });
                                    }
                                } else {
                                    console.log(
                                        `Successfully pushed price for SKU ${sku} on attempt ${attempt}`
                                        );
                                    resolve({
                                        success: true,
                                        response: response
                                    });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr
                                    .responseJSON?.error || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`,
                                    errorMsg);

                                if (attempt < maxRetries) {
                                    console.log(
                                        `Retry attempt ${attempt} for SKU ${sku} after ${delay/1000} seconds...`
                                        );
                                    setTimeout(attemptApply, delay);
                                } else {
                                    console.error(`Max retries reached for SKU ${sku}`);
                                    reject({
                                        error: true,
                                        xhr: xhr
                                    });
                                }
                            }
                        });
                    }

                    attemptApply();
                });
            }

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
                            skusToProcess.push({
                                sku: sku,
                                price: sprice
                            });
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
                            showToast(
                                `Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`,
                                'success');

                            // Reset to original state after 3 seconds
                            setTimeout(() => {
                                $btn.html(originalHtml);
                            }, 3000);
                        } else {
                            $btn.html(originalHtml);
                            showToast(
                                `Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`,
                                'error');
                        }
                        return;
                    }

                    const {
                        sku,
                        price
                    } = skusToProcess[currentIndex];

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
                                $btnInCell.attr('style',
                                    'border: none; background: none; color: #ffc107; padding: 0;');
                            }
                        }
                    }

                    // First save to database (like SPRICE edit does), then push to eBay
                    console.log(
                        `Processing SKU ${sku} (${currentIndex + 1}/${skusToProcess.length}): Saving SPRICE ${price} to database...`
                        );

                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            sku: sku,
                            sprice: price,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(saveResponse) {
                            console.log(`SKU ${sku}: Database save successful`, saveResponse);
                            if (saveResponse.error) {
                                console.error(`Failed to save SPRICE for SKU ${sku}:`, saveResponse
                                    .error);
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
                                            $btnInCell.attr('style',
                                                'border: none; background: none; color: #dc3545; padding: 0;'
                                                );
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

                            // After saving, push to eBay using retry function
                            console.log(`SKU ${sku}: Starting eBay price push...`);
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
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                    '<i class="fa-solid fa-check-double"></i>'
                                                    );
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #28a745; padding: 0;'
                                                    );
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
                                            const $btnInCell = $cellElement.find(
                                                '.apply-price-btn');
                                            if ($btnInCell.length) {
                                                $btnInCell.prop('disabled', false);
                                                $btnInCell.html(
                                                '<i class="fa-solid fa-x"></i>');
                                                $btnInCell.attr('style',
                                                    'border: none; background: none; color: #dc3545; padding: 0;'
                                                    );
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
                            console.error(`Failed to save SPRICE for SKU ${sku}:`, xhr
                                .responseJSON || xhr.responseText);
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
                                        $btnInCell.attr('style',
                                            'border: none; background: none; color: #dc3545; padding: 0;'
                                            );
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

            // Apply discount to selected SKUs (same flow as Amazon: validate, round .99/.49, re-find row on save)
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
                    if (selectedSkus.has(sku)) {
                        const originalPrice = parseFloat(row['eBay Price']) || 0;
                        if (originalPrice <= 0) {
                            return;
                        }

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
                            tableRow.update({
                                SPRICE: newPriceNum,
                                SPRICE_STATUS: 'processing'
                            });
                        }

                        saveSpriceWithRetry(sku, newPriceNum, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE set to eBay price for ${updatedCount} SKU(s)` :
                                            `Discount applied to ${updatedCount} SKU(s)`,
                                            'success'
                                        );
                                    } else {
                                        showToast(
                                            appliedAsSamePrice ?
                                            `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                            `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                            'error'
                                        );
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) tableRow.update({
                                    SPRICE: originalSPrice
                                });
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(
                                        appliedAsSamePrice ?
                                        `SPRICE updated for ${updatedCount} SKU(s), ${errorCount} failed` :
                                        `Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`,
                                        'error'
                                    );
                                }
                            });
                    }
                });
            }

            // Clear SPRICE for selected SKUs (same method as Amazon: batch POST to clear endpoint, then update table)
            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) {
                    showToast('Please select SKUs first', 'error');
                    return;
                }

                if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                    return;
                }

                let clearedCount = 0;
                const updates = [];

                table.getRows().forEach(row => {
                    const rowData = row.getData();
                    const sku = rowData['(Child) sku'];
                    if (!sku || !selectedSkus.has(sku)) return;
                    if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith('PARENT')) return;

                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0,
                        SPRICE_STATUS: null,
                        has_custom_sprice: false
                    });
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    clearedCount++;
                });

                if (updates.length > 0) {
                    $.ajax({
                        url: '/ebay-clear-sprice',
                        method: 'POST',
                        contentType: 'application/json',
                        dataType: 'json',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'Accept': 'application/json'
                        },
                        data: JSON.stringify({
                            updates: updates
                        }),
                        success: function(response) {
                            showToast(response.message || `SPRICE cleared for ${clearedCount} SKU(s)`,
                                'success');
                        },
                        error: function(xhr) {
                            console.error('Failed to clear SPRICE:', xhr.status, xhr.responseJSON || xhr
                                .responseText);
                            var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON
                                .error : 'Failed to clear SPRICE data';
                            showToast(msg, 'error');
                        }
                    });
                } else {
                    showToast('warning', 'No SPRICE values to clear for selected SKUs');
                }
            }

            // Build parent list from table (same logic as dropdown in dataLoaded) - call when needed so Play always has list
            function buildProductUniqueParentsFromTable() {
                if (typeof table === 'undefined' || !table) return [];
                var allRows = table.getData('all') || [];
                var seen = {};
                var list = [];
                allRows.forEach(function(r) {
                    var p = (r.Parent || '').toString().trim();
                    if (p && !String(p).toUpperCase().startsWith('PARENT') && !seen[p]) {
                        seen[p] = true;
                        list.push(p);
                    }
                });
                list.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                return list;
            }

            // Play / Pause parent navigation (same as product-master) - productUniqueParents set in dataLoaded or on first Play
            function initProductPlaybackControls() {
                if (typeof table === 'undefined' || !table) return;
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }

                // Use event delegation so clicks work even if buttons are re-rendered (same as product-master behavior)
                $(document).off('click.ebayplay', '#play-forward').on('click.ebayplay', '#play-forward',
                    productNextParent);
                $(document).off('click.ebayplay', '#play-backward').on('click.ebayplay', '#play-backward',
                    productPreviousParent);
                $(document).off('click.ebayplay', '#play-pause').on('click.ebayplay', '#play-pause',
                    productStopNavigation);
                $(document).off('click.ebayplay', '#play-auto').on('click.ebayplay', '#play-auto',
                    productStartNavigation);

                updateProductButtonStates();
            }

            function productStartNavigation(e) {
                if (e) e.preventDefault();
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    productUniqueParents = buildProductUniqueParentsFromTable();
                }
                if (!productUniqueParents || productUniqueParents.length === 0) {
                    showToast('info', 'No parent groups in data');
                    return;
                }
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                applyFilters();
                table.setPage(1);
                $('#play-auto').hide();
                $('#play-pause').show().removeClass('btn-light');
                updateProductButtonStates();
            }

            function productStopNavigation(e) {
                if (e) e.preventDefault();
                isProductNavigationActive = false;
                currentProductParentIndex = -1;
                $('#play-pause').hide();
                $('#play-auto').show().removeClass('btn-success btn-warning btn-danger').addClass('btn-light');
                applyFilters();
                updateProductButtonStates();
            }

            function productNextParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex >= productUniqueParents.length - 1) return;
                currentProductParentIndex++;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }

            function productPreviousParent(e) {
                if (e) e.preventDefault();
                if (!isProductNavigationActive) return;
                if (currentProductParentIndex <= 0) return;
                currentProductParentIndex--;
                applyFilters();
                table.setPage(1);
                updateProductButtonStates();
            }

            function updateProductButtonStates() {
                $('#play-backward').prop('disabled', !isProductNavigationActive || currentProductParentIndex <= 0);
                $('#play-forward').prop('disabled', !isProductNavigationActive || currentProductParentIndex >=
                    productUniqueParents.length - 1);
                $('#play-auto').attr('title', isProductNavigationActive ? 'Show all products' :
                    'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', 'Next parent');
                $('#play-backward').attr('title', 'Previous parent');
                if (isProductNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            // Image hover preview (forecast.analysis pattern)
            let ebayMpImagePreviewHideTimer = null;
            let ebayMpImagePreviewEl = null;

            function ebayMpRemoveImagePreview() {
                if (ebayMpImagePreviewHideTimer) {
                    clearTimeout(ebayMpImagePreviewHideTimer);
                    ebayMpImagePreviewHideTimer = null;
                }
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
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
                document.querySelectorAll('#image-hover-preview').forEach(function(el) {
                    el.remove();
                });
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

            // Event delegation for eye button clicks (add to SKU column formatter)
            table = new Tabulator("#ebay-table", {
                ajaxURL: EBAY_DATA_JSON_URL,
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    var text;
                    if (!totalRows || totalRows < 1) {
                        text = "Showing 0 of 0 rows";
                    } else {
                        var start = currentRow;
                        var end = Math.min(currentRow + pageSize - 1, totalRows);
                        text = "Showing " + start + "-" + end + " of " + totalRows + " rows";
                    }
                    $('#custom-pagination-counter').text(text);
                    return text;
                },
                columnCalcs: "both",
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
                    },
                    {
                        column: "_parent_sort",
                        dir: "asc"
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    const isParent = data.Parent && String(data.Parent).toUpperCase().startsWith(
                        'PARENT');
                    const el = row.getElement();
                    if (isParent) {
                        el.classList.add('ebay-parent-row');
                        el.style.setProperty('background-color', '#b3e5fc', 'important');
                    } else {
                        el.classList.remove('ebay-parent-row');
                    }
                },
                columns: [{
                        title: "",
                        field: "_parent_sort",
                        visible: false,
                        width: 0
                    },
                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: true,
                        frozen: true,
                        width: 50,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="No extra filter: this page only. If filter/search is on: all matching rows (all pages).">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            const isSelected = sku ? selectedSkus.has(sku) : false;
                            if (isParent) {
                                return '<input type="checkbox" class="sku-select-checkbox" data-sku="' +
                                    (sku || '') +
                                    '" disabled style="cursor: not-allowed; opacity: 0.6;">';
                            }
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku || ''}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
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
                                return '<img src="' + u + '" data-full="' + u +
                                    '" class="hover-thumb" alt="Product" style="width: 50px; height: 50px; object-fit: cover; cursor: zoom-in;">';
                            }
                            return '';
                        },
                        cellMouseOver: function(e, cell) {
                            const img = cell.getElement().querySelector('.hover-thumb');
                            if (!img) return;
                            ebayMpShowImagePreview(e.clientX, e.clientY, img.getAttribute(
                                'data-full'));
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
                            if (related && typeof related.closest === 'function' && related.closest(
                                    '#image-hover-preview')) {
                                ebayMpCancelImagePreviewHide();
                                return;
                            }
                            ebayMpScheduleImagePreviewHide();
                        },
                        headerSort: false,
                        width: 80
                    },
                    {
                        title: "Parent",
                        field: "Parent",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search Parent...",
                        cssClass: "text-primary",
                        tooltip: true,
                        frozen: true,
                        width: 150,
                        visible: true,
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            if (String(value).toUpperCase().startsWith('PARENT ')) {
                                return String(value).replace(/^PARENT\s+/i, '').trim();
                            }
                            return value;
                        }
                    },
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search SKU...",
                        cssClass: "text-primary fw-bold",
                        tooltip: true,
                        frozen: true,
                        width: 250,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            let sku = cell.getValue();
                            if (!sku && rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT')) {
                                sku = rowData.Parent;
                            }
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
                            let html =
                                `<span class="${isParent ? 'fw-bold text-primary' : ''}">${sku || ''}</span>`;
                            if (sku) {
                                html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                       style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                       data-sku="${sku}"
                                       title="Copy SKU"></i>`;
                                if (!isParent) {
                                    html += `<button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;">
                                        <i class="fa fa-info-circle"></i>
                                     </button>`;
                                }
                            }
                            return html;
                        }
                    },
                    {
                        title: "Ratings",
                        field: "rating",
                        hozAlign: "center",
                        editor: "input",
                        tooltip: "Enter rating between 0 and 5",
                        width: 80,
                        visible: false
                    },
                    {
                        title: "Links",
                        field: "links_column",
                        frozen: true,
                        width: 100,
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const buyerLink = rowData['B Link'] || '';
                            const sellerLink = rowData['S Link'] || '';

                            let html =
                                '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';

                            if (sellerLink) {
                                html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> S Link
                                </a>`;
                            }

                            if (buyerLink) {
                                html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                                    <i class="fa fa-link"></i> B Link
                                </a>`;
                            }

                            if (!sellerLink && !buyerLink) {
                                html +=
                                '<span class="text-muted" style="font-size: 12px;">-</span>';
                            }

                            html += '</div>';
                            return html;
                        },
                        headerSort: false
                    },

                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
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

                            // Color logic from inc/dec page - getDilColor
                            if (dil < 16.66) color = '#a00211'; // red
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)

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
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
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
                            let color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' : (
                                val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
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
                            const isParent = rowData.Parent && String(rowData.Parent).toUpperCase()
                                .startsWith('PARENT');
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
                                arrowHtml =
                                    ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
                            }
                            const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' :
                                (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
                            return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml}`;
                        },
                        width: 65
                    },
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
                                dotColor = '#ffc107';
                                title = 'NRL or NRA - Not Required';
                            } else if (hasCampaign) {
                                dotColor = '#28a745';
                                title = 'Campaign Exists';
                            } else {
                                dotColor = '#dc3545';
                                title = 'Campaign Missing';
                            }

                            return '<div style="display: flex; align-items: center; justify-content: center;">' +
                                '<span style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: ' +
                                dotColor + ';" title="' + title + '"></span>' +
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
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
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
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                    {
                        title: "E L60",
                        field: "eBay L60",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
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
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
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
                            const num = Math.round(parseFloat(value) || 0);
                            return num;
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
                        title: "Missing L",
                        field: "Missing",
                        hozAlign: "center",
                        width: 70,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            if (rowData.Parent && String(rowData.Parent).toUpperCase().startsWith(
                                    'PARENT')) {
                                return '';
                            }
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "MAP",
                        field: "MAP",
                        hozAlign: "center",
                        width: 90,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '';
                            }
                            const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                            const inv = parseFloat(rowData['INV']) || 0;
                            if (inv > 0 && ebayStock === 0) {
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${inv})</span>`;
                            }
                            if (inv > 0 && ebayStock > 0) {
                                if (inv === ebayStock) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                } else {
                                    const diff = inv - ebayStock;
                                    const sign = diff > 0 ? '+' : '';
                                    return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                                }
                            }
                            return '';
                        }
                    },

                    {
                        title: "L30 View",
                        field: "views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            let color = '';

                            // getViewColor logic from inc/dec page
                            if (value >= 30) color = '#28a745'; // green
                            else color = '#a00211'; // red

                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                        },
                        width: 50
                    },
                    {
                        title: "L7 VIEWS",
                        field: "l7_views",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            var value = parseInt(cell.getValue() || 0);
                            return value.toLocaleString();
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
                            var value = parseFloat(cell.getValue() || 0);
                            return value.toFixed(1) + '%';
                        },
                        width: 70
                    },

                    {
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith(
                                'PARENT');

                            // Don't show dropdown for parent rows
                            // if (isParent) {
                            //     return '';
                            // }

                            // Get value and handle null/undefined/empty cases
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '' || value
                                .trim() === '') {
                                value = 'REQ';
                            }

                            let bgColor = '#f8f9fa';
                            let textColor = '#000';

                            if (value === 'REQ') {
                                bgColor = '#28a745';
                                textColor = 'white';
                            } else if (value === 'NR') {
                                bgColor = '#dc3545';
                                textColor = 'white';
                            }

                            return `<select class="form-select form-select-sm nr-req-dropdown" 
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
                        title: "Prc",
                        field: "eBay Price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const rowData = cell.getRow().getData();
                            const lmpPrice = parseFloat(rowData['lmp_price'] || 0);

                            if (value === 0) {
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }

                            if (lmpPrice > 0 && value > lmpPrice) {
                                return `<span style="color: #dc3545; font-weight: 600;">$${value.toFixed(2)}</span>`;
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

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 50
                    },


                    {
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                            // Custom sorter to handle the 100% case
                            const aData = aRow.getData();
                            const bData = bRow.getData();

                            const aKwSpend = parseFloat(aData['kw_spend_L30'] || 0);
                            const bKwSpend = parseFloat(bData['kw_spend_L30'] || 0);

                            // Calculate effective AD% (100 if kw_spend > 0 and AD% is 0)
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

                            // If KW ads spend > 0 but AD% is 0, show red alert
                            if (kwSpend > 0 && adPercent === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                            }

                            return `${parseFloat(value).toFixed(1)}%`;
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

                            // PFT% = GPFT% - AD%
                            const percent = gpft - ad;
                            let color = '';

                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

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
                        title: "GROI%",
                        field: "ROI%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            let color = '';

                            // getRoiColor logic from inc/dec page
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

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

                            let html =
                                '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';

                            // Show lowest price
                            if (lmpPrice) {
                                const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
                                const priceColor = (lmpPrice < currentPrice) ? '#dc3545' :
                                '#28a745';
                                html +=
                                    `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
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
                        title: "S PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            const currentPrice = parseFloat(rowData['eBay Price']) || 0;
                            const spriceNum = (value != null && value !== '') ? parseFloat(value) :
                                NaN;
                            const sprice = isNaN(spriceNum) ? 0 : spriceNum;

                            // Blank only when SPRICE is missing or zero (no override)
                            if (value == null || value === '' || isNaN(spriceNum) || sprice <= 0)
                                return '';

                            // Show blank if price and SPRICE match (same as eBay Price)
                            if (currentPrice > 0 && sprice > 0 && currentPrice.toFixed(2) === sprice
                                .toFixed(2)) {
                                return '';
                            }

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
                                <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                    <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                                </button>
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');

                            // if (isParent) return '';

                            const sku = rowData['(Child) sku'];
                            const sprice = parseFloat(rowData.SPRICE) || 0;
                            const status = rowData.SPRICE_STATUS || null;

                            if (!sprice || sprice === 0) {
                                return '<span style="color: #999;">N/A</span>';
                            }

                            let icon = '<i class="fas fa-check"></i>';
                            let iconColor = '#28a745'; // Green for ready
                            let titleText = 'Apply Price to eBay';

                            if (status === 'processing') {
                                icon = '<i class="fas fa-spinner fa-spin"></i>';
                                iconColor = '#ffc107'; // Yellow text
                                titleText = 'Price pushing in progress...';
                            } else if (status === 'pushed') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText =
                                'Price pushed to eBay (Double-click to mark as Applied)';
                            } else if (status === 'applied') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'Price applied to eBay (Double-click to change)';
                            } else if (status === 'saved') {
                                icon = '<i class="fa-solid fa-check-double"></i>';
                                iconColor = '#28a745'; // Green text
                                titleText = 'SPRICE saved (Click to push to eBay)';
                            } else if (status === 'error') {
                                icon = '<i class="fa-solid fa-x"></i>';
                                iconColor = '#dc3545'; // Red text
                                titleText = 'Error applying price to eBay';
                            } else if (status === 'account_restricted') {
                                icon = '<i class="fa-solid fa-ban"></i>';
                                iconColor = '#ff6b00'; // Orange text
                                titleText =
                                    'Account restricted - Cannot update price. Please resolve account restrictions in eBay.';
                            }

                            // Show only icon with color, no background
                            return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                                ${icon}
                            </button>`;
                        },
                        cellClick: function(e, cell) {
                            const $target = $(e.target);

                            // Handle double-click to change status from 'pushed' to 'applied'
                            if (e.originalEvent && e.originalEvent.detail === 2) {
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const currentStatus = $btn.attr('data-status') || '';

                                if (currentStatus === 'pushed') {
                                    const sku = $btn.attr('data-sku') || $btn.data('sku');
                                    $.ajax({
                                        url: '/update-ebay-sprice-status',
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: {
                                            sku: sku,
                                            status: 'applied'
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                table.replaceData();
                                                showToast('Status updated to Applied',
                                                    'success');
                                            }
                                        }
                                    });
                                }
                                return;
                            }

                            if ($target.hasClass('apply-price-btn') || $target.closest(
                                    '.apply-price-btn').length) {
                                e.stopPropagation();
                                const $btn = $target.hasClass('apply-price-btn') ? $target : $target
                                    .closest('.apply-price-btn');
                                const sku = $btn.attr('data-sku') || $btn.data('sku');
                                const price = parseFloat($btn.attr('data-price') || $btn.data(
                                    'price'));
                                const currentStatus = $btn.attr('data-status') || '';

                                if (!sku || !price || price <= 0 || isNaN(price)) {
                                    showToast('Invalid SKU or price', 'error');
                                    return;
                                }

                                // If status is 'saved' or null, first save SPRICE, then push to eBay
                                if (currentStatus === 'saved' || !currentStatus) {
                                    const row = cell.getRow();
                                    row.update({
                                        SPRICE_STATUS: 'processing'
                                    });

                                    saveSpriceWithRetry(sku, price, row)
                                        .then((response) => {
                                            // After saving, push to eBay
                                            applyPriceWithRetry(sku, price, cell, 0);
                                        })
                                        .catch((error) => {
                                            row.update({
                                                SPRICE_STATUS: 'error'
                                            });
                                            showToast('Failed to save SPRICE', 'error');
                                        });
                                } else {
                                    // If already saved, just push to eBay
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
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

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

                            // SPFT = SGPFT - AD%
                            const percent = sgpft - ad;
                            if (isNaN(percent)) return '';

                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent < 30) color = '#ffc107'; // yellow
                            else if (percent >= 30 && percent < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50% and above)

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
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink

                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
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
                        width: 110
                    },

                    // ========== KW Ads Section Columns (hidden by default) ==========
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
                            if (isNaN(acos) || acos === 0) {
                                acos = 100;
                            }
                            var suggestedBudget = 0;
                            if (acos < 4) {
                                suggestedBudget = 9;
                            } else if (acos >= 4 && acos < 8) {
                                suggestedBudget = 6;
                            } else {
                                suggestedBudget = 3;
                            }
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
                                    '<i class="fa-solid fa-info-circle kw-toggle-metrics-btn" style="cursor: pointer; font-size: 12px; margin-left: 5px;" title="Toggle Clicks, Spend, Ad Sold"></i></div>';
                            }

                            var acosValue = "";
                            if (acos === 0) {
                                td.classList.add('red-bg');
                                acosValue = "100%";
                            } else if (acos < 7) {
                                td.classList.add('pink-bg');
                                acosValue = acos.toFixed(0) + "%";
                            } else if (acos >= 7 && acos <= 14) {
                                td.classList.add('green-bg');
                                acosValue = acos.toFixed(0) + "%";
                            } else {
                                td.classList.add('red-bg');
                                acosValue = acos.toFixed(0) + "%";
                            }

                            return '<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">' +
                                acosValue +
                                '<i class="fa-solid fa-info-circle kw-toggle-metrics-btn" style="cursor: pointer; font-size: 12px; margin-left: 5px;" title="Toggle Clicks, Spend, Ad Sold"></i></div>';
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('kw-toggle-metrics-btn') || e.target
                                .closest('.kw-toggle-metrics-btn')) {
                                e.stopPropagation();
                                var clicksVisible = table.getColumn('kw_clicks').isVisible();
                                var spendVisible = table.getColumn('kw_spend_L30').isVisible();
                                var adSoldVisible = table.getColumn('kw_ad_sold').isVisible();

                                if (clicksVisible || spendVisible || adSoldVisible) {
                                    table.hideColumn('kw_clicks');
                                    table.hideColumn('kw_spend_L30');
                                    table.hideColumn('kw_ad_sold');
                                } else {
                                    table.showColumn('kw_clicks');
                                    table.showColumn('kw_spend_L30');
                                    table.showColumn('kw_ad_sold');
                                }
                            }
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
                        title: "KW SPEND L30",
                        field: "kw_spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return Math.round(value);
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${Math.round(parseFloat(value))}</strong>`;
                        },
                        width: 110
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
                            var aUb7 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(
                                aData.kw_l7_spend || 0) / (parseFloat(aData
                                .kw_campaignBudgetAmount) * 7)) * 100 : 0;
                            var bUb7 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(
                                bData.kw_l7_spend || 0) / (parseFloat(bData
                                .kw_campaignBudgetAmount) * 7)) * 100 : 0;
                            return aUb7 - bUb7;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.kw_l7_spend) || 0;
                            var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 66 && ub7 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 66 && budget > 0) {
                                td.classList.add('red-bg');
                            }
                            return ub7.toFixed(0) + '%';
                        },
                        width: 70
                    },
                    {
                        title: "1UB%",
                        field: "kw_l1_spend",
                        hozAlign: "center",
                        visible: false,
                        sorter: function(a, b, aRow, bRow) {
                            var aData = aRow.getData();
                            var bData = bRow.getData();
                            var aUb1 = parseFloat(aData.kw_campaignBudgetAmount) > 0 ? (parseFloat(
                                aData.kw_l1_spend || 0) / parseFloat(aData
                                .kw_campaignBudgetAmount)) * 100 : 0;
                            var bUb1 = parseFloat(bData.kw_campaignBudgetAmount) > 0 ? (parseFloat(
                                bData.kw_l1_spend || 0) / parseFloat(bData
                                .kw_campaignBudgetAmount)) * 100 : 0;
                            return aUb1 - bUb1;
                        },
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.kw_l1_spend) || 0;
                            var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 66 && ub1 <= 99) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 99) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 66 && budget > 0) {
                                td.classList.add('red-bg');
                            }
                            return ub1.toFixed(0) + '%';
                        },
                        width: 70
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
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
                            return parseFloat(value).toFixed(2);
                        },
                        width: 70
                    },
                    {
                        title: "SBID",
                        field: "kw_sbid_calc",
                        hozAlign: "center",
                        visible: false,
                        sorter: function(a, b, aRow, bRow) {
                            function calcSbid(rowData) {
                                var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                                var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                                var budget = parseFloat(rowData.kw_campaignBudgetAmount) || 0;
                                var inv = parseFloat(rowData.INV || 0);
                                var price = parseFloat(rowData['eBay Price'] || 0);
                                var ub7 = budget > 0 ? (parseFloat(rowData.kw_l7_spend) || 0) / (
                                    budget * 7) * 100 : 0;
                                var ub1 = budget > 0 ? (parseFloat(rowData.kw_l1_spend) || 0) /
                                    budget * 100 : 0;
                                var lastSbidRaw = rowData.kw_last_sbid;
                                var lastSbid = (!lastSbidRaw || lastSbidRaw === '' ||
                                    lastSbidRaw === '0') ? 0 : parseFloat(lastSbidRaw) || 0;

                                if (ub7 > 99 && ub1 > 99) {
                                    if (l1Cpc > 1.25) return Math.floor(l1Cpc * 0.80 * 100) / 100;
                                    if (l1Cpc > 0) return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                    if (l7Cpc > 0) return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                    return 0;
                                }
                                var isOver = ub7 > 99 && ub1 > 99;
                                var isUnder = !isOver && budget > 0 && ub7 < 66 && ub1 < 66 && inv >
                                    0;
                                if (isOver) {
                                    var sbid = l1Cpc > 1.25 ? Math.floor(l1Cpc * 0.80 * 100) / 100 :
                                        (l1Cpc > 0 ? Math.floor(l1Cpc * 0.90 * 100) / 100 : 0);
                                    if (price < 20 && sbid > 0.20) sbid = 0.20;
                                    return sbid;
                                }
                                if (isUnder) {
                                    var baseBid = lastSbid > 0 ? lastSbid : (l1Cpc > 0 ? l1Cpc : (
                                        l7Cpc > 0 ? l7Cpc : 0));
                                    if (ub1 < 33) return Math.floor((baseBid + 0.10) * 100) / 100;
                                    if (ub1 >= 33 && ub1 < 66) return Math.floor(baseBid * 1.10 *
                                        100) / 100;
                                    return Math.floor(baseBid * 100) / 100;
                                }
                                if (l1Cpc > 0) return Math.floor(l1Cpc * 0.90 * 100) / 100;
                                if (l7Cpc > 0) return Math.floor(l7Cpc * 0.90 * 100) / 100;
                                return 0;
                            }
                            return calcSbid(aRow.getData()) - calcSbid(bRow.getData());
                        },
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();

                            // Check if NRA is selected
                            var nraValue = rowData.NR ? rowData.NR.trim() : "";
                            if (nraValue === 'NRA') {
                                return '-';
                            }

                            var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                            var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                            var budget = parseFloat(rowData.kw_campaignBudgetAmount) || 0;
                            var inv = parseFloat(rowData.INV || 0);
                            var price = parseFloat(rowData['eBay Price'] || 0);
                            var ub7 = budget > 0 ? (parseFloat(rowData.kw_l7_spend) || 0) / (
                                budget * 7) * 100 : 0;
                            var ub1 = budget > 0 ? (parseFloat(rowData.kw_l1_spend) || 0) / budget *
                                100 : 0;
                            var lastSbidRaw = rowData.kw_last_sbid;
                            var lastSbid = (!lastSbidRaw || lastSbidRaw === '' || lastSbidRaw ===
                                '0') ? 0 : parseFloat(lastSbidRaw) || 0;

                            // Helper function to get UB color
                            function getUbColor(ub) {
                                if (ub >= 66 && ub <= 99) return 'green';
                                if (ub > 99) return 'pink';
                                return 'red';
                            }

                            var ub7Color = getUbColor(ub7);
                            var ub1Color = getUbColor(ub1);

                            if (ub7Color !== ub1Color) {
                                return '-'; // No SBID suggestion if colors don't match
                            }

                            var sbid = 0;

                            // Rule: If both UB7 and UB1 are above 99%
                            if (ub7 > 99 && ub1 > 99) {
                                if (l1Cpc > 0) {
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                } else if (l7Cpc > 0) {
                                    sbid = Math.floor(l7Cpc * 0.90 * 100) / 100;
                                }
                                if (sbid === 0) return '-';
                                return sbid.toFixed(2);
                            }

                            // Determine utilization status
                            var isOverUtilized = ub7 > 99 && ub1 > 99;
                            var isUnderUtilized = !isOverUtilized && budget > 0 && ub7 < 66 && ub1 <
                                66 && inv > 0;

                            if (isOverUtilized) {
                                if (l1Cpc > 1.25) {
                                    sbid = Math.floor(l1Cpc * 0.80 * 100) / 100;
                                } else if (l1Cpc > 0) {
                                    sbid = Math.floor(l1Cpc * 0.90 * 100) / 100;
                                }
                                if (price < 20 && sbid > 0.20) sbid = 0.20;
                            } else if (isUnderUtilized) {
                                var baseBid = lastSbid > 0 ? lastSbid : (l1Cpc > 0 ? l1Cpc : (
                                    l7Cpc > 0 ? l7Cpc : 0));
                                if (baseBid > 0) {
                                    if (ub1 < 33) sbid = Math.floor((baseBid + 0.10) * 100) / 100;
                                    else if (ub1 >= 33 && ub1 < 66) sbid = Math.floor(baseBid *
                                        1.10 * 100) / 100;
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
                        editorParams: {
                            elementAttributes: {
                                maxlength: "10"
                            }
                        },
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (!value || value === '' || value === '0' || value === 0) {
                                return '-';
                            }
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
                            if (e.target.classList.contains("kw-update-bid-icon") || e.target
                                .closest(".kw-update-bid-icon")) {
                                var rowData = cell.getRow().getData();

                                // Check if bid is already pushed
                                var apprSbid = rowData.kw_apprSbid || '';
                                if (apprSbid && apprSbid !== '' && parseFloat(apprSbid) > 0) {
                                    return; // Don't allow re-push
                                }

                                // Check if NRA is selected
                                var nraValue = rowData.NR ? rowData.NR.trim() : "";
                                if (nraValue === 'NRA') {
                                    showToast('error', 'Cannot update bid for NRA campaigns');
                                    return;
                                }

                                // Get sbid_m value
                                var sbidM = parseFloat(rowData.kw_sbid_m) || 0;

                                if (sbidM <= 0) {
                                    showToast('error',
                                        'SBID M value is required. Please save SBID M first.');
                                    return;
                                }

                                if (!rowData.kw_campaign_id) {
                                    showToast('error', 'Campaign ID not found');
                                    return;
                                }

                                // Use sbid_m value to update eBay site
                                kwUpdateBid(sbidM, rowData.kw_campaign_id, cell);
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

                            if (!campaignId || campaignId === '' || campaignId === null ||
                                campaignId === undefined) {
                                return '<span style="color: #999;">-</span>';
                            }

                            return `<div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input kw-campaign-status-toggle" 
                                       type="checkbox" 
                                       role="switch" 
                                       data-campaign-id="${campaignId}"
                                       ${isEnabled ? 'checked' : ''}
                                       style="cursor: pointer; width: 3rem; height: 1.5rem;">
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('kw-campaign-status-toggle')) {
                                e.stopPropagation();
                            }
                        },
                        width: 80
                    },


                    // {
                    //     title: "Listed",
                    //     field: "Listed",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },
                    // {
                    //     title: "Live",
                    //     field: "Live",
                    //     formatter: "tickCross",
                    //     hozAlign: "center",
                    //     editor: true,
                    //     cellClick: function(e, cell) {
                    //         var currentValue = cell.getValue();
                    //         cell.setValue(!currentValue);
                    //     },
                    //     width: 100
                    // },

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
                        formatter: function(cell) {
                            var rd = cell.getRow().getData();

                            var views = parseFloat(rd.views) || 0;
                            var sold = parseInt(rd['eBay L30'], 10) || 0;
                            var es = parseFloat(rd.suggested_bid) || 0;

                            // If no sold → use suggested bid
                            if (sold === 0) {
                                return es > 0 ? es.toFixed(2) : '-';
                            }

                            // Avoid divide by zero
                            if (views <= 0) return '-';

                            // Calculate SCVR
                            var scvr = (sold / views) * 100;

                            var sbid;

                            // Apply color-based logic
                            if (scvr <= 4) {
                                sbid = 9.1; // RED
                            } else if (scvr > 4 && scvr <= 7) {
                                sbid = 7.1; // YELLOW
                            } else if (scvr > 7 && scvr <= 10) {
                                sbid = 4.1; // GREEN
                            } else {
                                sbid = 2.1; // PINK
                            }

                            return sbid.toFixed(2);
                        },
                        width: 80
                    },
                    {
                        title: "PMT T Views",
                        field: "pmt_t_views",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var val = cell.getRow().getData().views;
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
                            var val = cell.getRow().getData().l7_views;
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
                            var views = parseFloat(rd.views) || 0;
                            var ebayL30 = parseFloat(rd['eBay L30']) || 0;
                            if (views <= 0) return '0.00%';
                            var scvr = (ebayL30 / views) * 100;
                            var color = '#6c757d';
                            if (scvr <= 4) color = 'red';
                            else if (scvr > 4 && scvr <= 7) color = '#daa520';
                            else if (scvr > 7 && scvr <= 10) color = 'green';
                            else color = '#E83E8C';
                            return '<span style="color:' + color + '; font-weight: 600;">' + scvr
                                .toFixed(2) + '%</span>';
                        },
                        width: 80
                    },
                    {
                        title: "PMT ClkL7",
                        field: "pmt_clicks_l7",
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var val = cell.getValue();
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
                            var val = cell.getValue();
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
                            // Use GPFT% (same as PMP Ads page PFT - no AD% subtraction)
                            var gpft = parseFloat(rd['GPFT%']) || 0;
                            var val = gpft / 100; // Convert to decimal
                            var color = val >= 0 ? '#198754' : '#dc3545';
                            return '<span style="color:' + color + '">' + (val * 100).toFixed(2) +
                                '%</span>';
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
                            return '<span style="color:' + color + '">' + Math.round(roi) +
                                '%</span>';
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
                            // TPFT = GPFT_decimal + (ad_updates/100) - bid_percentage (matching PMP Ads formula)
                            var gpft = parseFloat(rd['GPFT%']) || 0;
                            var adUpdates = parseFloat(rd.ad_updates) || 0;
                            var cbid = parseFloat(rd.bid_percentage) || 0;
                            var tpft = (gpft / 100) + (adUpdates / 100) - cbid;
                            var color = tpft >= 0 ? '#198754' : '#dc3545';
                            return '<span style="color:' + color + '">' + tpft.toFixed(2) +
                                '</span>';
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
                            return '<span style="color:' + color + '">' + troi.toFixed(2) +
                                '</span>';
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
                            return `<select class="form-select form-select-sm pmt-nrl-dropdown" 
                                        data-sku="${sku}" data-field="NRL"
                                        style="min-width: 70px; background-color: ${bgColor}; color: #fff; padding: 2px 4px; font-size: 11px; border: none; border-radius: 4px;">
                                    <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                    <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>NRL</option>
                                    </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 80
                    }
                ]
            });

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
                setTimeout(function() {
                    if (typeof updateSelectAllCheckbox === 'function') updateSelectAllCheckbox();
                }, 50);
            });

            // NR/REQ dropdown change handler
            $(document).on('change', '.nr-req-dropdown', function() {
                const $select = $(this);
                const value = $select.val();

                // Find the row and get SKU
                const $cell = $select.closest('.tabulator-cell');
                const row = table.getRow($cell.closest('.tabulator-row')[0]);

                if (!row) {
                    console.error('Could not find row');
                    return;
                }

                const sku = row.getData()['(Child) sku'];

                // Update the row data
                row.update({
                    nr_req: value
                });

                // Save to database using listing_ebay endpoint
                $.ajax({
                    url: '/listing_ebay/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            console.log('NR/REQ saved successfully for', sku, 'value:', value);
                            const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ?
                                'NR updated' : 'Status cleared');
                            showToast('success', message);
                        } else {
                            showToast('error', response.message || 'Failed to save status');
                        }
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr
                            .responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
                    }
                });
            });

            // KW Ads NRL dropdown change handler
            $(document).on('change', '.kw-nrl-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRL updated');
                            // Update row data
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NRL: value
                                });
                                // If NRL set to NRL, auto-set NRA to NRA
                                if (value === 'NRL') {
                                    rows[0].update({
                                        NR: 'NRA'
                                    });
                                    $.ajax({
                                        url: '/update-ebay-nr-data',
                                        method: 'POST',
                                        contentType: 'application/json',
                                        headers: {
                                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]')
                                                .attr('content')
                                        },
                                        data: JSON.stringify({
                                            sku: sku,
                                            field: 'NR',
                                            value: 'NRA'
                                        })
                                    });
                                }
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRL');
                    }
                });
            });

            // PMT NRL dropdown change handler
            $(document).on('change', '.pmt-nrl-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var value = $select.val();
                var bgColor = value === 'NRL' ? '#dc3545' : '#28a745';
                $select.css({
                    'background-color': bgColor,
                    'color': '#fff'
                });

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: 'NRL',
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRL updated');
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NRL: value
                                });
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRL');
                    }
                });
            });

            // KW Ads NRA dropdown change handler
            $(document).on('change', '.kw-nra-dropdown', function() {
                var $select = $(this);
                var sku = $select.data('sku');
                var field = $select.data('field');
                var value = $select.val();

                $.ajax({
                    url: '/update-ebay-nr-data',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({
                        sku: sku,
                        field: field,
                        value: value
                    }),
                    success: function(response) {
                        if (response.success) {
                            showToast('success', 'NRA updated');
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NR: value
                                });
                            }
                        }
                    },
                    error: function(xhr) {
                        showToast('error', 'Failed to save NRA');
                    }
                });
            });

            // KW Ads Bulk Action handler
            $(document).on('click', '.kw-bulk-action-item', function(e) {
                e.preventDefault();
                var action = $(this).data('action');

                if (selectedSkus.size === 0) {
                    showToast('warning', 'Please select at least one row');
                    return;
                }

                var selectedSkusList = Array.from(selectedSkus);
                var $btn = $('#kwBulkActionsDropdown');
                var originalText = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Processing...').prop('disabled', true);

                if (action === 'PAUSE' || action === 'ACTIVATE') {
                    // Get campaign IDs for selected SKUs
                    var campaignRequests = [];
                    selectedSkusList.forEach(function(sku) {
                        var rows = table.searchRows('(Child) sku', '=', sku);
                        if (rows.length > 0) {
                            var campaignId = rows[0].getData().kw_campaign_id;
                            if (campaignId) {
                                campaignRequests.push({
                                    sku: sku,
                                    campaignId: campaignId,
                                    row: rows[0]
                                });
                            }
                        }
                    });

                    if (campaignRequests.length === 0) {
                        showToast('warning', 'No campaigns found for selected SKUs');
                        $btn.html(originalText).prop('disabled', false);
                        return;
                    }

                    var newStatus = action === 'ACTIVATE' ? 'ENABLED' : 'PAUSED';
                    if (!confirm((action === 'PAUSE' ? 'Pause' : 'Activate') + ' ' + campaignRequests
                            .length + ' campaign(s)?')) {
                        $btn.html(originalText).prop('disabled', false);
                        return;
                    }

                    var promises = campaignRequests.map(function(req) {
                        return fetch('/toggle-ebay-campaign-status', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            body: JSON.stringify({
                                campaign_id: req.campaignId,
                                status: newStatus
                            })
                        }).then(function(res) {
                            return res.json().then(function(data) {
                                return {
                                    data: data,
                                    req: req
                                };
                            });
                        });
                    });

                    Promise.all(promises).then(function(results) {
                        var successCount = 0;
                        results.forEach(function(r) {
                            if (r.data.status === 200) {
                                successCount++;
                                var dbStatus = newStatus === 'ENABLED' ? 'RUNNING' :
                                    'PAUSED';
                                r.req.row.update({
                                    kw_campaignStatus: dbStatus
                                });
                                r.req.row.reformat();
                            }
                        });
                        var statusText = newStatus === 'ENABLED' ? 'activated' : 'paused';
                        showToast('success', successCount + ' campaign(s) ' + statusText);
                        $btn.html(originalText).prop('disabled', false);
                        // Re-apply column visibility after bulk update
                        applySectionColumnVisibility($('#section-filter').val());
                    }).catch(function(err) {
                        showToast('error', 'Error: ' + (err.message || 'Unknown error'));
                        $btn.html(originalText).prop('disabled', false);
                    });
                } else {
                    // Handle NRA/RA/LATER actions
                    if (!confirm('Mark ' + selectedSkusList.length + ' SKU(s) as ' + action + '?')) {
                        $btn.html(originalText).prop('disabled', false);
                        return;
                    }

                    var promises = selectedSkusList.map(function(sku) {
                        return fetch('/update-ebay-nr-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            body: JSON.stringify({
                                sku: sku,
                                field: 'NR',
                                value: action
                            })
                        }).then(function(res) {
                            return res.json();
                        });
                    });

                    Promise.all(promises).then(function(results) {
                        selectedSkusList.forEach(function(sku) {
                            var rows = table.searchRows('(Child) sku', '=', sku);
                            if (rows.length > 0) {
                                rows[0].update({
                                    NR: action
                                });
                                rows[0].reformat();
                            }
                        });
                        showToast('success', selectedSkusList.length + ' SKU(s) marked as ' +
                            action);
                        $btn.html(originalText).prop('disabled', false);
                        // Re-apply column visibility after bulk update
                        applySectionColumnVisibility($('#section-filter').val());
                    }).catch(function(err) {
                        showToast('error', 'Error: ' + (err.message || 'Unknown error'));
                        $btn.html(originalText).prop('disabled', false);
                    });
                }
            });

            // KW Ads - Update Bid function
            function kwUpdateBid(aprBid, campaignId, cell) {
                fetch('/update-ebay-keywords-bid-price', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            campaign_ids: [campaignId],
                            bids: [aprBid]
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 200) {
                            showToast('success', 'Keywords updated successfully!');
                            if (cell) {
                                var rowData = cell.getRow().getData();
                                rowData.kw_apprSbid = aprBid;
                                cell.getRow().update(rowData);
                                cell.getRow().reformat();
                            }
                        } else {
                            var errorMsg = data.message || "Something went wrong";
                            showToast('error', 'Error: ' + errorMsg);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showToast('error', 'Error updating bid');
                    });
            }

            // KW Ads - Campaign Status Toggle handler
            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("kw-campaign-status-toggle")) {
                    var campaignId = e.target.getAttribute("data-campaign-id");
                    var isEnabled = e.target.checked;
                    var newStatus = isEnabled ? 'ENABLED' : 'PAUSED';
                    var originalChecked = isEnabled;

                    if (!campaignId) {
                        showToast('error', 'Campaign ID not found!');
                        e.target.checked = !isEnabled;
                        return;
                    }

                    // Find the row
                    var rows = table.getRows();
                    var currentRow = null;
                    for (var i = 0; i < rows.length; i++) {
                        var rowData = rows[i].getData();
                        if (rowData.kw_campaign_id === campaignId) {
                            currentRow = rows[i];
                            break;
                        }
                    }

                    if (!currentRow) {
                        showToast('error', 'Row not found!');
                        e.target.checked = !isEnabled;
                        return;
                    }

                    fetch('/toggle-ebay-campaign-status', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                campaign_id: campaignId,
                                status: newStatus
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 200 || data.status === '200') {
                                var dbStatus = newStatus === 'ENABLED' ? 'RUNNING' : 'PAUSED';
                                currentRow.update({
                                    kw_campaignStatus: dbStatus
                                });
                                e.target.checked = (dbStatus === 'RUNNING');
                                var statusCell = currentRow.getCell('kw_campaignStatus');
                                if (statusCell) {
                                    statusCell.reformat();
                                    setTimeout(function() {
                                        var cellElement = statusCell.getElement();
                                        if (cellElement) {
                                            var newCheckbox = cellElement.querySelector(
                                                '.kw-campaign-status-toggle');
                                            if (newCheckbox) {
                                                newCheckbox.checked = (dbStatus === 'RUNNING');
                                            }
                                        }
                                    }, 10);
                                }
                                showToast('success', data.message ||
                                    'Campaign status updated successfully');
                            } else {
                                showToast('error', data.message || "Failed to update campaign status");
                                e.target.checked = !originalChecked;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            showToast('error', 'Request failed: ' + err.message);
                            e.target.checked = !originalChecked;
                        });
                }
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                // Handle SBID M cell edit
                if (field === 'kw_sbid_m') {
                    var campaignId = data.kw_campaign_id;
                    if (!campaignId) {
                        showToast('error', 'Campaign ID not found');
                        return;
                    }

                    var cleanValue = String(value).replace(/[$\s]/g, '');
                    cleanValue = parseFloat(cleanValue) || 0;

                    if (cleanValue <= 0) {
                        showToast('error', 'SBID M must be greater than 0');
                        cell.setValue('');
                        return;
                    }

                    $.ajax({
                        url: '/save-ebay-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: campaignId,
                            sbid_m: cleanValue
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                var currentData = JSON.parse(JSON.stringify(row.getData()));
                                currentData.kw_sbid_m = cleanValue;
                                currentData.kw_apprSbid = '';
                                row.update(currentData);
                                setTimeout(function() {
                                    row.reformat();
                                }, 50);
                                showToast('success', 'SBID M saved successfully');
                            } else {
                                showToast('error', response.message || 'Failed to save SBID M');
                            }
                        },
                        error: function(xhr) {
                            var errorMsg = 'Failed to save SBID M';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            } else if (xhr.status === 404) {
                                errorMsg =
                                    'Campaign not found. Please ensure the campaign exists.';
                            } else if (xhr.status === 500) {
                                errorMsg = 'Server error. Please try again.';
                            }
                            showToast('error', errorMsg);
                            console.error('SBID M save error:', xhr);
                        }
                    });
                    return;
                }

                // Validate and save ratings field (must be between 0 and 5)
                if (field === 'rating') {
                    var numValue = parseFloat(value);
                    if (isNaN(numValue) || numValue < 0 || numValue > 5) {
                        alert('Ratings must be a number between 0 and 5');
                        cell.setValue(data.rating || 0); // Revert to original value
                        return;
                    }

                    // Save rating to database
                    $.ajax({
                        url: '/update-ebay-rating',
                        method: 'POST',
                        data: {
                            sku: data['(Child) sku'],
                            rating: numValue,
                            _token: $('meta[name=\"csrf-token\"]').attr('content')
                        },
                        success: function(response) {
                            console.log('Rating saved successfully');
                            showToast('success', 'Rating updated successfully');
                            // Update the row data
                            row.update({
                                rating: numValue
                            });
                        },
                        error: function(xhr) {
                            console.error('Error saving rating:', xhr.responseText);
                            showToast('error', 'Error saving rating');
                            cell.setValue(data.rating || 0); // Revert on error
                        }
                    });
                    return;
                }

                if (field === 'SPRICE') {
                    // Save SPRICE and recalculate SPFT, SROI
                    const row = cell.getRow();
                    row.update({
                        SPRICE_STATUS: 'processing'
                    });

                    saveSpriceWithRetry(data['(Child) sku'], value, row)
                        .then((response) => {
                            showToast('SPRICE saved successfully', 'success');
                        })
                        .catch((error) => {
                            showToast('Failed to save SPRICE', 'error');
                        });
                } else if (field === 'Listed' || field === 'Live') {
                    // Save Listed/Live status
                    $.ajax({
                        url: '/update-listed-live-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            field: field,
                            value: value
                        },
                        success: function(response) {
                            showToast('success', field + ' status updated successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to update ' + field + ' status');
                        }
                    });
                }
            });

            /** eBay listing qty: API uses `eBay Stock` (column field); legacy code used `E Stock`. */
            function rowEbayStockQty(data) {
                var d = data || {};
                var v = d['eBay Stock'];
                if (v === undefined || v === null || v === '') v = d['E Stock'];
                return parseFloat(v || 0) || 0;
            }

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const el30Filter = $('#el30-filter').val();
                const nrlFilter = $('#nrl-filter').val();
                const gpftFilter = $('#gpft-filter').val();
                const roiFilter = $('#roi-filter').val();
                const cvrFilter = $('#cvr-filter').val();
                const cvrTrendFilter = $('#cvr-trend-filter').val();
                const spriceFilter = $('#sprice-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const parentSkuVal = $('#parent-sku-dropdown').val() || '';
                const viewTypeFilter = $('#view-type-filter').val() || 'all';

                table.clearFilter(true);

                // When Play is active: show only current parent group (child SKUs + parent summary row, like product-master photo)
                // Skip View and Parent/SKU dropdown so we always show both children and parent row for that group
                if (!isProductNavigationActive) {
                    // View type: All | Parent | SKU (parent = only parent rows; sku = only child SKU rows)
                    if (viewTypeFilter === 'parent') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !!isParent;
                        });
                    } else if (viewTypeFilter === 'sku') {
                        table.addFilter(function(data) {
                            var isParent = data.is_parent_summary === true ||
                                (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'));
                            return !isParent;
                        });
                    }

                    // Parent / SKU dropdown: show child SKUs for selected parent, or single row for selected SKU
                    if (parentSkuVal) {
                        if (parentSkuVal.startsWith('p:')) {
                            const parentVal = parentSkuVal.slice(2);
                            table.addFilter(function(data) {
                                return (data.Parent || '') === parentVal;
                            });
                        } else if (parentSkuVal.startsWith('s:')) {
                            const skuVal = parentSkuVal.slice(2);
                            table.addFilter(function(data) {
                                return (data['(Child) sku'] || '') === skuVal;
                            });
                        }
                    }
                }

                if (inventoryFilter === 'zero') {
                    table.addFilter(function(data) {
                        return rowEbayStockQty(data) === 0;
                    });
                } else if (inventoryFilter === 'more') {
                    table.addFilter(function(data) {
                        return rowEbayStockQty(data) > 0;
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

                if (nrlFilter !== 'all') {
                    table.addFilter(function(data) {
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
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;

                        // GPFT% is stored as a number, not a string with %
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
                        const roiVal = parseFloat(data['ROI%']) || 0;
                        if (roiFilter === 'lt40') return roiVal < 40;
                        if (roiFilter === 'gt250') return roiVal > 250;
                        const [min, max] = roiFilter.split('-').map(Number);
                        return roiVal >= min && roiVal <= max;
                    });
                }

                if (cvrFilter !== 'all') {
                    table.addFilter(function(data) {
                        // const isParent = data.Parent && data.Parent.startsWith('PARENT');
                        // if (isParent) return true;
                        // Extract CVR from SCVR field
                        const scvrValue = parseFloat(data['SCVR'] || 0);
                        const views = parseFloat(data.views || 0);
                        const l30 = parseFloat(data['eBay L30'] || 0);
                        const cvr = views > 0 ? (l30 / views) * 100 : 0;

                        // Round to 2 decimal places to avoid floating point precision issues
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

                // CVR trend filter: CVR 60 vs CVR 30 (same as Amazon)
                if (cvrTrendFilter !== 'all') {
                    const cvrTrendTol = 0.1;
                    table.addFilter(function(data) {
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
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
                        if (data.Parent && String(data.Parent).toUpperCase().startsWith('PARENT'))
                        return true;
                        const sprice = data.SPRICE;
                        if (sprice == null || sprice === '') return true;
                        const num = parseFloat(sprice);
                        return isNaN(num) || num <= 0;
                    });
                }

                // DIL filter
                if (dilFilter !== 'all') {
                    table.addFilter(function(data) {
                        const inv = parseFloat(data['INV']) || 0;
                        const l30 = parseFloat(data['L30']) || 0;
                        const dil = inv === 0 ? 0 : (l30 / inv) * 100;

                        if (dilFilter === 'red') return dil < 16.66;
                        if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                        if (dilFilter === 'green') return dil >= 25 && dil < 50;
                        if (dilFilter === 'pink') return dil >= 50;
                        return true;
                    });
                }

                // Badge Filters (E Stock > 0 — aligned with E Stock filter)
                if (zeroSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 === 0 && estock > 0;
                    });
                }

                if (moreSoldFilterActive) {
                    table.addFilter(function(data) {
                        const ebayL30 = parseFloat(data['eBay L30']) || 0;
                        const estock = rowEbayStockQty(data);
                        return ebayL30 > 0 && estock > 0;
                    });
                }

                if (missingFilterActive) {
                    table.addFilter(function(data) {
                        const itemId = data['eBay_item_id'];
                        const estock = rowEbayStockQty(data);
                        return (!itemId || itemId === null || itemId === '') && estock > 0;
                    });
                }

                // === KW Ads section filters (only apply when KW Ads is selected) ===
                if ($('#section-filter').val() === 'kw_ads') {
                    const kwUtilizationFilter = $('#kw-utilization-filter').val();
                    const kwStatusFilter = $('#kw-status-filter').val();
                    const kwNraFilter = $('#kw-nra-filter').val();
                    const kwNrlFilter = $('#kw-nrl-filter').val();
                    const kwSbidmFilter = $('#kw-sbidm-filter').val();

                    // Utilization color combination filter (7UB color + 1UB color)
                    if (kwUtilizationFilter !== 'all') {
                        table.addFilter(function(data) {
                            var hasCampaign = data.kw_campaign_id && data.kw_campaign_id !== '';
                            if (!hasCampaign) return false;

                            var budget = parseFloat(data.kw_campaignBudgetAmount) || 0;
                            if (budget <= 0) return false;

                            var l7Spend = parseFloat(data.kw_l7_spend) || 0;
                            var l1Spend = parseFloat(data.kw_l1_spend) || 0;
                            var ub7 = (l7Spend / (budget * 7)) * 100;
                            var ub1 = (l1Spend / budget) * 100;

                            // Determine colors: green=66-99, pink=>99, red=<66
                            var ub7Color = ub7 >= 66 && ub7 <= 99 ? 'green' : (ub7 > 99 ? 'pink' : 'red');
                            var ub1Color = ub1 >= 66 && ub1 <= 99 ? 'green' : (ub1 > 99 ? 'pink' : 'red');
                            var combo = ub7Color + '-' + ub1Color;

                            return combo === kwUtilizationFilter;
                        });
                    }

                    // Campaign Status filter (RUNNING / PAUSED)
                    if (kwStatusFilter !== 'all') {
                        table.addFilter(function(data) {
                            var status = data.kw_campaignStatus || '';
                            return status === kwStatusFilter;
                        });
                    }

                    // NRA filter
                    if (kwNraFilter !== 'all') {
                        table.addFilter(function(data) {
                            var rowNra = data.NR ? data.NR.trim() : '';
                            if (kwNraFilter === 'RA') {
                                // RA includes empty/null (default is RA)
                                return rowNra !== 'NRA';
                            } else {
                                // NRA or LATER - exact match
                                return rowNra === kwNraFilter;
                            }
                        });
                    }

                    // NRL filter
                    if (kwNrlFilter !== 'all') {
                        table.addFilter(function(data) {
                            var rowNrl = data.NRL ? data.NRL.trim() : '';
                            if (kwNrlFilter === 'REQ') {
                                // REQ includes empty/null (default is REQ)
                                return rowNrl !== 'NRL';
                            } else {
                                // NRL - exact match
                                return rowNrl === kwNrlFilter;
                            }
                        });
                    }

                    // SBID M filter
                    if (kwSbidmFilter !== 'all') {
                        table.addFilter(function(data) {
                            var rowSbidM = data.kw_sbid_m;
                            var isBlank = false;

                            if (rowSbidM === null || rowSbidM === undefined || rowSbidM === '') {
                                isBlank = true;
                            } else {
                                var strValue = String(rowSbidM).trim();
                                if (strValue === '' || strValue === '0' || strValue === '0.0' ||
                                    strValue === '0.00' || strValue === '0.000' || strValue === '-') {
                                    isBlank = true;
                                } else {
                                    var numValue = parseFloat(strValue);
                                    if (isNaN(numValue) || numValue === 0 || numValue <= 0) {
                                        isBlank = true;
                                    }
                                }
                            }

                            if (kwSbidmFilter === 'blank') return isBlank;
                            if (kwSbidmFilter === 'data') return !isBlank;
                            return true;
                        });
                    }

                    // KW Ads range filters (1UB%, 7UB%, LBid, Acos, Views, L7 Views)
                    var hasKwRange = Object.values(kwRangeFilters).some(f => f.min !== null || f.max !== null);
                    if (hasKwRange) {
                        table.addFilter(function(data) {
                            var budget = parseFloat(data.kw_campaignBudgetAmount) || 0;
                            var l7Spend = parseFloat(data.kw_l7_spend) || 0;
                            var l1Spend = parseFloat(data.kw_l1_spend) || 0;
                            var ub7 = budget > 0 ? (l7Spend / (budget * 7)) * 100 : 0;
                            var ub1 = budget > 0 ? (l1Spend / budget) * 100 : 0;
                            var lbid = parseFloat(data.kw_last_sbid) || 0;
                            var acos = parseFloat(data.kw_acos) || 0;
                            var views = parseFloat(data.views) || 0;
                            var l7Views = parseFloat(data.l7_views) || 0;

                            var vals = {
                                '1ub': ub1,
                                '7ub': ub7,
                                'lbid': lbid,
                                'acos': acos,
                                'views': views,
                                'l7_views': l7Views
                            };

                            for (var key in kwRangeFilters) {
                                var rf = kwRangeFilters[key];
                                if (rf.min !== null && vals[key] < rf.min) return false;
                                if (rf.max !== null && vals[key] > rf.max) return false;
                            }
                            return true;
                        });
                    }
                }

                // === PMT Ads section filters (only apply when PMT Ads is selected) ===
                if ($('#section-filter').val() === 'pmt_ads') {
                    // PMT Dropdown color filters
                    for (var pmtKey in pmtDropdownFilters) {
                        var pmtColor = pmtDropdownFilters[pmtKey];
                        if (pmtColor === 'all') continue;

                        (function(filterKey, filterColor) {
                            table.addFilter(function(data) {
                                var val, color;

                                if (filterKey === 'pmt_ov_dil') {
                                    // OV DIL% = L30 / INV * 100
                                    var inv = parseFloat(data['INV']) || 0;
                                    var l30 = parseFloat(data['L30']) || 0;
                                    val = inv === 0 ? 0 : (l30 / inv) * 100;
                                    color = val >= 50 ? 'pink' : (val >= 25 ? 'green' : (val >= 16.66 ?
                                        'yellow' : 'red'));
                                } else if (filterKey === 'pmt_e_dil') {
                                    // E Dil% = eBay L30 / INV * 100
                                    var inv2 = parseFloat(data['INV']) || 0;
                                    var eL30 = parseFloat(data['eBay L30']) || 0;
                                    val = inv2 === 0 ? 0 : (eL30 / inv2) * 100;
                                    color = val >= 50 ? 'pink' : (val >= 25 ? 'green' : (val >= 16.66 ?
                                        'yellow' : 'red'));
                                } else if (filterKey === 'pmt_clk_l7') {
                                    val = parseFloat(data['pmt_clicks_l7']) || 0;
                                    color = val > 0 ? 'green' : 'red';
                                } else if (filterKey === 'pmt_clk_l30') {
                                    val = parseFloat(data['pmt_clicks_l30']) || 0;
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
                                    // TACOS from AD% field
                                    val = parseFloat(data['AD%']) || 0;
                                    if (val <= 0) color = 'pink';
                                    else if (val > 0 && val <= 5) color = 'green';
                                    else if (val > 5 && val <= 10) color = 'blue';
                                    else if (val > 10 && val <= 20) color = 'yellow';
                                    else color = 'red';
                                } else if (filterKey === 'pmt_scvr') {
                                    var views3 = parseFloat(data.views || 0);
                                    var eL303 = parseFloat(data['eBay L30'] || 0);
                                    val = views3 > 0 ? (eL303 / views3) * 100 : 0;
                                    if (val === 0) color = 'red';
                                    else if (val > 0 && val <= 1) color = 'yellow';
                                    else if (val > 1 && val <= 3) color = 'green';
                                    else if (val > 3) color = 'pink';
                                    // 'blue' = Low SCVR (<=0.5)
                                    if (filterColor === 'blue') {
                                        return val <= 0.5;
                                    }
                                }

                                return color === filterColor;
                            });
                        })(pmtKey, pmtColor);
                    }

                    // PMT Range filters
                    var hasPmtRange = Object.values(pmtRangeFilters).some(f => f.min !== null || f.max !== null);
                    if (hasPmtRange) {
                        table.addFilter(function(data) {
                            var tViews = parseFloat(data.views) || 0;
                            var l7Views = parseFloat(data.l7_views) || 0;
                            var cbid = parseFloat(data.bid_percentage) || 0;
                            var views4 = parseFloat(data.views || 0);
                            var eL304 = parseFloat(data['eBay L30'] || 0);
                            var scvrVal = views4 > 0 ? (eL304 / views4) * 100 : 0;

                            var vals = {
                                't_views': tViews,
                                'l7_views': l7Views,
                                'cbid': cbid,
                                'scvr': scvrVal
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

                // Play / Pause: show only current parent group (child SKUs + parent summary row, like product-master photo)
                if (isProductNavigationActive && productUniqueParents.length > 0 && currentProductParentIndex >=
                    0) {
                    var currentKey = productUniqueParents[currentProductParentIndex];
                    if (currentKey) {
                        table.addFilter(function(data) {
                            var p = (data.Parent || '').toString().trim();
                            return p === currentKey || p === ('PARENT ' + currentKey);
                        });
                    }
                }

                // Update range filter badge
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Update select all checkbox after filter is applied (matching Amazon approach)
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#view-type-filter, #parent-sku-dropdown, #inventory-filter, #el30-filter, #nrl-filter, #gpft-filter, #roi-filter, #cvr-filter, #cvr-trend-filter, #sprice-filter')
                .on('change', function() {
                    applyFilters();
                });

            $('#growth-sign-filter').on('change', function() {
                applyFilters();
                if ($('#section-filter').val() === 'kw_ads') {
                    updateKwAdsStats();
                }
            });

            // KW Ads section filter change handlers
            $('#kw-utilization-filter, #kw-status-filter, #kw-nra-filter, #kw-nrl-filter, #kw-sbidm-filter').on(
                'change',
                function() {
                    applyFilters();
                    // Update KW Ads stats after filter change
                    if ($('#section-filter').val() === 'kw_ads') {
                        updateKwAdsStats();
                    }
                });

            // KW Ads range filter input handlers (debounced)
            $('#kw-range-1ub-min, #kw-range-1ub-max, #kw-range-7ub-min, #kw-range-7ub-max, #kw-range-lbid-min, #kw-range-lbid-max, #kw-range-acos-min, #kw-range-acos-max, #kw-range-views-min, #kw-range-views-max, #kw-range-l7views-min, #kw-range-l7views-max')
                .on('input change', function() {
                    if (kwRangeFilterTimeout) clearTimeout(kwRangeFilterTimeout);
                    kwRangeFilterTimeout = setTimeout(function() {
                        // Read all range inputs
                        var v;
                        v = $('#kw-range-1ub-min').val();
                        kwRangeFilters['1ub'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-1ub-max').val();
                        kwRangeFilters['1ub'].max = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-7ub-min').val();
                        kwRangeFilters['7ub'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-7ub-max').val();
                        kwRangeFilters['7ub'].max = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-lbid-min').val();
                        kwRangeFilters['lbid'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-lbid-max').val();
                        kwRangeFilters['lbid'].max = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-acos-min').val();
                        kwRangeFilters['acos'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-acos-max').val();
                        kwRangeFilters['acos'].max = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-views-min').val();
                        kwRangeFilters['views'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-views-max').val();
                        kwRangeFilters['views'].max = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-l7views-min').val();
                        kwRangeFilters['l7_views'].min = v !== '' ? parseFloat(v) : null;
                        v = $('#kw-range-l7views-max').val();
                        kwRangeFilters['l7_views'].max = v !== '' ? parseFloat(v) : null;

                        applyFilters();
                        if ($('#section-filter').val() === 'kw_ads') {
                            updateKwAdsStats();
                        }
                    }, 500);
                });

            // PMT Ads manual-dropdown-container toggle
            $(document).on('click', '.manual-dropdown-container > button', function(e) {
                e.stopPropagation();
                var container = $(this).parent();
                // Close all other open dropdowns
                $('.manual-dropdown-container').not(container).removeClass('show');
                container.toggleClass('show');
            });
            // Close manual dropdowns on outside click
            $(document).on('click', function() {
                $('.manual-dropdown-container').removeClass('show');
            });

            // PMT Ads dropdown filter click handler
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
                var labelText = button.text().replace(/^\s*/, '').trim();
                // Keep the original label (e.g., "OV DIL%", "PFT%")
                var origLabel = dropdown.find('> button').attr('data-label') || button.text().trim();
                if (!dropdown.find('> button').attr('data-label')) {
                    dropdown.find('> button').attr('data-label', button.text().trim());
                }
                button.html('').append(statusCircle).append(' ' + origLabel);

                // Update filter state
                pmtDropdownFilters[column] = color;
                dropdown.removeClass('show');
                applyFilters();
            });

            // PMT Ads range filter Apply button
            $('#pmt-apply-range-btn').on('click', function() {
                var v;
                v = $('#pmt-t-views-min').val();
                pmtRangeFilters['t_views'].min = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-t-views-max').val();
                pmtRangeFilters['t_views'].max = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-l7-views-min').val();
                pmtRangeFilters['l7_views'].min = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-l7-views-max').val();
                pmtRangeFilters['l7_views'].max = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-cbid-min').val();
                pmtRangeFilters['cbid'].min = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-cbid-max').val();
                pmtRangeFilters['cbid'].max = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-scvr-min').val();
                pmtRangeFilters['scvr'].min = v !== '' ? parseFloat(v) : null;
                v = $('#pmt-scvr-max').val();
                pmtRangeFilters['scvr'].max = v !== '' ? parseFloat(v) : null;
                applyFilters();
            });

            // PMT Ads range filter Clear button
            $('#pmt-clear-range-btn').on('click', function() {
                $('#pmt-t-views-min, #pmt-t-views-max, #pmt-l7-views-min, #pmt-l7-views-max, #pmt-cbid-min, #pmt-cbid-max, #pmt-scvr-min, #pmt-scvr-max')
                    .val('');
                pmtRangeFilters = {
                    't_views': {
                        min: null,
                        max: null
                    },
                    'l7_views': {
                        min: null,
                        max: null
                    },
                    'cbid': {
                        min: null,
                        max: null
                    },
                    'scvr': {
                        min: null,
                        max: null
                    }
                };
                // Reset dropdown filters too
                for (var k in pmtDropdownFilters) {
                    pmtDropdownFilters[k] = 'all';
                }
                // Reset dropdown button appearances
                $('.manual-dropdown-container').each(function() {
                    var btn = $(this).find('> button');
                    var origLabel = btn.attr('data-label') || btn.text().trim();
                    btn.html('<span class="status-circle default"></span> ' + origLabel);
                    $(this).find('.pmt-column-filter').removeClass('active');
                });
                applyFilters();
            });

            // KW Ads INC/DEC SBID handlers
            $('#kw-inc-dec-dropdown .dropdown-item').on('click', function(e) {
                e.preventDefault();
                kwIncDecType = $(this).data('type');
                var labelText = kwIncDecType === 'value' ? 'Value (e.g., +0.5 or -0.5)' :
                    'Percentage (e.g., +10 or -10)';
                $('#kw-inc-dec-label').text(kwIncDecType === 'value' ? 'Value' : 'Percentage');
                $('#kw-inc-dec-input').attr('placeholder', labelText);
                $('#kw-inc-dec-btn').html('<i class="fa-solid fa-plus-minus me-1"></i>' + (kwIncDecType ===
                    'value' ? 'INC/DEC (By Value)' : 'INC/DEC (By %)'));
            });

            // Helper: get base bid value for a row (L Bid -> L1 CPC -> L7 CPC fallback)
            function getKwCurrentSbid(rowData) {
                // Try L Bid (kw_last_sbid) first
                var lastSbid = rowData.kw_last_sbid;
                if (lastSbid && lastSbid !== '' && lastSbid !== '0' && lastSbid !== 0) {
                    var sbidValue = parseFloat(lastSbid);
                    if (!isNaN(sbidValue) && sbidValue > 0) return sbidValue;
                }
                // Fallback: try L1 CPC
                var l1Cpc = parseFloat(rowData.kw_l1_cpc) || 0;
                if (l1Cpc > 0) return l1Cpc;
                // Fallback: try L7 CPC
                var l7Cpc = parseFloat(rowData.kw_l7_cpc) || 0;
                if (l7Cpc > 0) return l7Cpc;
                return null;
            }

            // Apply INC/DEC
            $('#kw-apply-inc-dec-btn').on('click', function() {
                var inputValue = $('#kw-inc-dec-input').val();
                if (!inputValue || inputValue === '') {
                    alert('Please enter a value');
                    return;
                }
                var incDecValue = parseFloat(inputValue);
                if (isNaN(incDecValue)) {
                    alert('Please enter a valid number');
                    return;
                }

                // Get selected rows via selectedSkus set
                var activeRows = table.getRows('active');
                var rowsToUpdate = [];
                activeRows.forEach(function(row) {
                    var rd = row.getData();
                    var sku = rd['(Child) sku'] || '';
                    if (!selectedSkus.has(sku)) return;
                    var campaignId = rd.kw_campaign_id;
                    if (!campaignId) return;
                    var currentLbid = getKwCurrentSbid(rd);
                    if (currentLbid === null) return;
                    var newSbid = kwIncDecType === 'value' ? currentLbid + incDecValue :
                        currentLbid * (1 + incDecValue / 100);
                    if (newSbid < 0) newSbid = 0;
                    newSbid = Math.round(newSbid * 100) / 100;
                    rowsToUpdate.push({
                        row: row,
                        campaignId: campaignId,
                        newSbid: newSbid
                    });
                });

                if (rowsToUpdate.length === 0) {
                    showToast('warning', 'Please select rows with valid L Bid and campaign');
                    return;
                }

                var savePromises = [];
                rowsToUpdate.forEach(function(info) {
                    savePromises.push($.ajax({
                        url: '/save-ebay-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: info.campaignId,
                            sbid_m: info.newSbid
                        }
                    }).then(function(response) {
                        return {
                            info: info,
                            response: response,
                            success: true
                        };
                    }).catch(function(error) {
                        return {
                            info: info,
                            error: error,
                            success: false
                        };
                    }));
                });

                Promise.all(savePromises).then(function(results) {
                    var successCount = 0;
                    results.forEach(function(r) {
                        if (r.success && r.response && r.response.status === 200) {
                            successCount++;
                            var currentData = JSON.parse(JSON.stringify(r.info.row
                            .getData()));
                            currentData.kw_sbid_m = r.info.newSbid;
                            currentData.kw_apr_bid = '';
                            r.info.row.update(currentData);
                        }
                    });
                    if (successCount > 0) {
                        showToast('success', 'SBID M saved for ' + successCount + ' campaign(s)');
                        table.redraw(true);
                    }
                });
            });

            // Clear INC/DEC input
            $('#kw-clear-inc-dec-btn').on('click', function() {
                $('#kw-inc-dec-input').val('');
                showToast('success', 'Input cleared');
            });

            // Clear SBID M for selected rows
            $(document).on('click', '#kw-clear-sbid-m-btn', function() {
                var activeRows = table.getRows('active');
                var rowsToClear = [];
                activeRows.forEach(function(row) {
                    var rd = row.getData();
                    var sku = rd['(Child) sku'] || '';
                    if (!selectedSkus.has(sku)) return;
                    var campaignId = rd.kw_campaign_id;
                    if (!campaignId) return;
                    var sbidM = rd.kw_sbid_m;
                    if (!sbidM || sbidM === '' || parseFloat(sbidM) <= 0) return;
                    rowsToClear.push({
                        row: row,
                        campaignId: campaignId
                    });
                });
                if (rowsToClear.length === 0) {
                    showToast('warning', 'No selected rows with SBID M to clear');
                    return;
                }
                var clearPromises = [];
                rowsToClear.forEach(function(info) {
                    clearPromises.push($.ajax({
                        url: '/save-ebay-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: info.campaignId,
                            sbid_m: 0
                        }
                    }).then(function(response) {
                        return {
                            info: info,
                            response: response,
                            success: true
                        };
                    }).catch(function(error) {
                        return {
                            info: info,
                            error: error,
                            success: false
                        };
                    }));
                });
                Promise.all(clearPromises).then(function(results) {
                    var successCount = 0;
                    results.forEach(function(r) {
                        if (r.success && r.response && r.response.status === 200) {
                            successCount++;
                            var currentData = JSON.parse(JSON.stringify(r.info.row
                            .getData()));
                            currentData.kw_sbid_m = 0;
                            r.info.row.update(currentData);
                        }
                    });
                    if (successCount > 0) {
                        showToast('success', 'SBID M cleared for ' + successCount + ' campaign(s)');
                        table.redraw(true);
                    }
                });
            });

            // APR ALL SBID - approve all selected rows' SBID (push bid to eBay)
            $('#kw-apr-all-sbid-btn').on('click', function() {
                var activeRows = table.getRows('active');
                var campaignIds = [];
                var bids = [];
                var rowBidMap = [];
                var seenCampaignIds = new Set();

                activeRows.forEach(function(row) {
                    var rd = row.getData();
                    var sku = rd['(Child) sku'] || '';
                    if (!selectedSkus.has(sku)) return;
                    var campaignId = rd.kw_campaign_id;
                    if (!campaignId || seenCampaignIds.has(campaignId)) return;
                    // Skip NRA rows
                    var nraValue = rd.NR ? rd.NR.trim() : '';
                    if (nraValue === 'NRA') return;
                    var sbidM = parseFloat(rd.kw_sbid_m) || 0;
                    if (sbidM <= 0) return;
                    seenCampaignIds.add(campaignId);
                    campaignIds.push(campaignId);
                    bids.push(sbidM);
                    rowBidMap.push({
                        row: row,
                        campaignId: campaignId,
                        bid: sbidM
                    });
                });

                if (campaignIds.length === 0) {
                    showToast('warning', 'No selected rows with valid SBID M to approve');
                    return;
                }
                if (!confirm('Approve SBID for ' + campaignIds.length + ' campaign(s)?')) return;

                fetch('/update-ebay-keywords-bid-price', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: JSON.stringify({
                            campaign_ids: campaignIds,
                            bids: bids
                        })
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(data) {
                        if (data.status === 200) {
                            showToast('success', 'Keywords updated successfully for ' + campaignIds
                                .length + ' campaign(s)');
                            rowBidMap.forEach(function(item) {
                                var currentData = JSON.parse(JSON.stringify(item.row
                            .getData()));
                                currentData.kw_apr_bid = item.bid;
                                item.row.update(currentData);
                            });
                            table.redraw(true);
                        } else {
                            showToast('error', data.message || 'Failed to update keywords');
                        }
                    })
                    .catch(function(error) {
                        showToast('error', 'Error updating bid: ' + error.message);
                    });
            });

            // SAVE ALL SBID M - save all visible rows' calculated SBID as SBID M
            $('#kw-save-all-sbid-m-btn').on('click', function() {
                var activeRows = table.getRows('active');
                var rowsToSave = [];
                activeRows.forEach(function(row) {
                    var rd = row.getData();
                    var campaignId = rd.kw_campaign_id;
                    if (!campaignId) return;
                    var sbidCalc = parseFloat(rd.kw_sbid_calc) || 0;
                    if (sbidCalc <= 0) return;
                    rowsToSave.push({
                        row: row,
                        campaignId: campaignId,
                        sbidCalc: sbidCalc
                    });
                });
                if (rowsToSave.length === 0) {
                    showToast('warning', 'No rows with calculated SBID to save');
                    return;
                }
                if (!confirm('Save SBID M for ' + rowsToSave.length + ' campaign(s)?')) return;
                var savePromises = [];
                rowsToSave.forEach(function(info) {
                    savePromises.push($.ajax({
                        url: '/save-ebay-sbid-m',
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            campaign_id: info.campaignId,
                            sbid_m: info.sbidCalc
                        }
                    }).then(function(response) {
                        return {
                            info: info,
                            response: response,
                            success: true
                        };
                    }).catch(function(error) {
                        return {
                            info: info,
                            error: error,
                            success: false
                        };
                    }));
                });
                Promise.all(savePromises).then(function(results) {
                    var successCount = 0;
                    results.forEach(function(r) {
                        if (r.success && r.response && r.response.status === 200) {
                            successCount++;
                            var currentData = JSON.parse(JSON.stringify(r.info.row
                            .getData()));
                            currentData.kw_sbid_m = r.info.sbidCalc;
                            r.info.row.update(currentData);
                        }
                    });
                    if (successCount > 0) {
                        showToast('success', 'SBID M saved for ' + successCount + ' campaign(s)');
                        table.redraw(true);
                    }
                });
            });

            // Section Filter: show/hide column groups
            // Define column groups for each section
            // Columns that are ONLY in pricing section (will be hidden when KW Ads is selected)
            var pricingOnlyColumns = [
                'image_path', 'Missing', 'eBay Stock', 'MAP', 'nr_req', 'CVR_60', 'CVR_45', 'SCVR',
                'GPFT%', 'AD%', 'PFT %', 'ROI%',
                'lmp_price', 'SPRICE', '_accept', 'SGPFT', 'SPFT', 'SROI'
            ];
            // Columns that are ONLY in KW Ads section (will be hidden in pricing view)
            var kwAdsOnlyColumns = [
                'kw_hasCampaign', 'NRL', 'NR', 'l7_views', 'kw_cvr',
                'kw_campaignBudgetAmount', 'kw_sbgt', 'kw_acos', 'kw_clicks',
                'kw_spend_L30', 'kw_ad_sold', 'kw_l7_spend', 'kw_l1_spend', 'kw_l7_cpc', 'kw_l1_cpc',
                'kw_last_sbid', 'kw_sbid_calc', 'kw_sbid_m', 'kw_apr_bid', 'kw_campaignStatus'
            ];
            // Columns that are ONLY in PMT Ads section
            var pmtAdsOnlyColumns = [
                'pmt_cbid', 'pmt_es_bid', 'pmt_s_bid', 'pmt_t_views', 'pmt_l7_views',
                'pmt_scvr', 'pmt_clicks_l7', 'pmt_clicks_l30', 'pmt_spend_L30',
                'pmt_pft', 'pmt_roi', 'pmt_tpft', 'pmt_troi', 'pmt_nrl'
            ];
            // Columns shared between sections (shown in both pricing and KW Ads)
            var sharedColumns = [
                '_select', 'INV', 'L30', 'E Dil%', 'eBay Price', 'eBay L60', 'eBay L45', 'eBay L30',
                'growth_percent', 'views'
            ];

            // Helper: apply column visibility for a given section
            function applySectionColumnVisibility(sectionVal) {
                if (sectionVal === 'all' || sectionVal === 'pricing') {
                    kwAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    pmtAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    pricingOnlyColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                } else if (sectionVal === 'kw_ads') {
                    pricingOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    pmtAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    kwAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                } else if (sectionVal === 'pmt_ads') {
                    pricingOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    kwAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.hideColumn(col);
                        } catch (e) {}
                    });
                    pmtAdsOnlyColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                    sharedColumns.forEach(function(col) {
                        try {
                            table.showColumn(col);
                        } catch (e) {}
                    });
                }
                table.redraw(true);
            }

            $('#section-filter').on('change', function() {
                var sectionVal = $(this).val();

                applySectionColumnVisibility(sectionVal);
                // Re-show Select column when Price % (Decrease/Increase) mode is active
                if ((decreaseModeActive || increaseModeActive || samePriceModeActive) && table && table
                    .getColumn) {
                    try {
                        var selectCol = table.getColumn('_select');
                        if (selectCol) selectCol.show();
                    } catch (e) {}
                }

                if (sectionVal === 'all' || sectionVal === 'pricing') {
                    // Hide KW Ads stats & filters, show pricing filters & Summary stats
                    $('#kw-ads-stats').hide();
                    $('#kw-ads-range-section').hide();
                    $('.kw-ads-filter-item').hide();
                    $('#pmt-ads-filter-section').hide();
                    $('.pmt-ads-filter-item').hide();
                    $('#dil-filter-wrapper').show();
                    $('.pricing-filter-item').css('display', '');
                    $('#summary-stats').show();
                    applyFilters();
                } else if (sectionVal === 'kw_ads') {
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

            // Range filter event listeners (E L30, Views)
            // Update KW Ads Statistics
            function updateKwAdsStats() {
                if (typeof table === 'undefined' || !table) return;

                // Use ALL data (like utilized page), then manually apply inventory filter only
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

                // Color combination counts for utilization filter
                var comboCounts = {
                    'green-green': 0,
                    'green-pink': 0,
                    'green-red': 0,
                    'pink-green': 0,
                    'pink-pink': 0,
                    'pink-red': 0,
                    'red-green': 0,
                    'red-pink': 0,
                    'red-red': 0
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

                // Get current inventory / E L30 / Growth sign filter values (match applyFilters)
                var invFilterVal = $('#inventory-filter').val() || 'more';
                var el30FilterVal = $('#el30-filter').val() || 'all';
                var growthSignKw = $('#growth-sign-filter').val() || 'all';

                allData.forEach(function(row) {
                    if (row.is_parent_summary) return;
                    var sku = row['(Child) sku'] || '';
                    if (!sku) return;

                    var estock = rowEbayStockQty(row);
                    var ebayL30ForFilter = parseFloat(row['eBay L30'] || 0) || 0;
                    var inv = parseFloat(row.INV || 0);
                    var ebayPrice = parseFloat(row['eBay Price'] || 0);
                    var ebayItemId = row.eBay_item_id || '';
                    var hasCampaign = row.kw_campaign_id && row.kw_campaign_id !== '';
                    var nraValue = row.NR ? row.NR.trim() : '';
                    var nrlValue = row.NRL ? row.NRL.trim() : '';

                    // === Counts from ALL data (before inventory filter) ===

                    // Total SKU count (unique)
                    if (!processedSkusTotal.has(sku)) {
                        processedSkusTotal.add(sku);
                        totalSkuCount++;
                    }

                    // eBay SKU (has eBay listing - item_id exists or price > 0)
                    if (!processedSkusEbay.has(sku)) {
                        processedSkusEbay.add(sku);
                        if (ebayItemId || ebayPrice > 0) {
                            ebaySkuCount++;
                        }
                    }

                    // Zero E Stock (unique per SKU) - counted BEFORE inventory filter
                    if (estock <= 0 && !processedSkusForZeroInv.has(sku)) {
                        processedSkusForZeroInv.add(sku);
                        zeroInvCount++;
                    }

                    // === Apply E Stock filter for remaining counts ===
                    if (invFilterVal === 'zero') {
                        if (estock > 0) return;
                    } else if (invFilterVal === 'more') {
                        if (estock <= 0) return;
                    }

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

                    // NRA / RA counts (unique per SKU)
                    if (!processedSkusForNra.has(sku)) {
                        processedSkusForNra.add(sku);
                        if (nraValue === 'NRA') {
                            nraCount++;
                        } else {
                            raCount++;
                        }
                    }

                    // NRL count (unique per SKU)
                    if (!processedSkusForNrl.has(sku)) {
                        processedSkusForNrl.add(sku);
                        if (nrlValue === 'NRL') {
                            nrlCount++;
                        }
                    }

                    // Campaign / Missing counts (unique per SKU)
                    if (hasCampaign) {
                        if (!processedSkusForCampaign.has(sku)) {
                            processedSkusForCampaign.add(sku);
                            campaignCount++;

                            // UB calculations
                            var budget = parseFloat(row.kw_campaignBudgetAmount) || 0;
                            var l7Spend = parseFloat(row.kw_l7_spend) || 0;
                            var l1Spend = parseFloat(row.kw_l1_spend) || 0;
                            var ub7 = budget > 0 ? (l7Spend / (budget * 7)) * 100 : 0;
                            var ub1 = budget > 0 ? (l1Spend / budget) * 100 : 0;

                            if (ub7 >= 66 && ub7 <= 99) ub7Count++;
                            if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) ub7Ub1Count++;

                            // Color combination count (unique per SKU)
                            if (!processedSkusForCombo.has(sku) && budget > 0) {
                                processedSkusForCombo.add(sku);
                                var ub7Color = ub7 >= 66 && ub7 <= 99 ? 'green' : (ub7 > 99 ? 'pink' :
                                    'red');
                                var ub1Color = ub1 >= 66 && ub1 <= 99 ? 'green' : (ub1 > 99 ? 'pink' :
                                    'red');
                                var combo = ub7Color + '-' + ub1Color;
                                if (comboCounts.hasOwnProperty(combo)) {
                                    comboCounts[combo]++;
                                }
                            }

                            // Paused campaigns
                            var status = row.kw_campaignStatus || '';
                            if (status === 'PAUSED') pausedCount++;

                            // L30 totals
                            totalClicks += parseInt(row.kw_clicks || 0);
                            totalSpend += parseFloat(row.kw_spend_L30 || 0);
                            totalAdSold += parseInt(row.kw_ad_sold || 0);

                            // ACOS average (only for items with spend)
                            var kwSpend = parseFloat(row.kw_spend_L30 || 0);
                            if (kwSpend > 0) {
                                var acos = parseFloat(row.kw_acos || 0);
                                totalAcos += acos;
                                acosItems++;
                            }

                            // CVR average (only for items with clicks)
                            var clicks = parseInt(row.kw_clicks || 0);
                            if (clicks > 0) {
                                var cvr = parseFloat(row.kw_cvr || 0);
                                totalCvr += cvr;
                                cvrItems++;
                            }
                        }
                    } else {
                        // Missing campaign (unique per SKU)
                        if (!processedSkusForMissing.has(sku)) {
                            processedSkusForMissing.add(sku);
                            // Check if NRL or NRA = yellow dot (not red missing)
                            if (nrlValue !== 'NRL' && nraValue !== 'NRA') {
                                // Red dot - truly missing
                                missingCount++;
                            } else {
                                // Yellow dot - NRL/NRA missing
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
                    'green-green': 'Green + Green',
                    'green-pink': 'Green + Pink',
                    'green-red': 'Green + Red',
                    'pink-green': 'Pink + Green',
                    'pink-pink': 'Pink + Pink',
                    'pink-red': 'Pink + Red',
                    'red-green': 'Red + Green',
                    'red-pink': 'Red + Pink',
                    'red-red': 'Red + Red'
                };
                $('#kw-utilization-filter option').each(function() {
                    var val = $(this).val();
                    if (val !== 'all' && comboLabels[val] !== undefined) {
                        $(this).text(comboLabels[val] + ' (' + comboCounts[val] + ')');
                    }
                });
            }

            // Update PFT% and ROI% calc values
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let sumLp = 0;

                data.forEach(row => {
                    const profit = parseFloat(row['Total_pft']) || 0;
                    const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                    // Only add if both values are > 0 (matching inc/dec page logic)
                    if (profit > 0 && salesL30 > 0) {
                        totalProfit += profit;
                        totalSales += salesL30;
                    }
                    sumLp += parseFloat(row['LP_productmaster']) || 0;
                });

                // PFT% and ROI% calculations removed - display elements removed
                // const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // const avgRoi = sumLp > 0 ? (totalProfit / sumLp) * 100 : 0;
            }

            // Update summary badges - use ALL data (not filtered) to match KW/PMP ads pages
            function updateSummary() {
                if (!table) return;
                // Use getData("all") to get ALL data without filters
                const allData = table.getData("all");
                const filteredData = table.getData("active");

                // Filtered data metrics (for other badges)
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;
                let zeroSoldCount = 0;
                let moreSoldCount = 0;
                let missingCount = 0;
                filteredData.forEach(row => {
                    const estock = rowEbayStockQty(row);
                    const ebayL30 = parseFloat(row['eBay L30'] || 0);

                    if (estock > 0) {
                        totalPftAmt += parseFloat(row['Total_pft'] || 0);
                        totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                        totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * ebayL30;
                        totalFbaL30 += ebayL30;

                        // Count 0 Sold and > 0 Sold (only E Stock > 0)
                        if (ebayL30 === 0) {
                            zeroSoldCount++;
                        } else {
                            moreSoldCount++;
                        }

                        const dil = parseFloat(row['E Dil%'] || 0);
                        if (!isNaN(dil)) {
                            totalDilPercent += dil;
                            dilCount++;
                        }

                        // Count Missing (only E Stock > 0)
                        const itemId = row['eBay_item_id'];
                        if (!itemId || itemId === null || itemId === '') {
                            missingCount++;
                        }

                    }
                });

                let totalWeightedPrice = 0;
                let totalL30 = 0;
                filteredData.forEach(row => {
                    if (rowEbayStockQty(row) > 0) {
                        const price = parseFloat(row['eBay Price'] || 0);
                        const l30 = parseFloat(row['eBay L30'] || 0);
                        totalWeightedPrice += price * l30;
                        totalL30 += l30;
                    }
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;

                let totalViews = 0;
                filteredData.forEach(row => {
                    if (rowEbayStockQty(row) > 0) {
                        totalViews += parseFloat(row.views || 0);
                    }
                });
                const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;

                // TACOS badge = channel Ads% (all-marketplace-master), same as Ebay 2/3 and AD% column
                const tacosPercent = EBAY_CHANNEL_ADS_PCT;

                // GROI% = (Total PFT / Total COGS) * 100
                const groiPercent = totalLpAmt > 0 ? ((totalPftAmt / totalLpAmt) * 100) : 0;

                // GPFT% = (Total PFT / Total Sales) * 100
                const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;

                // NPFT% = GPFT% - TACOS%
                const npftPercent = avgGpft - tacosPercent;

                // NROI% = GROI% - TACOS%
                const nroiPercent = groiPercent - tacosPercent;

                $('#total-sales-amt-badge').text('Sales: $' + Math.round(totalSalesAmt).toLocaleString());

                $('#avg-gpft-badge').text('GPFT: ' + Math.round(avgGpft) + '%');
                $('#avg-pft-badge').text('NPFT: ' + Math.round(npftPercent) + '%');
                $('#groi-percent-badge').text('GROI: ' + Math.round(groiPercent) + '%');
                $('#nroi-percent-badge').text('NROI: ' + Math.round(nroiPercent) + '%');
                $('#tacos-percent-badge').text('TACOS: ' + tacosPercent.toFixed(1) + '%');

                $('#avg-price-badge').text('Price: $' + avgPrice.toFixed(2));
                $('#avg-cvr-badge').text('CVR: ' + Math.round(avgCVR) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());

                $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
                $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());

                $('#missing-count-badge').text('Missing: ' + missingCount.toLocaleString());
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                if (!menu) return;
                menu.innerHTML = '';

                fetch('/ebay-column-visibility', {
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

                fetch('/ebay-column-visibility', {
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
                fetch('/ebay-column-visibility', {
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
                applySectionColumnVisibility($('#section-filter').val() || 'all');
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                applyFilters();

                // Set up periodic background retry check (every 30 seconds)
                setInterval(() => {
                    backgroundRetryFailedSkus();
                }, 30000);
            });

            table.on('dataLoaded', function() {
                // Populate Parent / SKU dropdown: unique parents and all SKUs (show child SKUs on select)
                var allRows = table.getData('all') || [];
                var parents = [];
                var seenParent = {};
                allRows.forEach(function(r) {
                    var p = r.Parent || '';
                    if (p && String(p).trim() !== '' && !String(p).toUpperCase().startsWith(
                            'PARENT') && !seenParent[p]) {
                        seenParent[p] = true;
                        parents.push(p);
                    }
                });
                parents.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                // Use same parent list for Play/Next/Previous (single parent SKUs like product-master)
                productUniqueParents = parents.slice(0);
                var skus = allRows.map(function(r) {
                    return r['(Child) sku'] || '';
                }).filter(function(s) {
                    return s !== '';
                });
                skus.sort(function(a, b) {
                    return String(a).localeCompare(String(b));
                });
                var $dropdown = $('#parent-sku-dropdown');
                $dropdown.find('option:not(:first)').remove();
                if (parents.length > 0) {
                    var $pg = $('<optgroup label="Parents (show child SKUs)">');
                    parents.forEach(function(p) {
                        $pg.append($('<option>').attr('value', 'p:' + p).text(p));
                    });
                    $dropdown.append($pg);
                }
                if (skus.length > 0) {
                    var $sg = $('<optgroup label="SKUs">');
                    skus.forEach(function(s) {
                        $sg.append($('<option>').attr('value', 's:' + s).text(s));
                    });
                    $dropdown.append($sg);
                }
                updateCalcValues();
                if (typeof updateSummary === 'function') updateSummary();
                // Update KW Ads stats if that section is active
                if ($('#section-filter').val() === 'kw_ads') {
                    updateKwAdsStats();
                }
                // Refresh checkboxes to reflect selectedSkus set (matching Amazon approach)
                setTimeout(function() {
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
                // Redraw so rowFormatter runs and parent rows get light blue background
                setTimeout(function() {
                    table.redraw(true);
                }, 50);
                // Play / Pause parent navigation (same as product-master)
                initProductPlaybackControls();
            });

            // Also initialize tooltips when table is rendered (matching Amazon approach)
            table.on('renderComplete', function() {
                setTimeout(function() {
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    // Initialize Bootstrap tooltips for dynamically created elements
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll(
                        '[data-bs-toggle="tooltip"]'));
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
                            new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    }
                }, 100);
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

            document.addEventListener("click", function(e) {
                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn")) {
                    const sku = e.target.getAttribute("data-sku");

                    // Copy to clipboard
                    navigator.clipboard.writeText(sku).then(function() {
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    }).catch(function(err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = sku;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    });
                }

                // View SKU chart
                if (e.target.closest('.view-sku-chart')) {
                    e.preventDefault();
                    e.stopPropagation();
                    const sku = e.target.closest('.view-sku-chart').getAttribute('data-sku');
                    currentSku = sku;
                    $('#modalSkuName').text(sku);
                    $('#sku-chart-days-filter').val('30');
                    $('#chart-no-data-message').hide();
                    loadSkuMetricsData(sku, 30);
                    $('#skuMetricsModal').modal('show');
                }
            });

            // Toast notification
            // Export column mapping (field -> display name)
            const exportColumnMapping = {
                'Parent': 'Parent',
                '(Child) sku': 'SKU',
                'INV': 'INV',
                'L30': 'L30',
                'E Dil%': 'Dil%',
                'eBay L30': 'eBay L30',
                'eBay L45': 'eBay L45',
                'eBay L60': 'eBay L60',
                'growth_percent': 'Growth',
                'eBay Stock': 'eBay Stock',
                'Missing': 'Missing',
                'MAP': 'MAP',
                'eBay Price': 'eBay Price',
                'lmp_price': 'LMP',
                'AD_Sales_L30': 'AD Sales L30',
                'AD_Units_L30': 'AD Units L30',
                'AD%': 'AD%',
                'TacosL30': 'TACOS L30',
                'T_Sale_l30': 'Total Sales L30',
                'Total_pft': 'Total Profit',
                'PFT %': 'PFT %',
                'ROI%': 'GROI%',
                'GPFT%': 'GPFT%',
                'views': 'Views',
                'nr_req': 'NR/REQ',
                'SPRICE': 'SPRICE',
                'SPFT': 'SPFT',
                'SROI': 'SROI',
                'SGPFT': 'SGPFT',
                'Listed': 'Listed',
                'Live': 'Live',
                'SCVR': 'CVR 30',
                'CVR_45': 'CVR 45',
                'CVR_60': 'CVR 60',
                'kw_spend_L30': 'KW Spend L30',
                'pmt_spend_L30': 'PMT Spend L30',
                'ebay2_ship': 'eBay2 Ship',
                'LP_productmaster': 'LP'
            };

            // Build export columns list
            function buildExportColumnsList() {
                const container = document.getElementById('export-columns-list');
                container.innerHTML = '';

                const columns = table.getColumns().filter(col => {
                    const field = col.getField();
                    return field && exportColumnMapping[field] && field !== '_select' && field !==
                    '_accept';
                });

                columns.forEach(col => {
                    const field = col.getField();
                    const displayName = exportColumnMapping[field];

                    const div = document.createElement('div');
                    div.className = 'form-check mb-2';
                    div.innerHTML = `
                        <input class="form-check-input export-column-checkbox" type="checkbox" 
                               value="${field}" id="export-col-${field}" checked>
                        <label class="form-check-label" for="export-col-${field}">
                            ${displayName}
                        </label>
                    `;
                    container.appendChild(div);
                });
            }

            // Select all export columns
            $('#select-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', true);
            });

            // Deselect all export columns
            $('#deselect-all-export-columns').on('click', function() {
                $('.export-column-checkbox').prop('checked', false);
            });

            // Confirm export
            $('#confirm-export-btn').on('click', function() {
                const selectedColumns = [];
                $('.export-column-checkbox:checked').each(function() {
                    selectedColumns.push($(this).val());
                });

                if (selectedColumns.length === 0) {
                    showToast('error', 'Please select at least one column to export');
                    return;
                }

                // Build export URL with selected columns
                const columnsParam = encodeURIComponent(JSON.stringify(selectedColumns));
                const exportUrl = `/ebay-export?columns=${columnsParam}`;

                // Close modal and trigger download
                $('#exportModal').modal('hide');
                window.location.href = exportUrl;
            });

            // When export modal is shown, build the columns list
            $('#exportModal').on('show.bs.modal', function() {
                if (table) {
                    buildExportColumnsList();
                }
            });

            // Import Ratings Modal Handler
            $('#importForm').on('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                const file = $('#csvFile')[0].files[0];

                if (!file) {
                    showToast('error', 'Please select a CSV file');
                    return;
                }

                formData.append('file', file);
                formData.append('_token', '{{ csrf_token() }}');

                const uploadBtn = $('#uploadBtn');
                uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                $.ajax({
                    url: '/import-ebay-ratings',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
                        $('#importModal').modal('hide');
                        $('#csvFile').val('');
                        showToast('success', response.success ||
                            'Ratings imported successfully');

                        // Reload table data
                        setTimeout(() => {
                            table.setData(EBAY_DATA_JSON_URL);
                        }, 1000);
                    },
                    error: function(xhr) {
                        uploadBtn.prop('disabled', false).html(
                            '<i class="fa fa-upload"></i> Import');
                        const errorMsg = xhr.responseJSON?.error || 'Failed to import ratings';
                        showToast('error', errorMsg);
                    }
                });
            });
        });

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
                data: {
                    sku: sku
                },
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
                        <td>
                            <code>${item.item_id}</code>
                        </td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                        <td><strong>$${parseFloat(item.total_price).toFixed(2)}</strong> ${badge}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${item.title || 'N/A'}
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="${productLink}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay">
                                    <i class="fa fa-external-link"></i>
                                </a>
                                <button class="btn btn-sm btn-danger delete-ebay-lmp-btn" 
                                    data-id="${item.id}" 
                                    data-item-id="${item.item_id}" 
                                    data-price="${item.total_price}"
                                    title="Delete this competitor">
                                    <i class="fa fa-trash"></i>
                                </button>
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
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
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

                        // Clear form
                        $('#addCompItemId').val('');
                        $('#addCompPrice').val('');
                        $('#addCompShipping').val('');
                        $('#addCompLink').val('');
                        $('#addCompTitle').val('');

                        // Reload competitors list
                        const sku = $('#addCompSku').val();
                        loadEbayCompetitorsModal(sku);

                        // Reload main table data
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to add competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to add competitor';
                    showToast(errorMsg, 'error');
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

            if (!confirm(`Delete competitor ${itemId} ($${price})?`)) {
                return;
            }

            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: '/ebay-lmp-delete',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    id: id
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Competitor deleted successfully', 'success');

                        // Reload competitors list
                        const sku = currentLmpData.sku;
                        loadEbayCompetitorsModal(sku);

                        // Reload main table data
                        table.replaceData();
                    } else {
                        showToast(response.error || 'Failed to delete competitor', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Failed to delete competitor';
                    showToast(errorMsg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });


        // Tooltip functions for eBay links
        function showEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '1';
                tooltip.style.visibility = 'visible';
            }
        }

        function hideEbayTooltip(element) {
            const tooltip = element.nextElementSibling;
            if (tooltip && tooltip.classList.contains('link-tooltip')) {
                tooltip.style.opacity = '0';
                tooltip.style.visibility = 'hidden';
            }
        }
    </script>
@endsection
