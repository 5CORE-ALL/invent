@extends('layouts.vertical', ['title' => 'TikTok GMV Max Campaigns', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gmvmax-badge {
            font-size: 1.15rem !important;
            padding: 0.6rem 1rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
            color: #000 !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TikTok GMV Max Campaigns',
        'sub_title'  => 'Campaign totals from the TikTok Marketing API — same columns as the Ads Manager export',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="btn-group me-2" role="group" id="gmvmax-range">
                        <button type="button" class="btn btn-sm btn-primary" data-range="L30">L30</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-range="L7">L7</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-range="L1">L1</button>
                    </div>
                    <span class="badge gmvmax-badge" style="background:#cfe2ff" id="b-campaigns">Campaigns 0</span>
                    <span class="badge gmvmax-badge" style="background:#a5d6e8" id="b-cost">Cost $0.00</span>
                    <span class="badge gmvmax-badge" style="background:#d7e3fc" id="b-orders">SKU orders 0</span>
                    <span class="badge gmvmax-badge" style="background:#9ec5fe" id="b-rev">Gross revenue $0.00</span>
                    <span class="badge gmvmax-badge" style="background:#ffe69c" id="b-roi">ROI -</span>
                    <span class="text-muted small ms-2" id="gmvmax-period"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="gmvmax-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" id="gmvmax-f-name" class="form-control form-control-sm"
                                   placeholder="Filter by campaign name…" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="gmvmax-f-id" class="form-control form-control-sm"
                                   placeholder="Filter by campaign ID…" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="gmvmax-f-sku" class="form-control form-control-sm"
                                   placeholder="Filter by SKU (campaigns selling it)…" autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="gmvmax-f-clear">Clear filters</button>
                        </div>
                    </div>
                </div>
                <div id="gmvmax-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function money(n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function moneyCell(cell) {
        const v = cell.getValue();
        return (v === null || v === undefined || v === '') ? '' : money(v);
    }
    function numCol(title, field, width, fmt) {
        return { title: title, field: field, width: width, hozAlign: 'right', sorter: 'number',
                 headerSortStartingDir: 'desc', formatter: fmt };
    }

    $(document).ready(function() {
        let range = 'L30';
        const table = new Tabulator('#gmvmax-table', {
            ajaxURL: "{{ route('tiktok.gmv.max.campaigns.data') }}",
            ajaxParams: function() { return { range: range }; },
            ajaxResponse: function(_u, _p, resp) {
                const s = (resp && resp.sums) || {};
                $('#b-campaigns').text('Campaigns ' + Number(s.campaigns || 0).toLocaleString('en-US'));
                $('#b-cost').text('Cost ' + money(s.cost));
                $('#b-orders').text('SKU orders ' + Number(s.sku_orders || 0).toLocaleString('en-US'));
                $('#b-rev').text('Gross revenue ' + money(s.gross_revenue));
                $('#b-roi').text('ROI ' + (s.roi == null ? '-' : Number(s.roi).toFixed(2)));
                const p = (resp && resp.period_start) ? (resp.period_start + ' → ' + resp.period_end) : '';
                const f = (resp && resp.fetched_at) ? ('  ·  fetched ' + resp.fetched_at + ' UTC') : '';
                $('#gmvmax-period').text(p ? ('Period ' + p + f) : 'No data uploaded yet for ' + range);
                return (resp && resp.data) ? resp.data : [];
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 250],
            initialSort: [{ column: 'cost', dir: 'desc' }],
            placeholder: 'No GMV Max campaign data yet — it is uploaded by the daily GitHub job.',
            columns: [
                { title: 'Campaign ID', field: 'campaign_id', width: 170, sorter: 'string' },
                { title: 'Campaign name', field: 'campaign_name', minWidth: 240, sorter: 'string' },
                numCol('Current budget', 'budget', 130, moneyCell),
                numCol('Cost', 'cost', 110, moneyCell),
                numCol('SKU orders', 'sku_orders', 110, function(c) { return Number(c.getValue() || 0).toLocaleString('en-US'); }),
                numCol('Cost per order', 'cost_per_order', 130, moneyCell),
                numCol('Gross revenue', 'gross_revenue', 130, moneyCell),
                numCol('ROI', 'roi', 90, function(c) { const v = c.getValue(); return (v == null || v === '') ? '' : Number(v).toFixed(2); }),
                { title: 'SKUs', field: 'skus', minWidth: 220, sorter: 'string', tooltip: true,
                  formatter: function(c) { const v = c.getValue() || ''; return v.length > 60 ? v.substring(0, 57) + '… (' + (c.getRow().getData().products || 0) + ')' : v; } },
                { title: 'Status', field: 'status', width: 110, hozAlign: 'center', sorter: 'string' },
                { title: 'Type', field: 'promotion_type', width: 110, hozAlign: 'center', sorter: 'string' },
                { title: 'Currency', field: 'currency', width: 90, hozAlign: 'center' },
            ],
        });

        $('#gmvmax-range button').on('click', function() {
            range = $(this).data('range');
            $('#gmvmax-range button').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
            table.setData("{{ route('tiktok.gmv.max.campaigns.data') }}", { range: range });
        });

        function applyFilters() {
            const name = $('#gmvmax-f-name').val().trim().toLowerCase();
            const id = $('#gmvmax-f-id').val().trim();
            const sku = $('#gmvmax-f-sku').val().trim().toLowerCase();
            if (!name && !id && !sku) { table.clearFilter(true); return; }
            table.setFilter(function(row) {
                if (name && !String(row.campaign_name || '').toLowerCase().includes(name)) return false;
                if (id && !String(row.campaign_id || '').includes(id)) return false;
                if (sku && !String(row.skus || '').toLowerCase().includes(sku)) return false;
                return true;
            });
        }
        $('#gmvmax-f-name, #gmvmax-f-id, #gmvmax-f-sku').on('input', applyFilters);
        $('#gmvmax-f-clear').on('click', function() {
            $('#gmvmax-f-name, #gmvmax-f-id, #gmvmax-f-sku').val('');
            table.clearFilter(true);
        });

        $('#gmvmax-csv').on('click', function() { table.download('csv', 'tiktok_gmv_max_campaigns_' + range + '.csv'); });
    });
</script>
@endsection
