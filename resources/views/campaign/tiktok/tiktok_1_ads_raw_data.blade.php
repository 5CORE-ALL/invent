@extends('layouts.vertical', ['title' => 'Tiktok 1 Sheet Ads', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .tt1-badge-row {
            flex-wrap: wrap;
        }
        .tt1-stat-badge {
            display: inline-block;
            flex-shrink: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            line-height: 1.2;
        }
        .tt1-stat-badge--rows   { background: #0ea5e9; }
        .tt1-stat-badge--spend  { background: #ef4444; }
        .tt1-stat-badge--spend-l1 { background: #f87171; color: #111; }
        .tt1-stat-badge--clicks { background: #4c7ed8; }
        .tt1-stat-badge--clicks-l1 { background: #60a5fa; color: #111; }
        .tt1-stat-badge--sold   { background: #f59e0b; color: #111; }
        .tt1-stat-badge--sold-l1 { background: #fbbf24; color: #111; }
        .tt1-stat-badge--sales  { background: #16a34a; }
        .tt1-stat-badge--sales-l1 { background: #22c55e; color: #111; }
        .tt1-stat-badge--cvr    { background: #db2777; }
        .tt1-stat-badge--cvr-l1 { background: #ec4899; color: #111; }
        .tt1-range-btn {
            background: #0d9488;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            line-height: 1.2;
        }
        .tt1-range-btn:hover { filter: brightness(1.08); color: #fff; }
        .tt1-video-title-btn {
            color: #0d6efd;
            font-size: 1.1rem;
            line-height: 1;
        }
        .tt1-video-title-btn:hover { color: #0a58ca; }
        #tt1-video-title-modal-body {
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 70vh;
            overflow-y: auto;
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
                <div class="d-flex align-items-center gap-2 tt1-badge-row">
                    <span class="tt1-stat-badge tt1-stat-badge--rows">ROWS: <span id="total-tt1-ads-raw">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--spend">COST L30: <span id="tt1-cost-l30">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--spend-l1">COST L1: <span id="tt1-cost-l1">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--clicks">CLICKS L30: <span id="tt1-clicks-l30">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--clicks-l1">CLICKS L1: <span id="tt1-clicks-l1">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sold">SOLD L30: <span id="tt1-orders-l30">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sold-l1">SOLD L1: <span id="tt1-orders-l1">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sales">ADS SALES L30: <span id="tt1-revenue-l30">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sales-l1">ADS SALES L1: <span id="tt1-revenue-l1">$0</span></span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="tt1-open-upload">
                        <i class="fa-solid fa-upload me-1"></i>Upload
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="tt1-ads-raw-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 tt1-badge-row mt-2">
                    <span class="tt1-stat-badge tt1-stat-badge--cvr" title="CVR = Sold ÷ Clicks × 100">
                        CVR L30: <span id="tt1-cvr-l30">0%</span>
                        <span id="tt1-cvr-l30-calc">(0 ÷ 0)</span>
                    </span>
                    <span class="tt1-stat-badge tt1-stat-badge--cvr-l1" title="CVR = Sold ÷ Clicks × 100">
                        CVR L1: <span id="tt1-cvr-l1">0%</span>
                        <span id="tt1-cvr-l1-calc">(0 ÷ 0)</span>
                    </span>
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

    <div class="modal fade" id="tt1-upload-modal" tabindex="-1" aria-labelledby="tt1-upload-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="tt1-upload-modal-label">
                        <i class="fa-solid fa-upload me-1"></i>Upload sheet
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="text-muted" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-upload me-1"></i>Upload (xlsx / csv / txt). Old range is deleted first.
                        </span>
                        <input type="file" id="tt1-l1-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <input type="file" id="tt1-l7-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <input type="file" id="tt1-l30-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <button type="button" id="tt1-l1-upload" class="tt1-range-btn">L1</button>
                        <button type="button" id="tt1-l7-upload" class="tt1-range-btn">L7</button>
                        <button type="button" id="tt1-l30-upload" class="tt1-range-btn">L30</button>
                    </div>
                    <div id="tt1-upload-status" class="mt-2" style="font-size: 0.85rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tt1-video-title-modal" tabindex="-1" aria-labelledby="tt1-video-title-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tt1-video-title-modal-label">Video title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="tt1-video-title-modal-body"></div>
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
        const clicksL30 = Number(sums.clicks_l30 || 0);
        const clicksL1 = Number(sums.clicks_l1 || 0);
        const ordersL30 = Number(sums.orders_l30 || 0);
        const ordersL1 = Number(sums.orders_l1 || 0);
        const cvrL30 = clicksL30 > 0 ? (ordersL30 / clicksL30) * 100 : 0;
        const cvrL1 = clicksL1 > 0 ? (ordersL1 / clicksL1) * 100 : 0;

        $('#total-tt1-ads-raw').text(Number(sums.count || 0).toLocaleString('en-US'));
        $('#tt1-cost-l30').text(money(sums.cost_l30));
        $('#tt1-cost-l1').text(money(sums.cost_l1));
        $('#tt1-clicks-l30').text(clicksL30.toLocaleString('en-US'));
        $('#tt1-clicks-l1').text(clicksL1.toLocaleString('en-US'));
        $('#tt1-orders-l30').text(ordersL30.toLocaleString('en-US'));
        $('#tt1-orders-l1').text(ordersL1.toLocaleString('en-US'));
        $('#tt1-revenue-l30').text(money(sums.revenue_l30));
        $('#tt1-revenue-l1').text(money(sums.revenue_l1));
        $('#tt1-cvr-l30').text(cvrL30.toFixed(1) + '%');
        $('#tt1-cvr-l1').text(cvrL1.toFixed(1) + '%');
        $('#tt1-cvr-l30-calc').text('(' + ordersL30.toLocaleString('en-US') + ' ÷ ' + clicksL30.toLocaleString('en-US') + ')');
        $('#tt1-cvr-l1-calc').text('(' + ordersL1.toLocaleString('en-US') + ' ÷ ' + clicksL1.toLocaleString('en-US') + ')');
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
                {
                    title: 'Video title',
                    field: 'video_title',
                    width: 110,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: function(cell) {
                        const v = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                        if (!v || v === '-' || v.toUpperCase() === 'N/A') {
                            return '';
                        }
                        return '<button type="button" class="btn btn-sm btn-link p-0 tt1-video-title-btn" title="View video title"><i class="fas fa-align-left"></i></button>';
                    },
                    cellClick: function(_e, cell) {
                        const v = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                        if (!v || v === '-' || v.toUpperCase() === 'N/A') {
                            return;
                        }
                        const row = cell.getRow().getData() || {};
                        $('#tt1-video-title-modal-label').text((row.campaign_name || 'Video title') + (row.sku ? ' · ' + row.sku : ''));
                        $('#tt1-video-title-modal-body').text(v);
                        const el = document.getElementById('tt1-video-title-modal');
                        if (window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(el).show();
                        } else {
                            $(el).modal('show');
                        }
                    },
                },
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

        function showTt1UploadModal() {
            const el = document.getElementById('tt1-upload-modal');
            if (!el) return;
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        }
        $('#tt1-open-upload').on('click', showTt1UploadModal);

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
