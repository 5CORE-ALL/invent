@extends('layouts.vertical', ['title' => 'All Raw Meta Ads', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #meta-raw-table .tabulator-header .tabulator-col-title {
            font-size: 11.5px;
            font-weight: 600;
        }
        .stat-card {
            border-radius: 10px;
            padding: 12px 16px;
            min-width: 140px;
        }
        .stat-card .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.85;
        }
        .stat-card .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
        }
        .json-modal-pre {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 16px;
            border-radius: 8px;
            max-height: 65vh;
            overflow: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .status-badge {
            font-size: 0.75rem;
            text-transform: capitalize;
        }
        .filter-bar {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px 14px;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'All Raw Meta Ads',
        'sub_title' => 'Saved raw ad records from Meta API sync',
    ])

    <div class="row g-3 mb-3">
        <div class="col-12">
            <h5 class="fw-bold text-success mb-2"><i class="fa fa-dollar-sign me-2"></i>Shopify Sales & Orders (exact attribution)</h5>
            <div class="d-flex flex-wrap gap-3" id="sales-stats-row">
                <div class="stat-card bg-success text-white">
                    <div class="stat-label">Sales L7</div>
                    <div class="stat-value" id="stat-sales-l7">—</div>
                </div>
                <div class="stat-card bg-success text-white">
                    <div class="stat-label">Sales L30</div>
                    <div class="stat-value" id="stat-sales-l30">—</div>
                </div>
                <div class="stat-card bg-primary text-white">
                    <div class="stat-label">Orders L7</div>
                    <div class="stat-value" id="stat-orders-l7">—</div>
                </div>
                <div class="stat-card bg-primary text-white">
                    <div class="stat-label">Orders L30</div>
                    <div class="stat-value" id="stat-orders-l30">—</div>
                </div>
                <div class="stat-card bg-info text-white">
                    <div class="stat-label">Sessions L30</div>
                    <div class="stat-value" id="stat-sessions-l30">—</div>
                </div>
                <div class="stat-card bg-dark text-white">
                    <div class="stat-label">Campaigns With Sales L30</div>
                    <div class="stat-value" id="stat-campaigns-with-sales">—</div>
                </div>
            </div>
            <small class="text-muted" id="shopify-sync-note">Sales/orders from Shopify API by campaign ID (not Meta Pixel).</small>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-3">
                <div class="stat-card bg-primary text-white">
                    <div class="stat-label">Total Saved</div>
                    <div class="stat-value">{{ number_format($totalRecords) }}</div>
                </div>
                <div class="stat-card bg-success text-white">
                    <div class="stat-label">Latest Sync</div>
                    <div class="stat-value">{{ $latestSyncDate ?? '—' }}</div>
                </div>
                <div class="stat-card bg-info text-white">
                    <div class="stat-label">Latest Sync Ads</div>
                    <div class="stat-value">{{ number_format($latestCount) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h4 class="mb-0">All Raw Meta Ads</h4>
                    <div class="d-flex gap-2">
                        <a href="{{ route('meta.ads.raw') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-cloud me-1"></i> Live API Fetch
                        </a>
                        <button type="button" class="btn btn-sm btn-success" id="export-btn">
                            <i class="fa fa-file-excel me-1"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center filter-bar mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label class="fw-semibold text-muted mb-0" style="font-size:0.82rem;">Sync Date:</label>
                        <select id="sync-date-filter" class="form-select form-select-sm" style="width: 180px;">
                            <option value="latest" selected>Latest ({{ $latestSyncDate ?? 'none' }})</option>
                            <option value="all">All Dates</option>
                            @foreach ($syncDates as $date)
                                <option value="{{ $date }}">{{ $date }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="vr mx-1"></div>
                    <input type="text" id="search-input" class="form-control form-control-sm"
                        placeholder="Search ad name, ID, campaign..." style="max-width: 280px;">
                    <button type="button" class="btn btn-sm btn-primary" id="reload-btn">
                        <i class="fa fa-sync me-1"></i> Reload
                    </button>
                </div>
            </div>

            <div class="card-body pt-0" style="padding-bottom: 0;">
                <div id="meta-raw-table-wrapper" style="height: calc(100vh - 320px);">
                    <div id="meta-raw-table" style="height: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="raw-json-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Raw JSON — <span id="modal-ad-name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre class="json-modal-pre" id="modal-json-content"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="copy-json-btn">
                        <i class="fa fa-copy me-1"></i> Copy JSON
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        let modalJson = null;

        function statusFormatter(cell) {
            const value = (cell.getValue() || '').toLowerCase();
            const colors = {
                active: 'success',
                paused: 'warning',
                archived: 'secondary',
                deleted: 'danger',
            };
            const color = colors[value] || 'light';
            const textClass = color === 'warning' || color === 'light' ? 'text-dark' : 'text-white';
            return `<span class="badge bg-${color} status-badge ${textClass}">${value || 'unknown'}</span>`;
        }

        function linkFormatter(cell) {
            const url = cell.getValue();
            if (!url) return '—';
            return `<a href="${url}" target="_blank" rel="noopener">Preview</a>`;
        }

        function reloadTable() {
            if (table) {
                table.setData();
            }
            loadSalesStats();
        }

        function moneyFormatter(cell) {
            const value = parseFloat(cell.getValue()) || 0;
            return '$' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function loadSalesStats() {
            const syncDate = $('#sync-date-filter').val();
            $.get("{{ route('meta.ads.saved.raw.sales.stats') }}", {
                sync_date: syncDate === 'latest' || syncDate === 'all' ? '' : syncDate,
                latest_only: syncDate === 'latest' ? 1 : 0,
                search: $('#search-input').val().trim(),
            }).done(function(response) {
                if (!response.success || !response.stats) return;
                const s = response.stats;
                $('#stat-sales-l7').text('$' + Number(s.sales_l7 || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#stat-sales-l30').text('$' + Number(s.sales_l30 || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                $('#stat-orders-l7').text(Number(s.orders_l7 || 0).toLocaleString());
                $('#stat-orders-l30').text(Number(s.orders_l30 || 0).toLocaleString());
                $('#stat-sessions-l30').text(Number(s.sessions_l30 || 0).toLocaleString());
                $('#stat-campaigns-with-sales').text(Number(s.campaigns_with_sales_l30 || 0).toLocaleString());
                if (s.shopify_synced_at) {
                    $('#shopify-sync-note').text('Shopify data last synced: ' + s.shopify_synced_at);
                }
            });
        }

        $(document).ready(function() {
            loadSalesStats();

            table = new Tabulator("#meta-raw-table", {
                ajaxURL: "{{ route('meta.ads.saved.raw.data') }}",
                ajaxParams: function() {
                    const syncDate = $('#sync-date-filter').val();
                    return {
                        sync_date: syncDate === 'latest' || syncDate === 'all' ? '' : syncDate,
                        latest_only: syncDate === 'latest' ? 1 : 0,
                        search: $('#search-input').val().trim(),
                    };
                },
                ajaxResponse: function(url, params, response) {
                    return {
                        last_page: response.last_page,
                        data: response.data,
                    };
                },
                pagination: true,
                paginationMode: "remote",
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 200, 500],
                paginationCounter: "rows",
                layout: "fitDataStretch",
                placeholder: "No raw Meta ads found",
                initialSort: [{ column: "ad_name", dir: "asc" }],
                columns: [
                    {
                        title: "View",
                        field: "raw_data",
                        width: 70,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function() {
                            return '<button class="btn btn-sm btn-outline-primary view-json-btn"><i class="fa fa-code"></i></button>';
                        },
                        cellClick: function(e, cell) {
                            if (!$(e.target).closest('.view-json-btn').length) return;
                            const row = cell.getRow().getData();
                            modalJson = row.raw_data;
                            $('#modal-ad-name').text(row.ad_name || row.ad_id || 'Ad');
                            $('#modal-json-content').text(JSON.stringify(row.raw_data, null, 2));
                            new bootstrap.Modal(document.getElementById('raw-json-modal')).show();
                        },
                    },
                    { title: "Ad ID", field: "ad_id", width: 170, headerFilter: "input" },
                    { title: "Ad Name", field: "ad_name", minWidth: 220, headerFilter: "input" },
                    { title: "Sales L7", field: "sales_l7", width: 110, hozAlign: "right", sorter: "number", formatter: moneyFormatter },
                    { title: "Sales L30", field: "sales_l30", width: 115, hozAlign: "right", sorter: "number", formatter: moneyFormatter },
                    { title: "Orders L7", field: "orders_l7", width: 95, hozAlign: "center", sorter: "number" },
                    { title: "Orders L30", field: "orders_l30", width: 100, hozAlign: "center", sorter: "number" },
                    { title: "Sessions L30", field: "sessions_l30", width: 110, hozAlign: "center", sorter: "number", visible: false },
                    { title: "Campaign ID", field: "campaign_id", width: 170, headerFilter: "input" },
                    { title: "Campaign Name", field: "campaign_name", minWidth: 180, visible: false },
                    { title: "Ad Set ID", field: "adset_id", width: 170, visible: false },
                    { title: "Status", field: "status", width: 100, formatter: statusFormatter },
                    { title: "Sync Date", field: "sync_date", width: 110, sorter: "date" },
                    { title: "Updated", field: "ad_updated_time", width: 150, sorter: "datetime" },
                    { title: "Created", field: "ad_created_time", width: 150, visible: false },
                    { title: "Source Ad ID", field: "source_ad_id", width: 150, visible: false },
                    { title: "Preview", field: "preview_shareable_link", width: 90, formatter: linkFormatter, headerSort: false },
                ],
            });

            $('#reload-btn, #sync-date-filter').on('click change', function(e) {
                if (e.type === 'click' || e.target.id === 'sync-date-filter') {
                    reloadTable();
                }
            });

            let searchTimer = null;
            $('#search-input').on('keyup', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(reloadTable, 350);
            });

            $('#export-btn').on('click', function() {
                table.download("csv", "meta-raw-ads.csv");
            });

            $('#copy-json-btn').on('click', function() {
                if (!modalJson) return;
                navigator.clipboard.writeText(JSON.stringify(modalJson, null, 2)).then(function() {
                    const btn = $('#copy-json-btn');
                    const original = btn.html();
                    btn.html('<i class="fa fa-check me-1"></i> Copied!');
                    setTimeout(function() { btn.html(original); }, 1500);
                });
            });
        });
    </script>
@endsection
