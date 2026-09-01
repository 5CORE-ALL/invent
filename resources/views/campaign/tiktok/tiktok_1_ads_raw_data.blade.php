@extends('layouts.vertical', ['title' => 'Tiktok 1 Sheet Ads', 'sidenav' => 'condensed'])

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
        .badge-tt1-ads-raw:not(.bg-primary) {
            color: #000 !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Tiktok 1 Sheet Ads',
        'sub_title'  => 'Upload TikTok ads export into tiktok_campaign_reports. Old rows for the chosen range are truncated first.',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge badge-tt1-ads-raw bg-primary" id="stat-tt1-ads-raw">
                        Rows <span id="total-tt1-ads-raw">0</span>
                    </span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-cost-l30-badge"
                          style="background-color: #a5d6e8;">Cost L30: $0.00</span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-cost-l1-badge"
                          style="background-color: #c5e4f3;">Cost L1: $0.00</span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-orders-l30-badge"
                          style="background-color: #cfe2ff;">SKU orders L30: 0</span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-orders-l1-badge"
                          style="background-color: #d7e3fc;">SKU orders L1: 0</span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-revenue-l30-badge"
                          style="background-color: #9ec5fe;">Revenue L30: $0.00</span>
                    <span class="badge badge-tt1-ads-raw" id="tt1-revenue-l1-badge"
                          style="background-color: #b8cfe5;">Revenue L1: $0.00</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="tt1-ads-raw-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                    <span class="text-muted" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-upload me-1"></i>Upload (xlsx / csv / txt). Old range is deleted first.
                    </span>
                    <input type="file" id="tt1-l1-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                    <input type="file" id="tt1-l7-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                    <input type="file" id="tt1-l30-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                    <button type="button" id="tt1-l1-upload" class="btn btn-sm btn-primary">L1</button>
                    <button type="button" id="tt1-l7-upload" class="btn btn-sm btn-primary">L7</button>
                    <button type="button" id="tt1-l30-upload" class="btn btn-sm btn-primary">L30</button>
                    <span id="tt1-upload-status" class="ms-2" style="font-size: 0.85rem;"></span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="tt1-ads-raw-search" class="form-control form-control-sm"
                           placeholder="Search campaign, product, video, status…">
                </div>
                <div id="tt1-ads-raw-table" style="height: calc(100vh - 320px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function money(n) {
        return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function applyTt1Sums(s) {
        const sums = s || {};
        $('#total-tt1-ads-raw').text(Number(sums.count || 0).toLocaleString('en-US'));
        $('#tt1-cost-l30-badge').text('Cost L30: ' + money(sums.cost_l30));
        $('#tt1-cost-l1-badge').text('Cost L1: ' + money(sums.cost_l1));
        $('#tt1-orders-l30-badge').text('SKU orders L30: ' + Number(sums.orders_l30 || 0).toLocaleString('en-US'));
        $('#tt1-orders-l1-badge').text('SKU orders L1: ' + Number(sums.orders_l1 || 0).toLocaleString('en-US'));
        $('#tt1-revenue-l30-badge').text('Revenue L30: ' + money(sums.revenue_l30));
        $('#tt1-revenue-l1-badge').text('Revenue L1: ' + money(sums.revenue_l1));
    }

    function numCol(title, field, width) {
        return {
            title: title,
            field: field,
            width: width || 110,
            hozAlign: 'right',
            sorter: 'number',
            headerSort: true,
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
                applyTt1Sums(response && response.sums ? response.sums : { count: data.length });
                return data;
            },
            layout: 'fitData',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: 'cost', dir: 'desc' }],
            placeholder: 'No rows in tiktok_campaign_reports. Upload an L1 / L7 / L30 export.',
            columns: [
                { title: 'ID', field: 'id', width: 80, hozAlign: 'right', sorter: 'number' },
                { title: 'SKU', field: 'sku', minWidth: 160 },
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
                { title: 'Exploration status', field: 'custom_status', width: 150 },
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
            table.download('csv', 'tt1_ads.csv');
        });

        function uploadTt1(fileInput, reportRange) {
            const file = fileInput.files[0];
            if (!file) return;
            const $status = $('#tt1-upload-status');
            $status.html('<span class="text-primary">Uploading ' + reportRange + '… old rows will be truncated.</span>');
            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_range', reportRange);
            $.ajax({
                url: "{{ route('tiktok.utilized.upload') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response && response.success) {
                        $status.html('<span class="text-success">' + (response.message || 'Done') + '</span>');
                        table.replaceData();
                    } else {
                        $status.html('<span class="text-danger">' + ((response && response.message) || 'Upload failed') + '</span>');
                    }
                    fileInput.value = '';
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed';
                    $status.html('<span class="text-danger">' + msg + '</span>');
                    fileInput.value = '';
                }
            });
        }

        $('#tt1-l1-upload').on('click', function() {
            $('#tt1-l1-file').off('change').on('change', function() { uploadTt1(this, 'L1'); }).trigger('click');
        });
        $('#tt1-l7-upload').on('click', function() {
            $('#tt1-l7-file').off('change').on('change', function() { uploadTt1(this, 'L7'); }).trigger('click');
        });
        $('#tt1-l30-upload').on('click', function() {
            $('#tt1-l30-file').off('change').on('change', function() { uploadTt1(this, 'L30'); }).trigger('click');
        });
    });
</script>
@endsection
