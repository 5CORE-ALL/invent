@php
    $pageTitle = 'Shopify Ads Master';
    $pageSubtitle = 'Shopify B2C';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .sam-stat-badge {
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
        .sam-stat-badge--spend  { background: #ef4444; }
        .sam-stat-badge--clicks { background: #4c7ed8; }
        .sam-stat-badge--sold   { background: #f59e0b; }
        .sam-stat-badge--sales  { background: #16a34a; }
        .sam-stat-badge--cvr    { background: #db2777; }
        .sam-stat-badge--acos   { background: #ea580c; }

        #shopify-ads-master-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }

        #shopify-ads-master-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 11px;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
            height: auto !important;
            min-height: 0 !important;
            display: block;
            align-items: unset;
            justify-content: unset;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            padding: 5px 3px;
            text-align: center;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row {
            min-height: 32px;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
            text-align: center;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        #shopify-ads-master-wrap .tabulator .tabulator-row .tabulator-cell:first-child {
            text-align: left;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title' => $pageSubtitle,
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- Badge strip + Search + Refresh --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-nowrap gap-2 flex-grow-1 overflow-x-auto py-1" style="min-width:0;">
                            <span class="sam-stat-badge sam-stat-badge--spend">SPEND: <span id="sam-badge-spend">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--clicks">CLICKS: <span id="sam-badge-clicks">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sold">SOLD: <span id="sam-badge-sold">0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--sales">SALES: <span id="sam-badge-sales">$0</span></span>
                            <span class="sam-stat-badge sam-stat-badge--cvr">CVR: <span id="sam-badge-cvr">0%</span></span>
                            <span class="sam-stat-badge sam-stat-badge--acos">ACOS: <span id="sam-badge-acos">0%</span></span>
                        </div>
                        <input type="text" id="sam-search" class="form-control form-control-sm"
                            placeholder="Search channel…" style="width:180px; flex-shrink:0;">
                        <button type="button" id="sam-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>

                    <div id="shopify-ads-master-wrap">
                        <div id="shopify-ads-master-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function wholeMoneyFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return '$' + Math.round(value).toLocaleString();
            }

            function intFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return Math.round(value).toLocaleString();
            }

            function percentFormatter(cell) {
                const value = Number(cell.getValue() || 0);
                return value.toLocaleString(undefined, {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 1,
                }) + '%';
            }

            function updateBadges(rows) {
                let spend = 0, clicks = 0, sold = 0, sales = 0;
                rows.forEach(function (r) {
                    spend  += Number(r.spend  || 0);
                    clicks += Number(r.clicks || 0);
                    sold   += Number(r.sold   || 0);
                    sales  += Number(r.sales  || 0);
                });
                const cvr  = clicks > 0 ? (sold  / clicks) * 100 : 0;
                const acos = sales  > 0 ? (spend / sales)  * 100 : (spend > 0 ? 100 : 0);

                document.getElementById('sam-badge-spend').textContent  = '$' + Math.round(spend).toLocaleString();
                document.getElementById('sam-badge-clicks').textContent = Math.round(clicks).toLocaleString();
                document.getElementById('sam-badge-sold').textContent   = Math.round(sold).toLocaleString();
                document.getElementById('sam-badge-sales').textContent  = '$' + Math.round(sales).toLocaleString();
                document.getElementById('sam-badge-cvr').textContent    = cvr.toFixed(1)  + '%';
                document.getElementById('sam-badge-acos').textContent   = Math.round(acos) + '%';
            }

            const channelLinks = {
                'Google Shopping': "{{ route('google.shopping.campaigns') }}",
                'Facebook':        "{{ route('facebook.all.ads.sheet') }}",
            };

            function channelFormatter(cell) {
                const name = cell.getValue() || '';
                const url  = channelLinks[name];
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:inherit;text-decoration:underline;font-weight:600;">' + name + '</a>';
                }
                return name;
            }

            const dataUrl = "{{ route('shopify.ads.master.data') }}";

            const table = new Tabulator('#shopify-ads-master-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    updateBadges(rows);
                    return rows;
                },
                layout: 'fitColumns',
                headerSort: true,
                initialSort: [],
                columns: [
                    { title: 'Channel', field: 'channel', minWidth: 150, headerSort: true, formatter: channelFormatter },
                    { title: 'SPEND',   field: 'spend',   hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true },
                    { title: 'CLICKS',  field: 'clicks',  hozAlign: 'center', formatter: intFormatter,        headerSort: true },
                    { title: 'SOLD',    field: 'sold',    hozAlign: 'center', formatter: intFormatter,        headerSort: true },
                    { title: 'SALES',   field: 'sales',   hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true },
                    { title: 'CVR',     field: 'cvr',     hozAlign: 'center', formatter: percentFormatter,    headerSort: true },
                    { title: 'ACOS',    field: 'acos',    hozAlign: 'center', formatter: percentFormatter,    headerSort: true },
                ],
            });

            // Re-compute badges from whatever rows are visible after a filter.
            table.on('dataFiltered', function (filters, rows) {
                updateBadges(rows.map(function (r) { return r.getData(); }));
            });

            // Search: filter Channel column on input
            document.getElementById('sam-search').addEventListener('input', function () {
                const q = this.value.trim();
                if (q === '') {
                    table.clearFilter();
                } else {
                    table.setFilter('channel', 'like', q);
                }
            });

            document.getElementById('sam-refresh').addEventListener('click', function () {
                document.getElementById('sam-search').value = '';
                table.clearFilter();
                table.setData(dataUrl);
            });
        });
    </script>
@endsection
