@extends('layouts.vertical', ['title' => 'GMV Tiktok Ads Raw Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        #stat-tt-gmv-ads-raw.badge,
        .badge-tt-gmv-ads-raw {
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
        'page_title' => 'GMV Tiktok Ads Raw Data',
        'sub_title'  => 'All rows saved to tiktok_gmv_ads — no filters',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge badge-tt-gmv-ads-raw bg-primary" id="stat-tt-gmv-ads-raw">
                        Rows <span id="total-tt-gmv-ads-raw">0</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="tt-gmv-ads-raw-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="tt-gmv-ads-raw-search" class="form-control form-control-sm"
                           placeholder="Search SKU, status, approval…">
                </div>
                <div id="tt-gmv-ads-raw-table" style="height: calc(100vh - 280px);"></div>
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
        const table = new Tabulator('#tt-gmv-ads-raw-table', {
            ajaxURL: "{{ route('tiktok.gmv.ads.raw.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                $('#total-tt-gmv-ads-raw').text(Number(response && response.count != null ? response.count : data.length).toLocaleString('en-US'));
                return data;
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: 'id', dir: 'desc' }],
            placeholder: 'No rows in tiktok_gmv_ads.',
            columns: [
                { title: 'ID', field: 'id', width: 80, hozAlign: 'right', sorter: 'number' },
                { title: 'SKU', field: 'sku', minWidth: 180 },
                numCol('Ad sold', 'ad_sold', 110),
                numCol('Ad sales', 'ad_sales', 120),
                numCol('Spend', 'spend', 110),
                numCol('Budget', 'budget', 110),
                { title: 'Status', field: 'status', width: 120, hozAlign: 'center' },
                { title: 'Approval', field: 'approval', width: 130, hozAlign: 'center' },
                { title: 'Created', field: 'created_at', minWidth: 160 },
                { title: 'Updated', field: 'updated_at', minWidth: 160 },
            ],
        });

        $('#tt-gmv-ads-raw-search').on('input', function() {
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

        $('#tt-gmv-ads-raw-csv').on('click', function() {
            table.download('csv', 'tiktok_gmv_ads_raw_data.csv');
        });
    });
</script>
@endsection
