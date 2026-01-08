@extends('layouts.vertical', ['title' => 'Temu Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
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

        /* eBay-style color coding */
        .dil-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .dil-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .dil-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .dil-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .dil-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .dil-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
        }

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu Pricing',
        'sub_title' => 'Temu Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu Pricing</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Inventory Filter -->
                    <div>
                        <select id="inventory-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Inventory</option>
                            <option value="gt0" selected>INV &gt; 0</option>
                            <option value="eq0" >INV = 0</option>
                        </select>
                    </div>

                    <!-- GPFT Filter -->
                    <div>
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50-60">50-60%</option>
                            <option value="60plus">60%+</option>
                        </select>
                    </div>

                    <!-- CVR Filter -->
                    <div>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0.01-1">0.01-1%</option>
                            <option value="1-2">1-2%</option>
                            <option value="2-3">2-3%</option>
                            <option value="3-4">3-4%</option>
                            <option value="0-4">0-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-10">7-10%</option>
                            <option value="10plus">10%+</option>
                        </select>
                    </div>

                    <!-- DIL Filter -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" id="dilFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="all">
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

                    <!-- ADS Filter -->
                    <div>
                        <select id="ads-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All ADS%</option>
                            <option value="0-10">Below 10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-100">30-100%</option>
                            <option value="100plus">100%+</option>
                        </select>
                    </div>

                    <!-- SPRICE Filter -->
                    <div>
                        <select id="sprice-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All SPRICE</option>
                            <option value="27-31">$27-$31</option>
                            <option value="lt27">&lt; $27</option>
                            <option value="gt31">&gt; $31</option>
                        </select>
                    </div>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export CSV
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadViewDataModal">
                        <i class="fa fa-eye"></i> Upload View Data
                    </button>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadAdDataModal">
                        <i class="fa fa-chart-line"></i> Upload Ad Data
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#uploadRPricingModal">
                        <i class="fa fa-tags"></i> Upload R Pricing
                    </button>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#uploadPricingModal">
                        <i class="fa fa-dollar-sign"></i> Upload Pricing
                    </button>
                    
                    <button type="button" id="clear-sprice-btn" class="btn btn-sm btn-danger">
                        <i class="fa fa-trash"></i> Clear SPRICE
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Basic Counts -->
                        <span class="badge bg-primary fs-6 p-2" id="total-products-badge" style="color: black; font-weight: bold;">Total Products: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: black; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: black; font-weight: bold;">0 Sold Count: 0</span>
                        
                        <!-- Pricing & Performance -->
                        <span class="badge bg-info fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.0%</span>
                        
                        <!-- Financial Totals -->
                        <span class="badge bg-success fs-6 p-2" id="total-revenue-badge" style="color: black; font-weight: bold;">Total Revenue: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-profit-badge" style="color: black; font-weight: bold;">Total Profit: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-lp-badge" style="color: black; font-weight: bold;">Total LP: $0</span>
                        
                        <!-- Percentages (Gross) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-gprft-badge" style="color: black; font-weight: bold;">Avg GPRFT%: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-groi-badge" style="color: black; font-weight: bold;">Avg GROI%: 0%</span>
                        
                        <!-- Advertising Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Total Spend: $0.00</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-ads-badge" style="color: black; font-weight: bold;">Ads %: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Total TCOS: 0%</span>
                        
                        <!-- Percentages (Net) -->
                        <span class="badge bg-success fs-6 p-2" id="avg-npft-badge" style="color: black; font-weight: bold;">Avg NPFT%: 0%</span>
                        <span class="badge bg-primary fs-6 p-2" id="avg-nroi-badge" style="color: black; font-weight: bold;">Avg NROI%: 0%</span>
                        
                        <!-- Engagement -->
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Total Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-temu-l30-badge" style="color: black; font-weight: bold;">Total Temu L30: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="badge bg-primary">0 SKUs selected</span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                               placeholder="Enter %" style="width: 150px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply 
                        </button>
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-amazon"></i> Suggest Amazon Price
                        </button>
                        <button id="sugg-r-prc-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-tag"></i> Suggest R Price
                        </button>
                        <button id="sprc-26-99-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-dollar-sign"></i> SPRC 26.99
                        </button>
                    </div>
                </div>
                <div id="temu-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="temu-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload View Data Modal -->
    <div class="modal fade" id="uploadViewDataModal" tabindex="-1" aria-labelledby="uploadViewDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadViewDataModalLabel">
                        <i class="fa fa-eye me-2"></i>Upload Temu View Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadViewDataForm" action="{{ route('temu.viewdata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="viewDataFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="viewDataFile" name="file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-lightbulb me-2"></i>
                            <strong>Note:</strong> This will INSERT new records only (no truncate/update).
                            <a href="{{ route('temu.viewdata.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadViewDataForm" class="btn btn-success">
                        <i class="fa fa-upload me-1"></i>Upload View Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Ad Data Modal -->
    <div class="modal fade" id="uploadAdDataModal" tabindex="-1" aria-labelledby="uploadAdDataModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="uploadAdDataModalLabel">
                        <i class="fa fa-chart-line me-2"></i>Upload Temu Ad Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadAdDataForm" action="{{ route('temu.addata.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="adDataFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="adDataFile" name="ad_data_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading new data!
                            <br>
                            <a href="{{ route('temu.addata.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadAdDataForm" class="btn btn-warning">
                        <i class="fa fa-upload me-1"></i>Upload Ad Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload R Pricing Modal -->
    <div class="modal fade" id="uploadRPricingModal" tabindex="-1" aria-labelledby="uploadRPricingModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="uploadRPricingModalLabel">
                        <i class="fa fa-tags me-2"></i>Upload Temu R Pricing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadRPricingForm" action="{{ route('temu.rpricing.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="rPricingFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" id="rPricingFile" name="r_pricing_file" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading new data!
                            <br>
                            <a href="{{ route('temu.rpricing.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadRPricingForm" class="btn btn-danger">
                        <i class="fa fa-upload me-1"></i>Upload R Pricing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Pricing Modal -->
    <div class="modal fade" id="uploadPricingModal" tabindex="-1" aria-labelledby="uploadPricingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="uploadPricingModalLabel">
                        <i class="fa fa-dollar-sign me-2"></i>Upload Temu Pricing Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif
                    
                    <form id="uploadPricingForm" method="POST" action="{{ route('temu.pricing.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="pricingFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Choose Excel File
                            </label>
                            <input type="file" class="form-control" name="pricing_file" id="pricingFile" accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle text-info me-1"></i>
                                Accepts .xlsx, .xls, or .csv files (Max: 10MB)
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-lightbulb me-2"></i>
                            <strong>Note:</strong> This will update pricing data.
                            <br>
                            <a href="{{ route('temu.pricing.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadPricingForm" class="btn btn-info">
                        <i class="fa fa-upload me-1"></i>Upload Pricing
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SKU Metrics Chart Modal -->
    <div class="modal fade" id="skuMetricsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-chart-line me-2"></i>Metrics Chart for <span id="modalSkuName" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center">
                        <div>
                            <label class="form-label fw-bold mb-0 me-2">Date Range:</label>
                            <select id="sku-chart-days-filter" class="form-select form-select-sm d-inline-block" style="width: auto;">
                                <option value="7" selected>Last 7 Days</option>
                                <option value="14">Last 14 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="60">Last 60 Days</option>
                            </select>
                        </div>
                        <div class="text-muted">
                            <small><i class="fa fa-info-circle"></i> Hover over data points for detailed information</small>
                        </div>
                    </div>
                    <div id="chart-no-data-message" class="alert alert-warning" style="display: none;">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>No Data Available:</strong> No historical data available for this SKU. Data will appear after running the metrics collection command.
                    </div>
                    <div style="height: 500px; position: relative;">
                        <canvas id="skuMetricsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "temu_decrease_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();
    
    // SKU-specific chart
    let skuMetricsChart = null;
    let currentSku = null;

    function initSkuMetricsChart() {
        const ctx = document.getElementById('skuMetricsChart').getContext('2d');
        
        // Register datalabels plugin
        Chart.register(ChartDataLabels);
        
        skuMetricsChart = new Chart(ctx, {
            type: 'line',
            plugins: [ChartDataLabels],
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Price (USD)',
                        data: [],
                        borderColor: '#FF0000',
                        backgroundColor: 'rgba(255, 0, 0, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FF0000',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y',
                        tension: 0.1,
                        fill: false,
                        spanGaps: true,
                        datalabels: {
                            display: true,
                            align: function(context) {
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 'top' : 'bottom';
                            },
                            anchor: 'center',
                            offset: 10,
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#FF0000',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? '$' + value.toFixed(2) : '';
                            }
                        }
                    },
                    {
                        label: 'Views',
                        data: [],
                        borderColor: '#0000FF',
                        backgroundColor: 'rgba(0, 0, 255, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0000FF',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y2',
                        tension: 0.1,
                        fill: false,
                        spanGaps: true,
                        datalabels: {
                            display: true,
                            align: function(context) {
                                const index = context.dataIndex;
                                return index % 2 === 0 ? 'left' : 'right';
                            },
                            anchor: 'center',
                            offset: 10,
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#0000FF',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? value.toFixed(0) : '';
                            }
                        }
                    },
                    {
                        label: 'CVR%',
                        data: [],
                        borderColor: '#00FF00',
                        backgroundColor: 'rgba(0, 255, 0, 0.2)',
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#00FF00',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        yAxisID: 'y1',
                        tension: 0.1,
                        fill: false,
                        spanGaps: true,
                        datalabels: {
                            display: true,
                            align: function(context) {
                                const index = context.dataIndex;
                                const total = context.dataset.data.length;
                                if (index === total - 1) return 'left';
                                return index % 2 === 0 ? 'bottom' : 'top';
                            },
                            anchor: 'center',
                            offset: 10,
                            clamp: true,
                            color: '#FFFFFF',
                            backgroundColor: '#00FF00',
                            borderRadius: 3,
                            padding: { top: 1, bottom: 1, left: 3, right: 3 },
                            font: {
                                weight: 'bold',
                                size: 8
                            },
                            formatter: function(value) {
                                return value ? value.toFixed(1) + '%' : '';
                            }
                        }
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 20,
                        right: 40,
                        top: 30,
                        bottom: 20
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Temu SKU Metrics',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    },
                    subtitle: {
                        display: true,
                        text: 'Price (Left) | CVR% (Right) | Views (Labels Only)',
                        font: {
                            size: 12
                        },
                        color: '#666'
                    },
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    if (context.dataset.label === 'Price (USD)') {
                                        label += '$' + context.parsed.y.toFixed(2);
                                    } else if (context.dataset.label === 'CVR%') {
                                        label += context.parsed.y.toFixed(1) + '%';
                                    } else {
                                        label += context.parsed.y.toFixed(0);
                                    }
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Price (USD)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            color: '#FF0000'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            },
                            color: '#FF0000',
                            font: {
                                size: 10,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            color: 'rgba(255, 0, 0, 0.1)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'CVR %',
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            color: '#00FF00'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(1) + '%';
                            },
                            color: '#00FF00',
                            font: {
                                size: 10,
                                weight: 'bold'
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    },
                    y2: {
                        type: 'linear',
                        display: false,
                        position: 'right',
                        beginAtZero: false,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    function loadSkuMetricsData(sku, days = 7) {
        fetch(`/temu-metrics-history?days=${days}&sku=${encodeURIComponent(sku)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (skuMetricsChart) {
                    if (!data || data.length === 0) {
                        $('#chart-no-data-message').show();
                        skuMetricsChart.data.labels = [];
                        skuMetricsChart.data.datasets.forEach(dataset => {
                            dataset.data = [];
                        });
                        skuMetricsChart.options.plugins.title.text = 'Temu SKU Metrics';
                        skuMetricsChart.update();
                        return;
                    }
                    
                    $('#chart-no-data-message').hide();
                    
                    skuMetricsChart.options.plugins.title.text = `Temu Metrics (${days} Days)`;
                    
                    skuMetricsChart.data.labels = data.map(d => d.date_formatted || d.date || '');
                    
                    const priceData = data.map(d => d.price || null);
                    const viewsData = data.map(d => d.views || null);
                    const cvrData = data.map(d => {
                        const cvr = d.cvr_percent;
                        return (cvr && cvr > 0) ? cvr : null;
                    });
                    
                    skuMetricsChart.data.datasets[0].data = priceData;
                    skuMetricsChart.data.datasets[1].data = viewsData;
                    skuMetricsChart.data.datasets[2].data = cvrData;
                    
                    const priceMin = Math.min(...priceData.filter(p => p != null && p > 0));
                    const priceMax = Math.max(...priceData.filter(p => p != null));
                    const viewsMin = Math.min(...viewsData.filter(v => v != null && v > 0));
                    const viewsMax = Math.max(...viewsData.filter(v => v != null));
                    const cvrMin = Math.min(...cvrData.filter(c => c != null && c > 0));
                    const cvrMax = Math.max(...cvrData.filter(c => c != null && c > 0));
                    
                    const yMin = priceMin * 0.97;
                    const yMax = priceMax * 1.03;
                    const y2Min = viewsMin * 0.97;
                    const y2Max = viewsMax * 1.03;
                    const y1Min = cvrMin > 0 ? cvrMin * 0.95 : 0;
                    const y1Max = cvrMax * 1.05;
                    
                    skuMetricsChart.options.scales.y.min = yMin;
                    skuMetricsChart.options.scales.y.max = yMax;
                    skuMetricsChart.options.scales.y2.min = y2Min;
                    skuMetricsChart.options.scales.y2.max = y2Max;
                    skuMetricsChart.options.scales.y1.min = y1Min;
                    skuMetricsChart.options.scales.y1.max = y1Max;
                    
                    skuMetricsChart.update('active');
                }
            })
            .catch(error => {
                alert('Error loading metrics data. Please check console for details.');
            });
    }
    
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

    $(document).ready(function() {
        // Initialize SKU-specific chart
        initSkuMetricsChart();

        // SKU chart days filter
        $('#sku-chart-days-filter').on('change', function() {
            const days = $(this).val();
            if (currentSku) {
                if (skuMetricsChart) {
                    skuMetricsChart.options.plugins.title.text = `Temu Metrics (${days} Days)`;
                    skuMetricsChart.update();
                }
                loadSkuMetricsData(currentSku, days);
            }
        });

        // Event delegation for chart button clicks
        $(document).on('click', '.view-sku-chart', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            currentSku = sku;
            $('#modalSkuName').text(sku);
            $('#sku-chart-days-filter').val('7');
            $('#chart-no-data-message').hide();
            loadSkuMetricsData(sku, 7);
            $('#skuMetricsModal').modal('show');
        });

        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() {
            const discountType = $(this).val();
            const $input = $('#discount-percentage-input');
            
            if (discountType === 'percentage') {
                $input.attr('placeholder', 'Enter %');
            } else {
                $input.attr('placeholder', 'Enter $');
            }
        });

        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            
            if (decreaseModeActive) {
                selectColumn.show();
                $(this).removeClass('btn-warning').addClass('btn-danger');
                $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                $('#increase-btn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
            } else {
                selectColumn.hide();
                $(this).removeClass('btn-danger').addClass('btn-warning');
                $(this).html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                selectedSkus.clear();
                updateSelectedCount();
                updateSelectAllCheckbox();
            }
        });
        
        // Increase Mode Toggle
        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            
            if (increaseModeActive) {
                selectColumn.show();
                $(this).removeClass('btn-success').addClass('btn-danger');
                $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
            } else {
                selectColumn.hide();
                selectedSkus.clear();
                $(this).removeClass('btn-danger').addClass('btn-success');
                $(this).html('<i class="fas fa-arrow-up"></i> Increase Mode');
                updateSelectedCount();
                updateSelectAllCheckbox();
            }
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active');
            
            filteredData.forEach(row => {
                const sku = row['sku'];
                if (sku) {
                    if (isChecked) {
                        selectedSkus.add(sku);
                    } else {
                        selectedSkus.delete(sku);
                    }
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

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

        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        $('#sugg-r-prc-btn').on('click', function() {
            applySuggestRPrice();
        });

        $('#clear-sprice-btn').on('click', function() {
            if (confirm('Are you sure you want to clear all SPRICE data? This action cannot be undone.')) {
                clearAllSprice();
            }
        });

        $('#sprc-26-99-btn').on('click', function() {
            applySprice2699();
        });

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active');
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                if (row) {
                    row.update({ sprice_status: 'processing' });
                }
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (row) {
                            row.update({
                                sprice: sprice,
                                sgprft_percent: response.sgprft_percent,
                                sroi_percent: response.sroi_percent,
                                sprice_status: 'saved'
                            });
                            row.reformat();
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        
                        if (retryCount < 1) {
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            if (row) {
                                row.update({ sprice_status: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

        function applyDiscount() {
            const discountValue = parseFloat($('#discount-percentage-input').val());
            const discountType = $('#discount-type-select').val();
            
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast('Please enter a valid discount value', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;

            allData.forEach(row => {
                const sku = row['sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['base_price']) || 0;
                    if (currentPrice > 0) {
                        let newSPrice;
                        
                        if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSPrice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSPrice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSPrice = currentPrice + discountValue;
                            } else {
                                newSPrice = currentPrice - discountValue;
                            }
                        }
                        
                        newSPrice = Math.max(0.01, newSPrice);
                        
                        const originalSPrice = parseFloat(row['sprice']) || 0;
                        
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['sku'] === sku;
                        });
                        
                        if (tableRow) {
                            tableRow.update({ 
                                sprice: newSPrice,
                                sprice_status: 'processing'
                            });
                            
                            // Force row to recalculate all formatted columns
                            tableRow.reformat();
                        }
                        
                        saveSpriceWithRetry(sku, newSPrice, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s)`, 'success');
                                    } else {
                                        showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ sprice: originalSPrice });
                                    tableRow.reformat();
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                }
                            });
                    }
                }
            });
            
            $('#discount-percentage-input').val('');
        }

        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['a_price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        row.update({
                            sprice: amazonPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            amazon_price: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuAmazonPriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuAmazonPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-amazon-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save Amazon prices', 'error');
                }
            });
        }

        function applySuggestRPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noRPriceCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const rPrice = parseFloat(rowData['recommended_base_price']);
                    
                    if (rPrice && rPrice > 0) {
                        row.update({
                            sprice: rPrice
                        });
                        
                        // Force row to recalculate all formatted columns
                        row.reformat();
                        
                        updates.push({
                            sku: sku,
                            r_price: rPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noRPriceCount++;
                    }
                } else {
                    noRPriceCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuRPriceUpdates(updates);
            }
            
            let message = `R price applied to ${updatedCount} SKU(s)`;
            if (noRPriceCount > 0) {
                message += ` (${noRPriceCount} SKU(s) had no R price or not found)`;
            }

            showToast(message, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuRPriceUpdates(updates) {
            $.ajax({
                url: '/temu-save-r-prices',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to save R prices', 'error');
                }
            });
        }

        function applySprice2699() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            const updates = [];
            const targetPrice = 26.99;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows("sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    
                    // Update the row with new SPRICE
                    row.update({ 
                        sprice: targetPrice
                    });
                    row.reformat();
                    
                    // Add to batch update
                    updates.push({
                        sku: sku,
                        sprice: targetPrice
                    });
                    
                    updatedCount++;
                }
            });
            
            if (updates.length > 0) {
                saveTemuSprice2699Updates(updates);
            }
            
            showToast(`SPRICE set to $26.99 for ${updatedCount} SKU(s)`, updatedCount > 0 ? 'success' : 'error');
        }

        function saveTemuSprice2699Updates(updates) {
            let saved = 0;
            let errors = 0;
            
            updates.forEach((update, index) => {
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: update.sku,
                        sprice: update.sprice
                    },
                    success: function(response) {
                        saved++;
                        if (index === updates.length - 1) {
                            showToast(`SPRICE $26.99 saved for ${saved} SKU(s)`, 'success');
                            table.redraw();
                        }
                    },
                    error: function(xhr) {
                        errors++;
                        if (index === updates.length - 1) {
                            if (errors === updates.length) {
                                showToast('Failed to save SPRICE', 'error');
                            } else {
                                showToast(`SPRICE saved for ${saved} SKU(s), ${errors} failed`, 'warning');
                            }
                        }
                    }
                });
            });
        }

        function clearAllSprice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            const skusArray = Array.from(selectedSkus);
            
            $.ajax({
                url: '/temu-clear-sprice',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    skus: skusArray
                },
                beforeSend: function() {
                    $('#clear-sprice-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Clearing...');
                },
                success: function(response) {
                    if (response.success) {
                        // Update the table rows
                        skusArray.forEach(sku => {
                            const rows = table.searchRows("sku", "=", sku);
                            if (rows.length > 0) {
                                rows[0].update({ sprice: null });
                                rows[0].reformat();
                            }
                        });
                        
                        showToast(`Successfully cleared SPRICE for ${response.cleared} SKU(s)`, 'success');
                        table.redraw();
                    }
                },
                error: function(xhr) {
                    showToast('Failed to clear SPRICE data', 'error');
                },
                complete: function() {
                    $('#clear-sprice-btn').prop('disabled', false).html('<i class="fa fa-trash"></i> Clear SPRICE');
                }
            });
        }

        function updateSummary() {
            const data = table.getData("active");
            
            let totalProducts = data.length;
            let totalQuantity = 0;
            let totalPriceWeighted = 0;
            let totalQty = 0;
            let totalRevenue = 0;
            let totalProfit = 0;
            let totalLp = 0;
            let totalGprft = 0;
            let totalGroi = 0;
            let totalAds = 0;
            let totalNpft = 0;
            let totalNroi = 0;
            let totalCvr = 0;
            let totalDil = 0;
            let totalSpend = 0;
            let totalViews = 0;
            let totalTemuL30 = 0;
            let totalInv = 0;
            let cvrCount = 0;
            let dilCount = 0;
            let zeroSoldCount = 0;
            
            data.forEach(row => {
                const qty = parseInt(row['quantity']) || 0;
                const price = parseFloat(row['base_price']) || 0;
                totalQuantity += qty;
                totalPriceWeighted += price * qty;
                totalQty += qty;
                
                // Revenue = Temu Price × Temu L30
                const temuPrice = parseFloat(row['temu_price']) || 0;
                const temuL30 = parseInt(row['temu_l30']) || 0;
                totalRevenue += temuPrice * temuL30;
                
                // Profit from row data
                totalProfit += parseFloat(row['profit']) || 0;
                
                // LP (Landing Price / COGS) from row data
                totalLp += parseFloat(row['lp']) || 0;
                
                // Percentage metrics (for averaging)
                totalGprft += parseFloat(row['profit_percent']) || 0;
                totalGroi += parseFloat(row['roi_percent']) || 0;
                totalAds += parseFloat(row['ads_percent']) || 0;
                totalNpft += parseFloat(row['npft_percent']) || 0;
                totalNroi += parseFloat(row['nroi_percent']) || 0;
                
                // CVR% (only count non-zero values for average)
                const cvr = parseFloat(row['cvr_percent']) || 0;
                if (cvr > 0) {
                    totalCvr += cvr;
                    cvrCount++;
                }
                
                // DIL% (only count non-zero values for average)
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                // Ad spend and views
                totalSpend += parseFloat(row['spend']) || 0;
                totalViews += parseInt(row['product_clicks']) || 0;
                totalTemuL30 += temuL30;
                totalInv += parseInt(row['inventory']) || 0;
                
                // Count SKUs with 0 sold (Temu L30 = 0)
                if (temuL30 === 0) {
                    zeroSoldCount++;
                }
            });
            
            // Calculate averages
            const avgPrice = totalQty > 0 ? totalPriceWeighted / totalQty : 0;
            const avgGprft = totalProducts > 0 ? totalGprft / totalProducts : 0;
            const avgGroi = totalProducts > 0 ? totalGroi / totalProducts : 0;
            const avgAds = totalProducts > 0 ? totalAds / totalProducts : 0;
            const avgNpft = totalProducts > 0 ? totalNpft / totalProducts : 0;
            const avgNroi = totalProducts > 0 ? totalNroi / totalProducts : 0;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            
            // Calculate TCOS: (Total Ad Spend / Total Revenue) × 100
            const totalTcos = totalRevenue > 0 ? (totalSpend / totalRevenue) * 100 : 0;
            
            // Update badges
            $('#total-products-badge').text('Total Products: ' + totalProducts.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold Count: ' + zeroSoldCount.toLocaleString());
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('Avg CVR: ' + avgCvr.toFixed(1) + '%');
            $('#avg-dil-badge').text('Avg DIL: ' + Math.round(avgDil) + '%');
            $('#total-revenue-badge').text('Total Revenue: $' + Math.round(totalRevenue).toLocaleString());
            $('#total-profit-badge').text('Total Profit: $' + Math.round(totalProfit).toLocaleString());
            $('#total-lp-badge').text('Total LP: $' + Math.round(totalLp).toLocaleString());
            $('#avg-gprft-badge').text('Avg GPRFT%: ' + avgGprft.toFixed(1) + '%');
            $('#avg-groi-badge').text('Avg GROI%: ' + avgGroi.toFixed(1) + '%');
            $('#total-spend-badge').text('Total Spend: $' + totalSpend.toFixed(2));
            $('#avg-ads-badge').text('Ads %: ' + Math.round(avgAds) + '%');
            $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos) + '%');
            $('#avg-npft-badge').text('Avg NPFT%: ' + avgNpft.toFixed(1) + '%');
            $('#avg-nroi-badge').text('Avg NROI%: ' + avgNroi.toFixed(1) + '%');
            $('#total-views-badge').text('Total Views: ' + totalViews.toLocaleString());
            $('#total-temu-l30-badge').text('Total Temu L30: ' + totalTemuL30.toLocaleString());
            $('#total-inv-badge').text('Total INV: ' + totalInv.toLocaleString());
        }

        // eBay-style color functions
        const getPftColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 125) return 'green';
            return 'pink';
        };

        table = new Tabulator("#temu-table", {
            ajaxURL: "/temu-decrease-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [
                {column: "cvr_percent", dir: "asc"}
            ],
            columns: [
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
                    headerSort: false
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '';
                        
                        return `${sku} <button class="btn btn-sm ms-1 view-sku-chart" data-sku="${sku}" title="View Metrics Chart" style="border: none; background: none; color: #87CEEB; padding: 2px 6px;"><i class="fa fa-info-circle"></i></button>`;
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    sorter: "number"
                },
                {
                    title: "OVL30",
                    field: "ovl30",
                    hozAlign: "center",
                    sorter: "number"
                },
                    {
                    title: "Dil%",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const dil = parseFloat(cell.getValue()) || 0;
                        
                        let color = '';
                        if (dil < 16.66) color = '#a00211'; // red (includes 0)
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                        else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                        else color = '#e83e8c'; // pink (50 and above)
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "Temu L30",
                    field: "temu_l30",
                    hozAlign: "center",
                    sorter: "number"
                },
            
                {
                    title: "CVR %",
                    field: "cvr_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#000';
                        
                        // eBay CVR color logic
                        if (value <= 4) color = '#a00211'; // red
                        else if (value > 4 && value <= 7) color = '#ffc107'; // yellow
                        else if (value > 7 && value <= 10) color = '#28a745'; // green
                        else color = '#ff1493'; // pink for > 10
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    }
                },
                 {
                    title: "Views",
                    field: "product_clicks",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    }
                },
               
                //  {
                //     title: "CTR",
                //     field: "ctr",
                //     hozAlign: "center",
                //     sorter: "number",
                //     formatter: function(cell) {
                //         const value = parseFloat(cell.getValue()) || 0;
                //         return value.toFixed(2) + '%';
                //     },
                //     width: 80
                // },
                {
                    title: "Base Price",
                    field: "base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    editorParams: {
                        min: 0,
                        step: 0.01
                    }
                },
                {
                    title: "Temu Price",
                    field: "temu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const basePrice = parseFloat(cell.getRow().getData()['base_price']) || 0;
                        // Only calculate Temu Price if base_price > 0 (item exists in Temu)
                        if (basePrice === 0) {
                            return '$0.00';
                        }
                        const temuPrice = basePrice <= 26.99 ? basePrice + 2.99 : basePrice;
                        return '$' + temuPrice.toFixed(2);
                    }
                },
                {
                    title: "A Price",
                    field: "a_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    }
                },
                {
                    title: "PRFT AMT",
                    field: "profit",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value < 0 ? '#dc3545' : (value > 0 ? '#28a745' : '#6c757d');
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    visible: false
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        // Custom sorter to handle the 100% case properly
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aSpend = parseFloat(aData['spend'] || 0);
                        const bSpend = parseFloat(bData['spend'] || 0);
                        const aTemuL30 = parseFloat(aData['temu_l30'] || 0);
                        const bTemuL30 = parseFloat(bData['temu_l30'] || 0);
                        
                        // Calculate effective ADS% (100 if spend > 0 and sales = 0)
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aSpend > 0 && aTemuL30 === 0) aVal = 100;
                        if (bSpend > 0 && bTemuL30 === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const rowData = cell.getRow().getData();
                        const spend = parseFloat(rowData['spend'] || 0);
                        const temuL30 = parseFloat(rowData['temu_l30'] || 0);
                        let color = '#000';
                        
                        // If spend > 0 but no sales, show 100% in red
                        if (spend > 0 && temuL30 === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">100%</span>`;
                        }
                        
                        // eBay ACOS color logic (includes 0 and 100 conditions)
                        if (value == 0 || value == 100) color = '#a00211'; // red
                        else if (value > 0 && value <= 7) color = '#ff1493'; // pink
                        else if (value > 7 && value <= 14) color = '#28a745'; // green
                        else if (value > 14 && value <= 21) color = '#ffc107'; // yellow
                        else if (value > 21) color = '#a00211'; // red
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    }
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },



                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    }
                },
                     {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "R Prc",
                    field: "recommended_base_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value || value === 0) return '';
                        return `$${parseFloat(value).toFixed(2)}`;
                    }
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const basePrice = parseFloat(rowData['base_price']) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value || sprice === 0) return '';
                        
                        // If SPRICE matches Base Price, show dash
                        if (sprice === basePrice) {
                            return '<span style="color: #999; font-style: italic;">-</span>';
                        }
                        
                        return `$${parseFloat(value).toFixed(2)}`;
                    }
                },
           
                {
                    title: "S Temu Prc",
                    field: "stemu_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price (SPRICE + 2.99 if <= 26.99)
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        return `$${stemuPrice.toFixed(2)}`;
                    }
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const percentage = 0.91; // Temu marketplace percentage
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SGPRFT% = ((S Temu Price × percentage - LP - Temu Ship) / S Temu Price) × 100
                        const sgprft = stemuPrice > 0 ? ((stemuPrice * percentage - lp - temuShip) / stemuPrice) * 100 : 0;
                        
                        const colorClass = getPftColor(sgprft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    }
                },
                {
                    title: "SROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const percentage = 0.91; // Temu marketplace percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SROI% = ((S Temu Price × percentage - LP - Temu Ship) / LP) × 100
                        const sroi = lp > 0 ? ((stemuPrice * percentage - lp - temuShip) / lp) * 100 : 0;
                        
                        const colorClass = getRoiColor(sroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(sroi)}%</span>`;
                    }
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.91; // Temu marketplace percentage
                        
                        if (sprice === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SGPRFT%
                        const sgprft = stemuPrice > 0 ? ((stemuPrice * percentage - lp - temuShip) / stemuPrice) * 100 : 0;
                        
                        // SPFT% = SGPRFT% - ADS%
                        const spft = sgprft - adsPercent;
                        
                        const colorClass = getPftColor(spft);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    }
                },
                {
                    title: "SNROI%",
                    field: "snroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const temuShip = parseFloat(rowData['temu_ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.91; // Temu marketplace percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // Calculate Suggested Temu Price
                        const stemuPrice = sprice <= 26.99 ? sprice + 2.99 : sprice;
                        
                        // SROI%
                        const sroi = lp > 0 ? ((stemuPrice * percentage - lp - temuShip) / lp) * 100 : 0;
                        
                        // SNROI% = SROI% - ADS%
                        const snroi = sroi - adsPercent;
                        
                        const colorClass = getRoiColor(snroi);
                        return `<span class="dil-percent-value ${colorClass}">${Math.round(snroi)}%</span>`;
                    }
                },
                 {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return '$' + value.toFixed(2);
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    visible: false
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            updateSummary();
        });

        // Apply filters
        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const adsFilter = $('#ads-filter').val();
            const spriceFilter = $('#sprice-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            const skuSearch = $('#sku-search').val();

            // Clear all filters first
            table.clearFilter();

            // SKU search filter
            if (skuSearch) {
                table.setFilter("sku", "like", skuSearch);
            }

            // Inventory filter
            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.inventory) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    if (inventoryFilter === 'eq0') return inv === 0;
                    return true;
                });
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    const gpft = parseFloat(data.profit_percent) || 0;
                    
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                    if (gpftFilter === '50-60') return gpft >= 50 && gpft < 60;
                    if (gpftFilter === '60plus') return gpft >= 60;
                    return true;
                });
            }

            // CVR filter
            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    const cvr = parseFloat(data.cvr_percent) || 0;
                    const cvrRounded = Math.round(cvr * 100) / 100;
                    
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0.01-1') return cvrRounded >= 0.01 && cvrRounded <= 1;
                    if (cvrFilter === '1-2') return cvrRounded > 1 && cvrRounded <= 2;
                    if (cvrFilter === '2-3') return cvrRounded > 2 && cvrRounded <= 3;
                    if (cvrFilter === '3-4') return cvrRounded > 3 && cvrRounded <= 4;
                    if (cvrFilter === '0-4') return cvrRounded >= 0 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-10') return cvrRounded > 7 && cvrRounded <= 10;
                    if (cvrFilter === '10plus') return cvrRounded > 10;
                    return true;
                });
            }

            // ADS filter
            if (adsFilter !== 'all') {
                table.addFilter(function(data) {
                    const ads = parseFloat(data.ads_percent) || 0;
                    
                    if (adsFilter === '0-10') return ads >= 0 && ads < 10;
                    if (adsFilter === '10-20') return ads >= 10 && ads < 20;
                    if (adsFilter === '20-30') return ads >= 20 && ads < 30;
                    if (adsFilter === '30-100') return ads >= 30 && ads <= 100;
                    if (adsFilter === '100plus') return ads > 100;
                    return true;
                });
            }

            // DIL filter
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const dil = parseFloat(data['dil_percent']) || 0;
                    
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // SPRICE filter
            if (spriceFilter !== 'all') {
                table.addFilter(function(data) {
                    const sprice = parseFloat(data.sprice) || 0;
                    
                    if (spriceFilter === '27-31') return sprice >= 27 && sprice <= 31;
                    if (spriceFilter === 'lt27') return sprice > 0 && sprice < 27;
                    if (spriceFilter === 'gt31') return sprice > 31;
                    return true;
                });
            }

            updateSummary();
            updateSelectAllCheckbox();
        }

        $('#inventory-filter, #gpft-filter, #cvr-filter, #ads-filter, #sprice-filter').on('change', function() {
            applyFilters();
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const column = $item.data('column');
            const color = $item.data('color');
            const dropdown = $item.closest('.dropdown');
            const button = dropdown.find('.dropdown-toggle');
            
            dropdown.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            applyFilters();
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            
            if (field === 'base_price') {
                const newPrice = parseFloat(cell.getValue());
                if (newPrice < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                $.ajax({
                    url: '/temu-pricing/update-price',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        base_price: newPrice
                    },
                    success: function(response) {
                        showToast('Price updated successfully', 'success');
                        updateSummary();
                    },
                    error: function(xhr) {
                        showToast('Failed to update price', 'error');
                        cell.restoreOldValue();
                    }
                });
            }
            
            // Handle SPRICE edit
            if (field === 'sprice') {
                const newSprice = parseFloat(cell.getValue());
                if (newSprice < 0) {
                    showToast('SPRICE cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                row.update({ sprice: newSprice });
                row.reformat();
                
                $.ajax({
                    url: '/temu-pricing/save-sprice',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['sku'],
                        sprice: newSprice
                    },
                    success: function(response) {
                        showToast('SPRICE saved successfully', 'success');
                    },
                    error: function(xhr) {
                        showToast('Failed to save SPRICE', 'error');
                    }
                });
            }
        });

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field && def.field !== '_select') {
                        const visible = savedVisibility[def.field] !== undefined ? savedVisibility[def.field] : def.visible !== false;
                        const li = document.createElement('li');
                        li.className = 'dropdown-item';
                        li.innerHTML = `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${def.field}" 
                                       id="col-${def.field}" ${visible ? 'checked' : ''}>
                                <label class="form-check-label" for="col-${def.field}">
                                    ${def.title}
                                </label>
                            </div>
                        `;
                        menu.appendChild(li);
                    }
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

            fetch('/temu-decrease-column-visibility', {
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
            fetch('/temu-decrease-column-visibility', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(savedVisibility => {
                table.getColumns().forEach(col => {
                    const field = col.getField();
                    if (field && savedVisibility[field] !== undefined) {
                        if (savedVisibility[field]) {
                            col.show();
                        } else {
                            col.hide();
                        }
                    }
                });
            });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            // Apply default INV > 0 filter on page load
            applyFilters();
            updateSummary();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('renderComplete', function() {
            updateSummary();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
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

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => {
                col.show();
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export functionality
        $('#export-btn').on('click', function() {
            table.download("csv", "temu_decrease_data.csv");
        });
    });
</script>
@endsection
