@extends('layouts.vertical', ['title' => 'Account Health Master Dashboard', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Custom styles for the Tabulator table */
        /* Pagination styling */
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

        .custom-select-wrapper {
            width: 100%;
            cursor: pointer;
            position: relative;
        }

        .custom-select-display {
            background-color: #fff;
            border: 1px solid #ced4da;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
        }

        .custom-select-options {
            position: absolute;
            z-index: 999;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-top: none;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .custom-select-search {
            width: 100%;
            padding: 0.5rem;
            border: none;
            border-bottom: 1px solid #eee;
            outline: none;
        }

        .custom-select-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
        }

        .custom-select-option:hover {
            background-color: #f1f1f1;
        }

        .ec-dash-pie-wrap {
            max-width: 260px;
            margin: 0 auto;
        }

        .ec-dash-pie-wrap canvas {
            cursor: pointer;
        }

        .ec-dash-legend-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        /* Do not use .table-container here: global overflow:auto + sticky
           .tabulator-header collapses the table body to one row. */
        .ahm-dash-table-wrap {
            width: 100%;
            max-height: none !important;
            overflow: visible !important;
            margin-bottom: 1.5rem;
        }

        #channelTable.tabulator {
            height: 560px !important;
            min-height: 560px !important;
            max-height: none !important;
        }

        #channelTable.tabulator .tabulator-header,
        #channelTable.tabulator .tabulator-header .tabulator-col:not(.tabulator-frozen) {
            position: relative !important;
            top: auto !important;
        }

        #channelTable.tabulator .tabulator-header .tabulator-col.tabulator-frozen {
            position: sticky !important;
            z-index: 12;
            background: #00d5d5 !important;
        }

        #channelTable.tabulator .tabulator-row .tabulator-cell.tabulator-frozen {
            position: sticky !important;
            z-index: 11;
            background: #fff;
        }

        #channelTable.tabulator .tabulator-tableholder {
            height: 470px !important;
            min-height: 470px !important;
            max-height: 470px !important;
            overflow: auto !important;
            flex: none !important;
        }

        .dropdown-search-container {
            position: relative;
            min-width: 260px;
        }

        .ahm-search-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }

        .dropdown-search-container .ahm-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            z-index: 2;
        }

        .dropdown-search-container .form-control {
            padding-left: 34px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            height: 38px;
        }

        .dropdown-search-results {
            display: none;
            position: fixed;
            z-index: 10050;
            max-height: 280px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, .16);
            padding: 6px;
        }

        .dropdown-search-item {
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #0f172a;
        }

        .dropdown-search-item:hover,
        .dropdown-search-item.active {
            background: #eef2ff;
            color: #3730a3;
        }

        .dropdown-search-empty {
            padding: 10px;
            color: #64748b;
            font-size: 13px;
        }

        .ahm-metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 12px;
        }

        .ahm-metric-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 12px 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .ahm-metric-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 2px;
        }

        .ahm-metric-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
        }

        .ahm-metric-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex: none;
        }

        .ahm-metric-date {
            font-size: 11px;
            color: #64748b;
            text-align: right;
            line-height: 1.3;
            white-space: pre-line;
        }

        .ahm-metric-canvas {
            position: relative;
            height: 132px;
        }

        .ahm-metric-foot {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            color: #334155;
            margin-top: 4px;
        }

        .ahm-chart-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
            justify-content: space-between;
            width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Account Health Master Dashboard',
        'sub_title' => 'Manage your channels and monitor their performance metrics',
    ])
    <style>
        .ahm-dash-table-wrap { max-height: none !important; overflow: visible !important; }
        #channelTable.tabulator { height: 560px !important; min-height: 560px !important; }
        #channelTable.tabulator .tabulator-header,
        #channelTable.tabulator .tabulator-header .tabulator-col:not(.tabulator-frozen) { position: relative !important; top: auto !important; }
        #channelTable.tabulator .tabulator-header .tabulator-col.tabulator-frozen { position: sticky !important; z-index: 12; background: #00d5d5 !important; }
        #channelTable.tabulator .tabulator-row .tabulator-cell.tabulator-frozen { position: sticky !important; z-index: 11; background: #fff; }
        #channelTable.tabulator .tabulator-tableholder {
            height: 470px !important; min-height: 470px !important; max-height: 470px !important;
            overflow: auto !important; flex: none !important;
        }
    </style>
    <div class="container-fluid">
        <div class="col-md-12 mt-0 pt-0 mb-1 pb-1">
            <div class="row justify-content-center align-items-center g-3">
                <div class="col d-flex align-items-center gap-2 flex-wrap">
                    <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                        <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="play-pause" class="btn btn-light rounded-circle" title="Pause playback"
                            style="display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="play-auto" class="btn btn-light rounded-circle" title="Start auto-play">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div>
                    <button id="addChannelBtn" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#addChannelModal"
                        style="background: linear-gradient(135deg, #4361ee, #3f37c9); border: none;">
                        <i class="fas fa-plus-circle me-2"></i> Add Channel
                    </button>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <a href="{{ route('account-health-master.export') }}" class="btn btn-success">
                            <i class="fas fa-file-export me-1"></i> Export Health Data
                        </a>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#accountHealthImportModal">
                            <i class="fas fa-file-import me-1"></i> Import Health Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Channel Modal -->
        <div class="modal fade right-to-left" id="addChannelModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-side">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Add New Channel</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="channelForm">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="channelName" class="form-label">Channel Name</label>
                                    <input type="text" class="form-control" id="channelName" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="channelUrl" class="form-label">Sheet Link</label>
                                <input type="url" class="form-control" id="channelUrl">
                            </div>
                            <div class="mb-3">
                                <label for="type" class="form-label">Type</label>
                                <input type="text" class="form-control" id="type">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveChannelBtn">Save Channel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Channel Modal -->
        <div class="modal fade" id="editChannelModal" tabindex="-1" aria-labelledby="editChannelModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editChannelForm">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editChannelModalLabel">Edit Channel</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="originalChannel" name="original_channel">
                            <div class="mb-3">
                                <label for="editChannelName" class="form-label">Channel Name</label>
                                <input type="text" class="form-control" id="editChannelName" name="channel_name"
                                    readonly>
                            </div>
                            <div class="mb-3">
                                <label for="editChannelUrl" class="form-label">Sheet URL</label>
                                <input type="text" class="form-control" id="editChannelUrl" name="sheet_url"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="editType" class="form-label">Type</label>
                                <input type="text" class="form-control" id="editType" name="type" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Update Channel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div class="modal fade" id="accountHealthImportModal" tabindex="-1"
            aria-labelledby="accountHealthImportModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('account-health-master.import') }}" method="POST" enctype="multipart/form-data"
                    class="modal-content" id="accountHealthImportForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="accountHealthImportModalLabel">Import Account Health Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="accountHealthExcelFile" class="form-label">Select Excel File</label>
                            <input type="file" class="form-control" id="accountHealthExcelFile" name="excel_file"
                                accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="mb-3">
                            <label for="importType" class="form-label">Import Type</label>
                            <select class="form-control" id="importType" name="import_type" required>
                                <option value="">Select Import Type</option>
                                <option value="channel_data">Channel Performance Data</option>
                                <option value="health_rates">Health Rates (ODR, Fulfillment, etc.)</option>
                                <option value="both">Both Channel Data & Health Rates</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="updateMode" class="form-label">Update Mode</label>
                            <select class="form-control" id="updateMode" name="update_mode" required>
                                <option value="update">Update Existing Records</option>
                                <option value="create">Create New Records Only</option>
                                <option value="replace">Replace All Data</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Download sample files:
                                <br>
                                • <a href="{{ route('account-health-master.sample', 'channel') }}"
                                    class="alert-link">Channel Data Sample</a>
                                <br>
                                • <a href="{{ route('account-health-master.sample', 'rates') }}" class="alert-link">Health
                                    Rates Sample</a>
                                <br>
                                • <a href="{{ route('account-health-master.sample', 'combined') }}"
                                    class="alert-link">Combined Sample</a>
                            </small>
                        </div>
                        <div class="progress mb-3" id="importProgress" style="display: none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                style="width: 0%">0%</div>
                        </div>
                        <div id="importResults" class="alert alert-success" style="display: none;">
                            <h6>Import Results:</h6>
                            <ul id="importResultsList"></ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="importSubmitBtn">
                            <i class="fas fa-file-import me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="customLoader" style="display: flex; justify-content: center; align-items: center; height: 300px;">
            <div class="spinner-border text-info" role="status">
                <span class="sr-only">Loading...</span>
            </div>
            <span class="ms-2">Loading table, please wait...</span>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-12 col-lg-4 col-xl-3">
                <div class="card shadow-sm">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
                        <span class="fw-semibold">Escalated Claims</span>
                        <a href="{{ route('escalated.claims.tabulator') }}" class="small text-decoration-none">Open page</a>
                    </div>
                    <div class="card-body py-2">
                        <div class="ec-dash-pie-wrap">
                            <canvas id="ec-dashboard-status-pie" height="200"></canvas>
                        </div>
                        <div class="d-flex justify-content-center gap-3 flex-wrap small mt-2">
                            <span><span class="ec-dash-legend-dot" style="background:#dc2626;"></span> Red <strong
                                    id="ec-dash-count-red">0</strong></span>
                            <span><span class="ec-dash-legend-dot" style="background:#eab308;"></span> Yellow <strong
                                    id="ec-dash-count-yellow">0</strong></span>
                            <span><span class="ec-dash-legend-dot" style="background:#16a34a;"></span> Green <strong
                                    id="ec-dash-count-green">0</strong></span>
                        </div>
                        <p class="small text-muted text-center mb-0 mt-1">Click a slice to open filtered list</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8 col-xl-9">
                <div class="card shadow-sm">
                    <div class="card-header py-2">
                        <div class="ahm-chart-toolbar">
                            <div>
                                <div class="fw-semibold" id="ahmChartTitle">Marketplace columns</div>
                                <div class="small text-muted" id="ahmChartSubtitle">Search a marketplace. Each column gets its own graph and updated date.</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-end">
                                <div>
                                    <label class="ahm-search-label" for="ahmChartSearch">Marketplace</label>
                                    <div class="dropdown-search-container">
                                        <i class="fas fa-search ahm-search-icon"></i>
                                        <input type="text" class="form-control form-control-sm" id="ahmChartSearch" placeholder="Type to search" autocomplete="off">
                                        <div class="dropdown-search-results" id="ahmChartSearchDropdown"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="ahmHistoryBtn" data-bs-toggle="modal" data-bs-target="#ahmHistoryModal">History</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body py-2">
                        <div id="ahmMetricGrid" class="ahm-metric-grid"></div>
                        <p id="ahmChartEmpty" class="small text-muted text-center mb-0">Search a marketplace to see a graph for every column.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="ahmHistoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Entry history <span id="ahmHistoryChannel" class="text-muted"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                            <label class="small mb-0 text-muted" for="ahmHistoryColumn">Column</label>
                            <select id="ahmHistoryColumn" class="form-select form-select-sm" style="max-width: 240px;">
                                <option value="">All columns</option>
                            </select>
                        </div>
                        <div id="ahmHistoryLoading" class="p-3 text-muted">Loading...</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Updated date</th>
                                        <th>When</th>
                                        <th>User</th>
                                        <th>Column</th>
                                        <th>Field</th>
                                        <th>From</th>
                                        <th>To</th>
                                    </tr>
                                </thead>
                                <tbody id="ahmHistoryBody"></tbody>
                            </table>
                        </div>
                        <p id="ahmHistoryEmpty" class="p-3 text-muted mb-0" style="display:none;">No entries for this marketplace yet.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="ahm-dash-table-wrap" id="channelTableWrapper">
            <div id="channelTable"></div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        (function() {
            const summaryUrl = @json(route('escalated.claims.summary'));
            const pageUrl = @json(route('escalated.claims.tabulator'));
            const canvas = document.getElementById('ec-dashboard-status-pie');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            fetch(summaryUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r) {
                return r.ok ? r.json() : Promise.reject(r);
            }).then(function(payload) {
                const counts = (payload && payload.counts) ? payload.counts : {
                    red: 0,
                    yellow: 0,
                    green: 0
                };
                const redEl = document.getElementById('ec-dash-count-red');
                const yellowEl = document.getElementById('ec-dash-count-yellow');
                const greenEl = document.getElementById('ec-dash-count-green');
                if (redEl) redEl.textContent = String(counts.red || 0);
                if (yellowEl) yellowEl.textContent = String(counts.yellow || 0);
                if (greenEl) greenEl.textContent = String(counts.green || 0);

                const values = [counts.red || 0, counts.yellow || 0, counts.green || 0];
                const tones = ['red', 'yellow', 'green'];
                new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Red', 'Yellow', 'Green'],
                        datasets: [{
                            data: values,
                            backgroundColor: ['#dc2626', '#eab308', '#16a34a'],
                            borderWidth: 1,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const total = values.reduce(function(a, b) {
                                            return a + b;
                                        }, 0);
                                        const v = ctx.raw || 0;
                                        const pct = total ? ((v / total) * 100).toFixed(1) : '0.0';
                                        return ctx.label + ': ' + v + ' (' + pct + '%)';
                                    }
                                }
                            }
                        },
                        onClick: function(evt, elements) {
                            if (!elements || !elements.length) {
                                return;
                            }
                            const tone = tones[elements[0].index];
                            if (!tone) {
                                return;
                            }
                            window.location.href = pageUrl + '?tone=' + encodeURIComponent(tone);
                        }
                    }
                });
            }).catch(function(err) {
                console.error('Escalated Claims summary failed', err);
            });
        })();
    </script>

    <script>
        var jq = jQuery.noConflict(true);
        let originalChannelData = [];
        let ahmCharts = [];
        let ahmHistoryRows = [];
        let table;
        let isPlaying = false;
        let currentChannelIndex = 0;
        let uniqueChannels = [];
        let uniqueChannelRows = [];

        // Mapping of fields to their update endpoints
        const fieldUpdateEndpoints = {
            'odr_rate': '/odr-rate/update',
            'odr_rate_allowed': '/odr-rate/update',
            'fulfillment_rate': '/fullfillment-rate/update',
            'fulfillment_rate_allowed': '/fullfillment-rate/update',
            'valid_tracking_rate': '/validtracking-rate/update',
            'valid_tracking_rate_allowed': '/validtracking-rate/update',
            'on_time_delivery': '/onTimeDelivery-rate/update',
            'on_time_delivery_allowed': '/onTimeDelivery-rate/update',
            'atoz_claims_rate': '/atozclaims-rate/update',
            'atoz_claims_rate_allowed': '/atozclaims-rate/update',
            'violation_rate': '/voilance-rate/update',
            'violation_rate_allowed': '/voilance-rate/update',
            'late_shipment_rate': '/lateshipment-rate/update',
            'late_shipment_rate_allowed': '/lateshipment-rate/update',
            'negative_seller_rate': '/negativeSeller-rate/update',
            'negative_seller_rate_allowed': '/negativeSeller-rate/update',
            'refund_rate': '/refund-rate/update',
            'refund_rate_allowed': '/refund-rate/update'
        };

        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || value === '#DIV/0!' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Adds a "Delete cell data" option (right-click) to every editable rate cell, to remove wrong entries.
        const clearableFields = Object.keys(fieldUpdateEndpoints);

        function deleteCellData(cell) {
            const field = cell.getField();
            const channel = cell.getData().channel;
            const label = cell.getColumn().getDefinition().title || field;
            if (!confirm(`Delete "${String(label).replace(/<[^>]*>/g, ' ').trim()}" data for "${channel}"?`)) return;

            fetch('/account-health-master/dashboard/clear-cell', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ channel: channel, field: field })
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        // row.update does not fire cellEdited, so nothing is re-saved
                        cell.getRow().update({ [field]: field.endsWith('_allowed') ? '' : 'N/A' });
                    } else {
                        alert('Error: ' + (res.message || 'Failed to delete cell data.'));
                    }
                })
                .catch(() => alert('Server error while deleting cell data.'));
        }

        function withCellDeleteMenu(columns) {
            return columns.map(col => {
                if (col.field && clearableFields.includes(col.field)) {
                    col.contextMenu = [{
                        label: '<i class="fas fa-trash text-danger"></i> Delete cell data',
                        action: function(e, cell) {
                            deleteCellData(cell);
                        }
                    }];
                }
                return col;
            });
        }

        function initializeTable() {
            const toNum = (v, def = 0) => {
                const n = parseFloat(String(v).replace(/,/g, ''));
                return Number.isFinite(n) ? n : def;
            };
            const pick = (obj, keys, def = '') => {
                for (const k of keys) {
                    const v = obj[k];
                    if (v !== undefined && v !== null && v !== '') return v;
                }
                return def;
            };

            function formatExactPercent(raw) {
                const text = String(raw == null ? '' : raw).replace(/%/g, '').replace(/,/g, '').trim();
                if (/^-?\d+(\.\d+)?$/.test(text)) {
                    return text + '%';
                }
                const parsed = parseFloat(text);
                return (Number.isFinite(parsed) ? String(parsed) : text) + '%';
            }

            table = new Tabulator("#channelTable", {
                ajaxURL: "/account-health-master/dashboard-data",
                ajaxConfig: "GET",
                layout: "fitDataFill",
                pagination: true,
                paginationSize: 50,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "560px",
                renderVertical: "basic",
                ajaxResponse: function(url, params, response) {
                    console.log('AJAX response:', response);
                    jq('#customLoader').hide();
                    if (!response || !response.data) {
                        console.warn('No data in response:', response);
                        alert('No data received from server.');
                        return [];
                    }
                    originalChannelData = response.data;
                    rememberMarketplace(response.data);
                    updateL30OrdersTotal(response.data);
                    return response.data.map(item => ({
                        channel: pick(item, ['channel', 'Channel', 'Channel '], ''),
                        l30_orders: toNum(pick(item, ['l30_orders', 'L30 Orders'], 0), 0),
                        nr: toNum(pick(item, ['nr', 'NR'], 0), 0),
                        odr_rate: pick(item, ['odr_rate', 'ODR Rate', 'ODR'], 'N/A'),
                        odr_rate_allowed: pick(item, ['odr_rate_allowed'], ''),
                        fulfillment_rate: pick(item, ['fulfillment_rate', 'Fulfillment Rate'],
                            'N/A'),
                        fulfillment_rate_allowed: pick(item, ['fulfillment_rate_allowed'], ''),
                        valid_tracking_rate: pick(item, ['valid_tracking_rate',
                            'Valid Tracking Rate'
                        ], 'N/A'),
                        valid_tracking_rate_allowed: pick(item, ['valid_tracking_rate_allowed'],
                            ''),
                        on_time_delivery: pick(item, ['on_time_delivery_rate',
                                'On Time Delivery Rate'
                            ],
                            'N/A'),
                        on_time_delivery_allowed: pick(item, ['on_time_delivery_rate_allowed'], ''),
                        // atoz_claims_rate: pick(item, ['atoz_claims_rate', 'AtoZ Claims Rate'],
                        //     'N/A'),
                        // atoz_claims_rate_allowed: pick(item, ['atoz_claims_rate_allowed'], ''),
                        violation_rate: pick(item, ['violation_rate', 'Violation Rate'], 'N/A'),
                        violation_rate_allowed: pick(item, ['violation_rate_allowed'], ''),
                        late_shipment_rate: pick(item, ['late_shipment_rate', 'Late Shipment Rate'],
                            'N/A'),
                        late_shipment_rate_allowed: pick(item, ['late_shipment_rate_allowed'], ''),
                        negative_seller_rate: pick(item, ['negative_seller_rate',
                            'Negative Seller Rate'
                        ], 'N/A'),
                        negative_seller_rate_allowed: pick(item, ['negative_seller_rate_allowed'],
                            ''),
                        refund_rate: pick(item, ['refund_rate', 'Refund Rate'], 'N/A'),
                        refund_rate_allowed: pick(item, ['refund_rate_allowed'], ''),
                        sheet_link: pick(item, ['sheet_link'], ''),
                        type: pick(item, ['type'], '')
                    }));
                },
                ajaxError: function(xhr, error, thrown) {
                    console.error("AJAX error:", error, thrown, xhr.responseText);
                    jq('#customLoader').hide();
                    alert('Failed to load data: ' + (xhr.responseJSON?.message ||
                        'Unknown error. Check console for details.'));
                },
                ajaxRequesting: function() {
                    jq('#customLoader').show();
                },
                columns: withCellDeleteMenu([{
                        title: "Channel",
                        field: "channel",
                        frozen: true,
                        headerSort: true,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (!value) return "";
                            const channelName = value.trim().toLowerCase();
                            const routeMap = {
                                'amazon': '/overall-amazon',
                                'amazon fba': '/overall-amazon-fba',
                                'ebay': '/ebay',
                                'ebaytwo': '/ebayTwoAnalysis',
                                'ebaythree': '/ebayThreeAnalysis',
                                'temu': '/temu',
                                'macys': '/macys',
                                'wayfair': '/Wayfair',
                                'reverb': '/reverb',
                                'shopify b2c': '/shopifyB2C',
                                'doba': '/doba',
                                'walmart': '/walmartAnalysis',
                                'bestbuy usa': '/bestbuyusa-analytics',
                            };
                            const routeUrl = routeMap[channelName];
                            return routeUrl ?
                                `<a href="${routeUrl}" target="_blank" style="color: #007bff; text-decoration: underline;">${value}</a>` :
                                `<span style="background:#f5f5f5;color:#000;font-weight:600;padding:4px 14px;border-radius:12px;">${value}</span>`;
                        },
                        hozAlign: "left"
                    },
                    {
                        title: `<small id="l30OrdersCountBadgeHeader" class="badge bg-dark text-white mb-1" style="font-size: 13px;">0</small><br>L30 Orders`,
                        field: "l30_orders",
                        frozen: true,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span class="metric-value">${value.toLocaleString('en-US')}</span>`;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "NR",
                        field: "nr",
                        formatter: function(cell) {
                            const value = toNum(cell.getValue());
                            const checked = value === 1 ? 'checked' : '';
                            return `<input type="checkbox" class="checkbox-nr" data-channel="${cell.getData().channel}" ${checked}>`;
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "ODR Rate",
                        field: "odr_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = "any";

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string') {
                                numericValue = currentValue.replace('%', '').trim();
                            }
                            input.value = (numericValue === 'N/A' || numericValue === '' || numericValue == null || isNaN(parseFloat(numericValue))) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            const exact = String(value).replace(/%/g, '').replace(/,/g, '').trim();
                            const shown = /^-?\d+(\.\d+)?$/.test(exact) ? exact : String(parsed);
                            let color = parsed > 5 ? "#dc3545" : parsed > 2 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${shown}%</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['odr_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "ODR Rate Allowed",
                        field: "odr_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['odr_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Fulfillment Rate",
                        field: "fulfillment_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed < 95 ? "#dc3545" : parsed < 98 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['fulfillment_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Fulfillment Rate Allowed",
                        field: "fulfillment_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['fulfillment_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Valid Tracking Rate",
                        field: "valid_tracking_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed < 90 ? "#dc3545" : parsed < 95 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['valid_tracking_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Valid Tracking Rate Allowed",
                        field: "valid_tracking_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['valid_tracking_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "On Time Delivery",
                        field: "on_time_delivery",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed < 90 ? "#dc3545" : parsed < 95 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['on_time_delivery']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "On Time Delivery Allowed",
                        field: "on_time_delivery_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['on_time_delivery_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Violation/Compliance",
                        field: "violation_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed > 5 ? "#dc3545" : parsed > 2 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['violation_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Violation/Compliance Allowed",
                        field: "violation_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['violation_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Late Shipment Rate",
                        field: "late_shipment_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed > 10 ? "#dc3545" : parsed > 5 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['late_shipment_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Late Shipment Rate Allowed",
                        field: "late_shipment_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['late_shipment_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Negative Seller Rate",
                        field: "negative_seller_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed > 3 ? "#dc3545" : parsed > 1 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['negative_seller_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Negative Seller Rate Allowed",
                        field: "negative_seller_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['negative_seller_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Refund Rate",
                        field: "refund_rate",
                        editor: function(cell, onRendered, success, cancel) {
                            const input = document.createElement("input");
                            input.type = "number";
                            input.min = 0;
                            input.max = 100;
                            input.step = 0.1;

                            const currentValue = cell.getValue();
                            let numericValue = currentValue;
                            if (typeof currentValue === 'string' && currentValue.includes('%')) {
                                numericValue = parseFloat(currentValue.replace('%', ''));
                            }
                            input.value = isNaN(numericValue) ? '' : numericValue;

                            onRendered(function() {
                                input.focus();
                                input.style.height = "100%";
                                input.style.width = "100%";
                                input.style.border = "none";
                                input.style.padding = "4px";
                                input.style.boxSizing = "border-box";
                            });

                            function onSubmit() {
                                let value = parseFloat(input.value);
                                if (input.value.trim() === '') {
                                    // Emptied the box = delete this cell's data (wrong entry)
                                    cancel();
                                    const existing = cell.getValue();
                                    if (!input._clearing && existing !== 'N/A' && existing !== '' && existing !== null && existing !== undefined) {
                                        input._clearing = true;
                                        setTimeout(function() { deleteCellData(cell); }, 0);
                                    }
                                    return;
                                }
                                if (isNaN(value) || value < 0 || value > 100) {
                                    cancel();
                                    return;
                                }
                                success(value);
                            }

                            input.addEventListener("blur", onSubmit);
                            input.addEventListener("keydown", function(e) {
                                if (e.key === "Enter") {
                                    onSubmit();
                                }
                                if (e.key === "Escape") {
                                    cancel();
                                }
                            });

                            return input;
                        },
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === 'N/A' || value === '' || isNaN(parseFloat(value))) {
                                return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                            <span>N/A</span>
                                        </div>`;
                            }
                            const parsed = parseFloat(value);
                            let color = parsed > 2 ? "#dc3545" : parsed > 1 ? "#ffc107" : "#28a745";
                            return `<div class="text-center editable-field" style="background-color:${color}; color:#ffffff; padding:4px; border-radius:4px;">
                                        <span>${formatExactPercent(value)}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            const newValue = parseFloat(cell.getValue());
                            if (isNaN(newValue) || newValue < 0 || newValue > 100) {
                                alert('Please enter a value between 0 and 100');
                                cell.restoreOldValue();
                                return;
                            }
                            updateField(cell, fieldUpdateEndpoints['refund_rate']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Refund Rate Allowed",
                        field: "refund_rate_allowed",
                        editor: "input",
                        editorParams: {
                            minLength: 0,
                            maxLength: 50
                        },
                        formatter: function(cell) {
                            const value = cell.getValue() || '';
                            return `<div class="text-center editable-field" style="padding:4px; border-radius:4px;">
                                        <span>${value}</span>
                                    </div>`;
                        },
                        cellEdited: function(cell) {
                            updateField(cell, fieldUpdateEndpoints['refund_rate_allowed']);
                        },
                        hozAlign: "center"
                    },
                    {
                        title: "Actions",
                        field: null,
                        formatter: function(cell) {
                            return `
                                <div class="d-flex justify-content-center">
                                    <button class="btn btn-sm btn-outline-primary edit-btn me-1" title="Edit" data-channel="${cell.getData().channel}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>`;
                        },
                        hozAlign: "center"
                    }
                ])
            });

            table.on("cellEdited", function(cell) {
                // Handled by column-specific cellEdited
            });

            return table;
        }

        function updateField(cell, updateUrl) {
            const rowData = cell.getData();
            const fieldName = cell.getField();
            const fieldValue = cell.getValue();

            // Create payload with specific field data
            const payload = {
                channel: rowData.channel,
                [fieldName]: fieldValue
            };

            console.log('Sending update payload:', payload);

            fetch(updateUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        cell.getElement().innerHTML += '<span class="save-indicator text-success">Saved</span>';
                        setTimeout(() => {
                            const indicator = cell.getElement().querySelector('.save-indicator');
                            if (indicator) indicator.remove();
                        }, 2000);
                        const selectedSite = document.getElementById('ahmChartSearch');
                        if (selectedSite && String(selectedSite.value).trim().toLowerCase() === String(rowData.channel || '').trim().toLowerCase()) {
                            loadAhmChart();
                        }
                        console.log(`Updated ${fieldName}:`, response);
                    } else {
                        alert(`Error: ${response.message || 'Failed to save field'}`);
                        cell.restoreOldValue();
                    }
                })
                .catch(error => {
                    console.error(`Update failed for ${fieldName}:`, error);
                    alert('Server error while saving field');
                    cell.restoreOldValue();
                });
        }

        function updateL30OrdersTotal(data) {
            let l30OrdersTotal = 0;
            data.forEach(row => {
                const l30Orders = parseNumber(row['l30_orders'] || row['L30 Orders'] || 0);
                l30OrdersTotal += l30Orders;
            });
            const badgeElement = document.getElementById('l30OrdersCountBadgeHeader');
            if (badgeElement) {
                badgeElement.textContent = Math.round(l30OrdersTotal).toLocaleString('en-US');
            }
        }

        const ahmPalette = {
            odr_rate: '#ef4444',
            fulfillment_rate: '#2563eb',
            valid_tracking_rate: '#059669',
            late_shipment_rate: '#d97706',
            on_time_delivery: '#7c3aed',
            negative_seller_rate: '#db2777',
            atoz_claims_rate: '#0891b2',
            violation_rate: '#ea580c',
            refund_rate: '#4f46e5'
        };

        function marketplaceNames(data) {
            const rows = data || originalChannelData || [];
            const names = [];
            rows.forEach(row => {
                const name = String(row.channel || row.Channel || row['Channel '] || '').trim();
                if (name && names.indexOf(name) === -1) names.push(name);
            });
            names.sort((a, b) => a.localeCompare(b));
            return names;
        }

        function rememberMarketplace(data) {
            const saved = localStorage.getItem('ahm-dash-chart-channel') || '';
            const match = marketplaceNames(data).find(name => name.toLowerCase() === saved.toLowerCase());
            if (!match) return;
            jq('#ahmChartSearch').val(match);
            setTimeout(function() {
                if (table) table.setFilter('channel', '=', match);
            }, 0);
            loadAhmChart();
        }

        function selectedMarketplace() {
            const chartSearch = document.getElementById('ahmChartSearch');
            const typed = chartSearch ? String(chartSearch.value || '').trim() : '';
            if (!typed) return '';
            return marketplaceNames().find(name => name.toLowerCase() === typed.toLowerCase()) || '';
        }

        function formatEntryDate(iso) {
            if (!iso) return '';
            const date = new Date(String(iso).slice(0, 10) + 'T00:00:00');
            if (Number.isNaN(date.getTime())) return String(iso);
            return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
        }

        function formatMetricValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            const number = Number(value);
            if (!Number.isFinite(number)) return String(value);
            return Math.abs(number) >= 100 ? number.toLocaleString('en-US', { maximumFractionDigits: 1 }) : String(Math.round(number * 100) / 100);
        }

        function destroyAhmCharts() {
            ahmCharts.forEach(chart => {
                try { chart.destroy(); } catch (e) {}
            });
            ahmCharts = [];
        }

        function chooseMarketplace(name, options) {
            const clean = String(name || '').trim();
            const filterTable = !options || options.filterTable !== false;
            jq('#ahmChartSearch').val(clean);
            jq('.dropdown-search-results').hide();
            if (clean) {
                localStorage.setItem('ahm-dash-chart-channel', clean);
                if (filterTable && table && !isPlaying) table.setFilter('channel', '=', clean);
            } else {
                localStorage.removeItem('ahm-dash-chart-channel');
                if (filterTable && table && !isPlaying) table.clearFilter();
                destroyAhmCharts();
                const grid = document.getElementById('ahmMetricGrid');
                const empty = document.getElementById('ahmChartEmpty');
                const title = document.getElementById('ahmChartTitle');
                const subtitle = document.getElementById('ahmChartSubtitle');
                if (grid) grid.innerHTML = '';
                if (title) title.textContent = 'Marketplace columns';
                if (subtitle) subtitle.textContent = 'Search a marketplace. Each column gets its own graph and updated date.';
                if (empty) {
                    empty.style.display = 'block';
                    empty.textContent = 'Search a marketplace to see a graph for every column.';
                }
                return;
            }
            loadAhmChart();
        }

        function loadAhmChart() {
            const channel = selectedMarketplace();
            const empty = document.getElementById('ahmChartEmpty');
            const grid = document.getElementById('ahmMetricGrid');
            if (!channel) {
                destroyAhmCharts();
                if (grid) grid.innerHTML = '';
                if (empty) {
                    empty.style.display = 'block';
                    empty.textContent = 'Search a marketplace to see a graph for every column.';
                }
                return;
            }
            const url = '/account-health-master/dashboard/series?channel=' + encodeURIComponent(channel);
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.ok ? res.json() : Promise.reject(res))
                .then(payload => renderMarketplaceCharts(payload))
                .catch(() => {
                    destroyAhmCharts();
                    if (grid) grid.innerHTML = '';
                    if (empty) {
                        empty.style.display = 'block';
                        empty.textContent = 'Could not load the graphs for this marketplace.';
                    }
                });
        }

        function renderMarketplaceCharts(payload) {
            const grid = document.getElementById('ahmMetricGrid');
            const empty = document.getElementById('ahmChartEmpty');
            const title = document.getElementById('ahmChartTitle');
            const subtitle = document.getElementById('ahmChartSubtitle');
            const columns = (payload && payload.columns) ? payload.columns : [];
            destroyAhmCharts();
            if (grid) grid.innerHTML = '';
            if (title) title.textContent = (payload && payload.channel) ? payload.channel : 'Marketplace';
            if (subtitle) subtitle.textContent = 'One graph per column, plotted on the date the value was updated.';
            if (!grid || !columns.length || typeof Chart === 'undefined') {
                if (empty) {
                    empty.style.display = 'block';
                    empty.textContent = 'No columns to chart for this marketplace.';
                }
                return;
            }
            if (empty) empty.style.display = 'none';
            columns.forEach(column => {
                const color = ahmPalette[column.key] || '#2563eb';
                const card = document.createElement('div');
                card.className = 'ahm-metric-card';
                const updated = column.updated_on ? ('Updated ' + formatEntryDate(column.updated_on)) : 'No current entry';
                const allowedUpdated = column.allowed_updated_on ? ('Allowed ' + formatEntryDate(column.allowed_updated_on)) : '';
                card.innerHTML =
                    '<div class="ahm-metric-head">' +
                        '<div class="ahm-metric-title"><span class="ahm-metric-dot" style="background:' + color + '"></span><span></span></div>' +
                        '<div class="ahm-metric-date"></div>' +
                    '</div>' +
                    '<div class="ahm-metric-canvas"><canvas></canvas></div>' +
                    '<div class="ahm-metric-foot"><span></span><span></span></div>';
                card.querySelector('.ahm-metric-title span:last-child').textContent = column.label || column.key;
                card.querySelector('.ahm-metric-date').textContent = allowedUpdated ? (updated + '\n' + allowedUpdated) : updated;
                card.querySelector('.ahm-metric-foot span:first-child').textContent = 'Current ' + formatMetricValue(column.latest);
                card.querySelector('.ahm-metric-foot span:last-child').textContent = 'Allowed ' + (column.latest_allowed || '—');
                grid.appendChild(card);

                const dates = [];
                (column.points || []).forEach(point => { if (dates.indexOf(point.date) === -1) dates.push(point.date); });
                (column.allowed_points || []).forEach(point => { if (dates.indexOf(point.date) === -1) dates.push(point.date); });
                dates.sort();
                const currentMap = {};
                const allowedMap = {};
                (column.points || []).forEach(point => { currentMap[point.date] = point.value; });
                (column.allowed_points || []).forEach(point => { allowedMap[point.date] = point.value; });
                const canvas = card.querySelector('canvas');
                if (!dates.length || !canvas) {
                    const holder = card.querySelector('.ahm-metric-canvas');
                    if (holder) holder.innerHTML = '<div class="small text-muted text-center" style="padding-top:42px;">No dated entries yet</div>';
                    return;
                }
                const datasets = [
                    {
                        label: 'Current',
                        data: dates.map(day => currentMap[day] === undefined ? null : currentMap[day]),
                        borderColor: color,
                        backgroundColor: color + '33',
                        pointBackgroundColor: '#fff',
                        pointBorderColor: color,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        spanGaps: false
                    }
                ];
                if ((column.allowed_points || []).length) {
                    datasets.push({
                        label: 'Allowed',
                        data: dates.map(day => allowedMap[day] === undefined ? null : allowedMap[day]),
                        borderColor: '#0f172a',
                        backgroundColor: 'transparent',
                        pointBackgroundColor: '#0f172a',
                        pointRadius: 3,
                        borderWidth: 1.5,
                        borderDash: [5, 4],
                        fill: false,
                        tension: 0.2,
                        spanGaps: false
                    });
                }
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: dates.map(formatEntryDate),
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 4 } },
                            y: { beginAtZero: true, ticks: { font: { size: 10 } } }
                        }
                    }
                });
                ahmCharts.push(chart);
            });
        }

        function historyCell(value) {
            const text = document.createElement('div');
            text.textContent = value || '';
            return text.innerHTML;
        }

        function renderAhmHistoryRows() {
            const body = document.getElementById('ahmHistoryBody');
            const empty = document.getElementById('ahmHistoryEmpty');
            const filter = document.getElementById('ahmHistoryColumn');
            const metric = filter ? filter.value : '';
            const rows = ahmHistoryRows.filter(row => !metric || row.metric_key === metric || row.metric === metric);
            if (!body) return;
            body.innerHTML = '';
            if (!rows.length) {
                if (empty) {
                    empty.style.display = 'block';
                    empty.textContent = 'No entries for this column yet.';
                }
                return;
            }
            if (empty) empty.style.display = 'none';
            body.innerHTML = rows.map(row => {
                return '<tr><td>' + historyCell(formatEntryDate(row.updated_on || row.at)) + '</td><td>' + historyCell(row.at) +
                    '</td><td>' + historyCell(row.user_name) + '</td><td>' + historyCell(row.metric) +
                    '</td><td>' + historyCell(row.column) + '</td><td>' + historyCell(row.old_value) +
                    '</td><td>' + historyCell(row.new_value) + '</td></tr>';
            }).join('');
        }

        function openAhmHistory() {
            const channel = selectedMarketplace();
            if (!channel) {
                alert('Search and select a marketplace first.');
                return;
            }
            const title = document.getElementById('ahmHistoryChannel');
            const body = document.getElementById('ahmHistoryBody');
            const loading = document.getElementById('ahmHistoryLoading');
            const empty = document.getElementById('ahmHistoryEmpty');
            const filter = document.getElementById('ahmHistoryColumn');
            if (title) title.textContent = '— ' + channel;
            if (body) body.innerHTML = '';
            if (loading) loading.style.display = 'block';
            if (empty) empty.style.display = 'none';
            ahmHistoryRows = [];
            fetch('/account-health-master/dashboard/history?channel=' + encodeURIComponent(channel), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.ok ? res.json() : Promise.reject(res)).then(payload => {
                if (loading) loading.style.display = 'none';
                ahmHistoryRows = (payload && payload.rows) ? payload.rows : [];
                if (filter) {
                    const current = filter.value;
                    const seen = {};
                    filter.innerHTML = '<option value="">All columns</option>';
                    ahmHistoryRows.forEach(row => {
                        const key = row.metric_key || row.metric;
                        if (!key || seen[key]) return;
                        seen[key] = true;
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = row.metric || key;
                        filter.appendChild(option);
                    });
                    filter.value = seen[current] ? current : '';
                }
                if (!ahmHistoryRows.length) {
                    if (empty) {
                        empty.style.display = 'block';
                        empty.textContent = 'No entries for this marketplace yet.';
                    }
                    return;
                }
                renderAhmHistoryRows();
            }).catch(() => {
                if (loading) loading.style.display = 'none';
                if (empty) {
                    empty.style.display = 'block';
                    empty.textContent = 'Could not load history.';
                }
            });
        }

        document.getElementById('ahmHistoryBtn')?.addEventListener('click', function (event) {
            if (!selectedMarketplace()) {
                event.preventDefault();
                event.stopPropagation();
                alert('Search and select a marketplace first.');
                return;
            }
            openAhmHistory();
        });
        document.getElementById('ahmHistoryColumn')?.addEventListener('change', renderAhmHistoryRows);

        function startPlayback() {
            if (!originalChannelData.length) return;
            uniqueChannels = [...new Set(originalChannelData.map(item => item['channel']?.trim() || item['Channel ']
                ?.trim() || item['Channel']?.trim()))].filter(Boolean);
            uniqueChannelRows = uniqueChannels.map(channel => originalChannelData.find(item => (item['channel']?.trim() ||
                item['Channel ']?.trim() || item['Channel']?.trim()) === channel));
            if (!uniqueChannelRows.length) return;

            currentChannelIndex = 0;
            isPlaying = true;
            table.setPageSize(1);
            table.clearFilter();
            showCurrentChannel();

            jq('#play-auto').hide();
            jq('#play-pause').show();
            setTimeout(() => updatePlayButtonColor(), 500);
        }

        function stopPlayback() {
            isPlaying = false;
            const name = selectedMarketplace();
            const done = table.setData(originalChannelData);
            const applyFilter = function() {
            table.setPageSize(50);
                if (name) table.setFilter('channel', '=', name);
            };
            if (done && typeof done.then === 'function') done.then(applyFilter);
            else applyFilter();
            jq('#play-auto').show();
            jq('#play-pause').hide();
        }

        function showCurrentChannel() {
            if (!isPlaying || !uniqueChannelRows.length) return;
            const currentRow = uniqueChannelRows[currentChannelIndex];
            if (currentRow) {
                table.setData([currentRow]);
                const playingName = currentRow['channel']?.trim() || currentRow['Channel ']?.trim() || currentRow['Channel']?.trim() || '';
                jq('#ahmChartSearch').val(playingName);
                if (playingName) {
                    localStorage.setItem('ahm-dash-chart-channel', playingName);
                    loadAhmChart();
                }
                const tableContainer = jq('#channelTable').parent();
                if (tableContainer.length) tableContainer.scrollTop(0);
                setTimeout(() => updatePlayButtonColor(), 500);
            }
        }

        function nextChannel() {
            if (!isPlaying) return;
            if (currentChannelIndex < uniqueChannelRows.length - 1) {
                currentChannelIndex++;
                showCurrentChannel();
            } else {
                stopPlayback();
            }
        }

        function previousChannel() {
            if (!isPlaying) return;
            if (currentChannelIndex > 0) {
                currentChannelIndex--;
                showCurrentChannel();
            }
        }

        function updatePlayButtonColor() {
            const rows = table.getRows();
            if (!rows.length) return;
            const row = rows[0];
            const checkbox = jq(row.getElement()).find('.checkbox-nr');
            if (checkbox.length) {
                const isChecked = checkbox.prop('checked');
                jq('#play-pause').removeClass('btn-light btn-success btn-danger')
                    .addClass(isChecked ? 'btn-success' : 'btn-danger')
                    .css('color', 'white');
            }
        }

        function placeMarketplaceMenu(input, dropdown) {
            const rect = input.get(0).getBoundingClientRect();
            dropdown.css({
                top: (rect.bottom + 4) + 'px',
                left: rect.left + 'px',
                width: Math.max(rect.width, 260) + 'px'
            });
        }

        function renderMarketplaceMenu(input, dropdown) {
            const term = String(input.val() || '').trim().toLowerCase();
            const matches = marketplaceNames().filter(name => !term || name.toLowerCase().includes(term));
            dropdown.empty();
            if (!matches.length) {
                dropdown.append('<div class="dropdown-search-empty">No marketplace found</div>');
            } else {
                matches.forEach(name => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-search-item';
                    item.dataset.value = name;
                    item.textContent = name;
                    dropdown.append(item);
                });
            }
            placeMarketplaceMenu(input, dropdown);
            dropdown.show();
        }

        function bindMarketplaceSearch(inputSelector, dropdownSelector) {
            const input = jq(inputSelector);
            const dropdown = jq(dropdownSelector);
            input.on('focus', function() { renderMarketplaceMenu(input, dropdown); });
            input.on('input', function() {
                const typed = String(this.value || '').trim();
                if (typed === '') chooseMarketplace('');
                renderMarketplaceMenu(input, dropdown);
            });
            input.on('keydown', function(event) {
                const items = dropdown.find('.dropdown-search-item');
                const active = items.filter('.active');
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (!items.length) return;
                    let next = event.key === 'ArrowDown' ? items.first() : items.last();
                    if (active.length) {
                        next = event.key === 'ArrowDown' ? active.next('.dropdown-search-item') : active.prev('.dropdown-search-item');
                        if (!next.length) next = event.key === 'ArrowDown' ? items.first() : items.last();
                    }
                    items.removeClass('active');
                    next.addClass('active');
                    return;
                }
                if (event.key === 'Enter') {
                    event.preventDefault();
                    const pick = (active.data('value') || items.first().data('value') || '');
                    if (pick) chooseMarketplace(pick);
                    return;
                }
                if (event.key === 'Escape') dropdown.hide();
            });
            dropdown.on('click', '.dropdown-search-item', function() {
                chooseMarketplace(jq(this).data('value'));
            });
        }

        jq(document).ready(function() {
            jq('#customLoader').show();

            table = initializeTable();
            if (!table) return;

            table.on('dataProcessed', function() {
                requestAnimationFrame(function() {
                    if (table) {
                        table.setHeight('560px');
                    }
                });
            });

            jq.ajax({
                url: '/account-health-master/dashboard-data',
                type: "GET",
                success: function(json) {
                    console.log('Initial AJAX response:', json);
                    if (json && json.data) {
                        originalChannelData = json.data;
                        setupEventHandlers();
                    } else {
                        console.warn('No data in initial AJAX response:', json);
                        alert('No data received from server.');
                    }
                    jq('#customLoader').hide();
                },
                error: function(xhr, error, thrown) {
                    console.error('Error loading initial data:', error, thrown, xhr.responseText);
                    jq('#customLoader').hide();
                    alert('Failed to load initial data: ' + (xhr.responseJSON?.message ||
                        'Unknown error. Check console for details.'));
                }
            });
        });

        function setupEventHandlers() {
            jq('#play-auto').on('click', startPlayback);
            jq('#play-pause').on('click', stopPlayback);
            jq('#play-forward').on('click', nextChannel);
            jq('#play-backward').on('click', previousChannel);

            bindMarketplaceSearch('#ahmChartSearch', '#ahmChartSearchDropdown');

            jq(window).on('scroll resize', function() {
                if (jq('#ahmChartSearchDropdown').is(':visible')) {
                    placeMarketplaceMenu(jq('#ahmChartSearch'), jq('#ahmChartSearchDropdown'));
                }
            });

            jq(document).on('click', function(e) {
                if (!e.target.closest('.dropdown-search-container')) {
                    jq('.dropdown-search-results').hide();
                }
            });

            table.on("dataLoaded", function(data) {
                updateL30OrdersTotal(data);
            });

            setupModalHandlers();
            setupCheckboxHandlers();

            table.on("cellClick", function(e, cell) {
                if (jq(e.target).closest('.edit-btn').length) {
                    const rowData = cell.getData();
                    const channel = rowData['channel']?.trim() || '';
                    const sheetUrl = rowData['sheet_link'] || '';
                    const type = rowData['type']?.trim() || '';

                    jq('#editChannelName').val(channel);
                    jq('#editChannelUrl').val(sheetUrl);
                    jq('#editType').val(type);
                    jq('#originalChannel').val(channel);
                    jq('#editChannelModal').modal('show');
                }
            });
        }

        function setupModalHandlers() {
            jq('#saveChannelBtn').on('click', function() {
                const channelName = jq('#channelName').val().trim();
                const channelUrl = jq('#channelUrl').val().trim();
                const type = jq('#type').val().trim();

                if (!channelName || !channelUrl || !type) {
                    alert("Channel Name, Sheet Link, and Type are required.");
                    return;
                }

                jq.ajax({
                    url: '/channel_master/store',
                    method: 'POST',
                    data: {
                        channel: channelName,
                        sheet_link: channelUrl,
                        type: type,
                        _token: jq('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            jq('#addChannelModal').modal('hide');
                            jq('#channelForm')[0].reset();
                            table.setData('/account-health-master/dashboard-data');
                            alert('Channel added successfully.');
                        } else {
                            alert("Error: " + (res.message || 'Failed to add channel.'));
                        }
                    },
                    error: function() {
                        alert("Server error while adding channel.");
                    }
                });
            });

            jq('#editChannelForm').on('submit', function(e) {
                e.preventDefault();
                const channel = jq('#editChannelName').val().trim();
                const sheetUrl = jq('#editChannelUrl').val().trim();
                const type = jq('#editType').val().trim();
                const originalChannel = jq('#originalChannel').val().trim();

                if (!channel || !sheetUrl || !type) {
                    alert("Channel Name, Sheet URL, and Type are required.");
                    return;
                }

                jq.ajax({
                    url: '/channel_master/update',
                    method: 'POST',
                    data: {
                        channel: channel,
                        sheet_url: sheetUrl,
                        type: type,
                        original_channel: originalChannel,
                        _token: jq('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            jq('#editChannelModal').modal('hide');
                            jq('#editChannelForm')[0].reset();
                            table.setData('/account-health-master/dashboard-data');
                            alert('Channel updated successfully.');
                        } else {
                            alert("Error: " + (res.message || 'Failed to update channel.'));
                        }
                    },
                    error: function() {
                        alert("Server error while updating channel.");
                    }
                });
            });

            const importForm = jq('#accountHealthImportForm');
            const progressBar = jq('#importProgress');
            const resultsDiv = jq('#importResults');
            const submitBtn = jq('#importSubmitBtn');

            importForm.on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                progressBar.show();
                resultsDiv.hide();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Importing...');

                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress += 10;
                    progressBar.find('.progress-bar').css('width', progress + '%').text(progress + '%');
                    if (progress >= 90) clearInterval(progressInterval);
                }, 200);

                fetch(this.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': jq('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        clearInterval(progressInterval);
                        progressBar.find('.progress-bar').css('width', '100%').text('100%');
                        setTimeout(() => {
                            progressBar.hide();
                            if (data.success) {
                                resultsDiv.removeClass('alert-danger').addClass('alert-success');
                                const resultsList = jq('#importResultsList').empty();
                                if (data.results) {
                                    Object.keys(data.results).forEach(key => {
                                        resultsList.append(
                                            `<li>${key}: ${data.results[key]}</li>`);
                                    });
                                }
                                resultsDiv.show();
                                table.setData('/account-health-master/dashboard-data');
                                setTimeout(() => {
                                    jq('#accountHealthImportModal').modal('hide');
                                    location.reload();
                                }, 3000);
                            } else {
                                resultsDiv.removeClass('alert-success').addClass('alert-danger');
                                resultsDiv.html(
                                    `<strong>Error:</strong> ${data.message || 'Import failed'}`);
                                resultsDiv.show();
                            }
                        }, 500);
                    })
                    .catch(error => {
                        clearInterval(progressInterval);
                        progressBar.hide();
                        resultsDiv.removeClass('alert-success').addClass('alert-danger');
                        resultsDiv.html('<strong>Error:</strong> Network error occurred during import');
                        resultsDiv.show();
                        console.error('Import error:', error);
                    })
                    .finally(() => {
                        submitBtn.prop('disabled', false).html(
                            '<i class="fas fa-file-import me-1"></i> Import');
                    });
            });

            jq('#accountHealthImportModal').on('hidden.bs.modal', function() {
                importForm[0].reset();
                progressBar.hide();
                resultsDiv.hide();
                submitBtn.prop('disabled', false).html('<i class="fas fa-file-import me-1"></i> Import');
            });
        }

        function setupCheckboxHandlers() {
            jq('#channelTable').on('change', '.checkbox-nr', function() {
                const channel = jq(this).data('channel');
                const value = jq(this).is(':checked') ? 1 : 0;

                jq.ajax({
                    url: '/channels-master/toggle-flag',
                    method: 'POST',
                    data: {
                        channel: channel,
                        field: 'nr',
                        value: value,
                        _token: jq('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (!res.success) {
                            alert('Failed to update NR flag: ' + (res.message || 'Unknown error'));
                            jq(this).prop('checked', !value);
                        }
                    },
                    error: function() {
                        alert('Server error while updating NR flag.');
                        jq(this).prop('checked', !value);
                    }
                });
            });
        }
    </script>
@endsection
