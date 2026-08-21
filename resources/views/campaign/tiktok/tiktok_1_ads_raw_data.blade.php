@extends('layouts.vertical', ['title' => 'Tiktok 1 Ads Raw Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        #stat-tt1-ads-raw.badge,
        .badge-tt1-ads-raw {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Tiktok 1 Ads Raw Data',
        'sub_title'  => 'All rows saved to tiktok_campaign_reports — no filters',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge badge-tt1-ads-raw bg-primary" id="stat-tt1-ads-raw">
                        Rows <span id="total-tt1-ads-raw">0</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="tt1-ads-raw-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="tt1-ads-raw-search" class="form-control form-control-sm"
                           placeholder="Search campaign, product, video, status…">
                </div>
                <div id="tt1-ads-raw-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function numCol(title, field, width) {
        return {
            title: title,
            field: field,
            width: width || 110,
            hozAlign: 'right',
            sorter: 'number',
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                return Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 4 }) : String(v);
            },
        };
    }

    $(document).ready(function() {
        const table = new Tabulator('#tt1-ads-raw-table', {
            ajaxURL: "{{ route('tiktok1.ads.raw.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                $('#total-tt1-ads-raw').text(Number(response && response.count != null ? response.count : data.length).toLocaleString('en-US'));
                return data;
            },
            layout: 'fitData',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: 'id', dir: 'desc' }],
            placeholder: 'No rows in tiktok_campaign_reports.',
            columns: [
                { title: 'ID', field: 'id', width: 80, hozAlign: 'right', sorter: 'number' },
                { title: 'Campaign name', field: 'campaign_name', minWidth: 180 },
                { title: 'Campaign ID', field: 'campaign_id', minWidth: 140 },
                { title: 'Product ID', field: 'product_id', minWidth: 140 },
                { title: 'Report range', field: 'report_range', width: 120, hozAlign: 'center' },
                { title: 'Creative type', field: 'creative_type', minWidth: 130 },
                { title: 'Video title', field: 'video_title', minWidth: 200 },
                { title: 'Video ID', field: 'video_id', minWidth: 140 },
                { title: 'TikTok account', field: 'tiktok_account', minWidth: 150 },
                { title: 'Time posted', field: 'time_posted', minWidth: 160 },
                { title: 'Status', field: 'status', width: 110 },
                { title: 'Custom status', field: 'custom_status', width: 130 },
                { title: 'Authorization type', field: 'authorization_type', minWidth: 150 },
                numCol('Cost', 'cost', 100),
                numCol('Budget', 'budget', 100),
                numCol('SKU orders', 'sku_orders', 110),
                numCol('Cost per order', 'cost_per_order', 130),
                numCol('Gross revenue', 'gross_revenue', 130),
                numCol('ROI', 'roi', 90),
                numCol('In ROAS', 'in_roas', 100),
                { title: 'Currency', field: 'currency', width: 90, hozAlign: 'center' },
                numCol('Impressions', 'product_ad_impressions', 120),
                numCol('Clicks', 'product_ad_clicks', 100),
                numCol('Click rate', 'product_ad_click_rate', 110),
                numCol('Ad CVR', 'ad_conversion_rate', 100),
                numCol('2s view %', 'video_view_rate_2_second', 100),
                numCol('6s view %', 'video_view_rate_6_second', 100),
                numCol('25% view', 'video_view_rate_25_percent', 100),
                numCol('50% view', 'video_view_rate_50_percent', 100),
                numCol('75% view', 'video_view_rate_75_percent', 100),
                numCol('100% view', 'video_view_rate_100_percent', 110),
                { title: 'Created', field: 'created_at', minWidth: 160 },
                { title: 'Updated', field: 'updated_at', minWidth: 160 },
            ],
        });

        $('#tt1-ads-raw-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return Object.keys(row).some(function(key) {
                    return String(row[key] == null ? '' : row[key]).toLowerCase().includes(q);
                });
            });
        });

        $('#tt1-ads-raw-csv').on('click', function() {
            table.download('csv', 'tiktok_1_ads_raw_data.csv');
        });
    });
</script>
@endsection
