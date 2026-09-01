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
        .badge-tt-gmv-ads-raw:not(.bg-primary) {
            color: #000 !important;
        }
        #tt-gmv-ads-raw-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 20px !important;
        }
        #tt-gmv-ads-raw-table .tabulator-header .tabulator-col {
            cursor: pointer;
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
                        Rows <span id="total-tt-gmv-ads-raw">{{ number_format((int) ($sums['count'] ?? 0)) }}</span>
                    </span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-ad-sold-l30-badge"
                          style="background-color: #cfe2ff;" title="Sum of Ad sold where report_range is L30">Ad sold L30: {{ number_format((int) ($sums['sold_l30'] ?? 0)) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-ad-sold-l1-badge"
                          style="background-color: #d7e3fc;" title="Sum of Ad sold where report_range is L1">Ad sold L1: {{ number_format((int) ($sums['sold_l1'] ?? 0)) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-ad-sales-l30-badge"
                          style="background-color: #9ec5fe;" title="Sum of Ad sales where report_range is L30">Ad sales L30: ${{ number_format((float) ($sums['sales_l30'] ?? 0), 2) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-ad-sales-l1-badge"
                          style="background-color: #b8cfe5;" title="Sum of Ad sales where report_range is L1">Ad sales L1: ${{ number_format((float) ($sums['sales_l1'] ?? 0), 2) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-spend-l30-badge"
                          style="background-color: #a5d6e8;" title="Sum of Spend where report_range is L30">Spend L30: ${{ number_format((float) ($sums['spend_l30'] ?? 0), 2) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-spend-l1-badge"
                          style="background-color: #c5e4f3;" title="Sum of Spend where report_range is L1">Spend L1: ${{ number_format((float) ($sums['spend_l1'] ?? 0), 2) }}</span>
                    <span class="badge badge-tt-gmv-ads-raw" id="tt-gmv-budget-badge"
                          style="background-color: #ffe69c;" title="Sum of Budget (L30 rows, fallback all rows)">Budget: ${{ number_format((float) ($sums['budget'] ?? 0), 2) }}</span>
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
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    const ttGmvServerSums = {!! json_encode($sums ?? []) !!};
    function numCol(title, field, width) {
        return {
            title: title,
            field: field,
            width: width || 110,
            hozAlign: 'right',
            sorter: 'number',
            headerSort: true,
            headerSortStartingDir: 'desc',
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                return Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 4 }) : String(v);
            },
        };
    }

    function textCol(title, field, opts) {
        return Object.assign({
            title: title,
            field: field,
            sorter: 'string',
            headerSort: true,
            headerSortStartingDir: 'asc',
        }, opts || {});
    }

    function money(n) {
        return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function applyGmvSums(s) {
        const sums = s || {};
        $('#total-tt-gmv-ads-raw').text(Number(sums.count || 0).toLocaleString('en-US'));
        $('#tt-gmv-ad-sold-l30-badge').text('Ad sold L30: ' + Number(sums.sold_l30 || 0).toLocaleString('en-US'));
        $('#tt-gmv-ad-sold-l1-badge').text('Ad sold L1: ' + Number(sums.sold_l1 || 0).toLocaleString('en-US'));
        $('#tt-gmv-ad-sales-l30-badge').text('Ad sales L30: ' + money(sums.sales_l30));
        $('#tt-gmv-ad-sales-l1-badge').text('Ad sales L1: ' + money(sums.sales_l1));
        $('#tt-gmv-spend-l30-badge').text('Spend L30: ' + money(sums.spend_l30));
        $('#tt-gmv-spend-l1-badge').text('Spend L1: ' + money(sums.spend_l1));
        $('#tt-gmv-budget-badge').text('Budget: ' + money(sums.budget));
    }

    function sumsFromRows(rows) {
        const list = Array.isArray(rows) ? rows : [];
        const sums = {
            count: 0, sold_l30: 0, sold_l1: 0,
            sales_l30: 0, sales_l1: 0, spend_l30: 0, spend_l1: 0, budget: 0,
        };
        let budgetL30 = 0, budgetAll = 0, hasL30 = false;
        list.forEach(function(row) {
            const data = row && typeof row.getData === 'function' ? row.getData() : (row || {});
            const range = String(data.report_range == null ? '' : data.report_range).toUpperCase().trim();
            const sold = Number(data.ad_sold) || 0;
            const sales = Number(data.ad_sales) || 0;
            const spend = Number(data.spend) || 0;
            const budget = Number(data.budget) || 0;
            budgetAll += budget;
            sums.count += 1;
            if (range === 'L30') {
                hasL30 = true;
                sums.sold_l30 += sold;
                sums.sales_l30 += sales;
                sums.spend_l30 += spend;
                budgetL30 += budget;
            } else if (range === 'L1') {
                sums.sold_l1 += sold;
                sums.sales_l1 += sales;
                sums.spend_l1 += spend;
            } else {
                sums.sold_l30 += sold;
                sums.sales_l30 += sales;
                sums.spend_l30 += spend;
            }
        });
        sums.budget = hasL30 ? budgetL30 : budgetAll;
        return sums;
    }

    $(document).ready(function() {
        let serverSums = ttGmvServerSums || null;
        if (serverSums) {
            applyGmvSums(serverSums);
        }
        const table = new Tabulator('#tt-gmv-ads-raw-table', {
            ajaxURL: "{{ route('tiktok.gmv.ads.raw.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                serverSums = response && response.sums ? response.sums : sumsFromRows(data);
                applyGmvSums(serverSums);
                return data;
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            columnDefaults: { headerSort: true },
            initialSort: [{ column: 'ad_sold', dir: 'desc' }, { column: 'ad_sales', dir: 'desc' }],
            placeholder: 'No rows in tiktok_gmv_ads.',
            columns: [
                numCol('ID', 'id', 80),
                textCol('SKU', 'sku', { minWidth: 180 }),
                textCol('Range', 'report_range', { width: 90, hozAlign: 'center' }),
                numCol('Ad sold', 'ad_sold', 110),
                numCol('Ad sales', 'ad_sales', 120),
                numCol('Spend', 'spend', 110),
                numCol('Budget', 'budget', 110),
                textCol('Status', 'status', { width: 120, hozAlign: 'center' }),
                textCol('Approval', 'approval', { width: 130, hozAlign: 'center' }),
                textCol('Created', 'created_at', { minWidth: 160 }),
                textCol('Updated', 'updated_at', { minWidth: 160 }),
            ],
        });

        table.on('dataLoaded', function(data) {
            if (serverSums) {
                applyGmvSums(serverSums);
                return;
            }
            if (Array.isArray(data) && data.length) {
                applyGmvSums(sumsFromRows(data));
            }
        });

        table.on('dataFiltered', function(_filters, rows) {
            const q = String($('#tt-gmv-ads-raw-search').val() || '').trim();
            if (!q) {
                if (serverSums) {
                    applyGmvSums(serverSums);
                }
                return;
            }
            applyGmvSums(sumsFromRows(rows));
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
