@php
    $pageTitle = 'Advertisement Master';
    $pageSubtitle = '';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .adm-stat-badge {
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
        .adm-stat-badge--spend  { background: #ef4444; }
        .adm-stat-badge--clicks { background: #4c7ed8; }
        .adm-stat-badge--sold   { background: #f59e0b; }
        .adm-stat-badge--sales  { background: #16a34a; }
        .adm-stat-badge--cvr    { background: #db2777; }
        .adm-stat-badge--acos   { background: #ea580c; }
        .adm-stat-badge--tcos   { background: #7c3aed; }
        .adm-stat-badge--ssales { background: #0d9488; }

        #advertisement-master-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }

        #advertisement-master-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 11px;
        }

        #advertisement-master-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }

        #advertisement-master-wrap .tabulator .tabulator-row {
            min-height: 32px;
        }

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
            text-align: center;
        }

        #advertisement-master-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }

        #advertisement-master-wrap .tabulator .tabulator-row .tabulator-cell:first-child {
            text-align: left;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-right: 4px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            vertical-align: middle;
            flex-shrink: 0;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control-expand::after {
            content: '+';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #advertisement-master-wrap .tabulator .tabulator-data-tree-control-collapse::after {
            content: '−';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        #advertisement-master-wrap .tabulator-row.adm-child-row .tabulator-cell {
            background: #f8fafc;
        }

        #advertisement-master-wrap .tabulator-row.adm-child-row:hover .tabulator-cell {
            background: #f1f5f9;
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
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-nowrap gap-2 flex-grow-1 overflow-x-auto py-1" style="min-width:0;">
                            <span class="adm-stat-badge adm-stat-badge--spend">SPEND: <span id="adm-badge-spend">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--clicks">CLICKS: <span id="adm-badge-clicks">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sold">SOLD: <span id="adm-badge-sold">0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--sales">ADS SALES: <span id="adm-badge-sales">$0</span></span>
                            <span class="adm-stat-badge adm-stat-badge--cvr">CVR: <span id="adm-badge-cvr">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--acos">ACOS: <span id="adm-badge-acos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--tcos">TCOS: <span id="adm-badge-tcos">0%</span></span>
                            <span class="adm-stat-badge adm-stat-badge--ssales" title="Combined Amazon + eBay + eBay 2 + eBay 3 + Shopify L30 store sales">S SALES: <span id="adm-badge-ssales">$0</span></span>
                        </div>
                        <input type="text" id="adm-search" class="form-control form-control-sm"
                            placeholder="Search channel…" style="width:180px; flex-shrink:0;">
                        <button type="button" id="adm-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>

                    <div id="advertisement-master-wrap">
                        <div id="advertisement-master-table"></div>
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
            let admSSales = 0;

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
                    if (r && r.is_sub_row) return;
                    spend  += Number(r.spend  || 0);
                    clicks += Number(r.clicks || 0);
                    sold   += Number(r.sold   || 0);
                    sales  += Number(r.sales  || 0);
                });
                const cvr  = clicks > 0 ? (sold  / clicks) * 100 : 0;
                const acos = sales  > 0 ? (spend / sales)  * 100 : (spend > 0 ? 100 : 0);
                const tcos = admSSales > 0 ? (spend / admSSales) * 100 : (spend > 0 ? 100 : 0);

                document.getElementById('adm-badge-spend').textContent  = '$' + Math.round(spend).toLocaleString();
                document.getElementById('adm-badge-clicks').textContent = Math.round(clicks).toLocaleString();
                document.getElementById('adm-badge-sold').textContent   = Math.round(sold).toLocaleString();
                document.getElementById('adm-badge-sales').textContent  = '$' + Math.round(sales).toLocaleString();
                document.getElementById('adm-badge-cvr').textContent    = cvr.toFixed(1) + '%';
                document.getElementById('adm-badge-acos').textContent   = Math.round(acos) + '%';
                document.getElementById('adm-badge-tcos').textContent   = Math.round(tcos) + '%';
            }

            const channelLinks = {
                'Amazon': "{{ route('amazon.ads.all') }}",
                'Amazon · KW': "{{ route('amazon.kw.ads') }}",
                'Amazon · PT': "{{ route('amazon.pt.ads') }}",
                'Amazon · HL': "{{ route('amazon.hl.ads') }}",
                'eBay': "{{ route('ebay.campaign.ads') }}",
                'eBay 2': "{{ route('ebay2.campaign.ads') }}",
                'eBay 3': "{{ route('ebay3.campaign.ads') }}",
                'Shopify': "{{ route('shopify.ads.master') }}",
                'Shopify · Google Shopping': "{{ route('google.shopping.campaigns') }}",
                'Shopify · Google SERP': "{{ route('google.serp.campaigns') }}",
                'Shopify · Youtube ads': "{{ route('google.youtube.ads.campaigns') }}",
                'Shopify · Facebook': "{{ route('facebook.ads.channel') }}",
                'Shopify · Facebook · G Video': "{{ route('facebook.ads.channel.group.video') }}",
                'Shopify · Facebook · G Carousal': "{{ route('facebook.ads.channel.group.carousal') }}",
                'Shopify · Facebook · P Video': "{{ route('facebook.ads.channel.parent.video') }}",
                'Shopify · Facebook · P Carousal': "{{ route('facebook.ads.channel.parent.carousal') }}",
                'Shopify · Instagram': "{{ route('instagram.ads.channel') }}",
                'Shopify · Instagram · G Video': "{{ route('instagram.ads.channel.group.video') }}",
                'Shopify · Instagram · G Carousal': "{{ route('instagram.ads.channel.group.carousal') }}",
                'Shopify · Instagram · P Video': "{{ route('instagram.ads.channel.parent.video') }}",
                'Shopify · Instagram · P Carousal': "{{ route('instagram.ads.channel.parent.carousal') }}",
            };

            function channelFormatter(cell) {
                const name = cell.getValue() || '';
                const url  = channelLinks[name];
                const row  = cell.getRow().getData() || {};
                const isChild = !!cell.getRow().getTreeParent();
                const weight = isChild ? 'font-weight:500;' : 'font-weight:600;';
                const color = isChild ? 'color:#475569;' : '';
                if (url) {
                    return '<a href="' + url + '" target="_blank" style="color:inherit;text-decoration:underline;' + weight + color + '">' + name + '</a>';
                }
                return '<span style="' + weight + color + '">' + name + '</span>';
            }

            const dataUrl = "{{ route('advertisement.master.data') }}";

            const table = new Tabulator('#advertisement-master-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    admSSales = Number(response.total_net_sales || 0);
                    const ssEl = document.getElementById('adm-badge-ssales');
                    if (ssEl) {
                        ssEl.textContent = '$' + Number(admSSales).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        });
                    }
                    updateBadges(rows);
                    return rows;
                },
                layout: 'fitColumns',
                headerSort: true,
                initialSort: [],
                dataTree: true,
                dataTreeStartExpanded: false,
                dataTreeChildField: '_children',
                dataTreeFilter: true,
                rowFormatter: function (row) {
                    if (row.getTreeParent()) {
                        row.getElement().classList.add('adm-child-row');
                    }
                },
                columns: [
                    { title: 'Channel', field: 'channel', minWidth: 150, headerSort: true, formatter: channelFormatter },
                    { title: 'SPEND', field: 'spend', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true },
                    { title: 'CLICKS', field: 'clicks', hozAlign: 'center', formatter: intFormatter, headerSort: true },
                    { title: 'SOLD', field: 'sold', hozAlign: 'center', formatter: intFormatter, headerSort: true },
                    { title: 'ADS SALES', field: 'sales', hozAlign: 'center', formatter: wholeMoneyFormatter, headerSort: true },
                    { title: 'CVR', field: 'cvr', hozAlign: 'center', formatter: percentFormatter, headerSort: true },
                    { title: 'ACOS', field: 'acos', hozAlign: 'center', formatter: percentFormatter, headerSort: true },
                ],
            });

            table.on('dataFiltered', function () {
                updateBadges(table.getData());
            });

            document.getElementById('adm-search').addEventListener('input', function () {
                const q = this.value.trim();
                if (q === '') {
                    table.clearFilter();
                } else {
                    table.setFilter('channel', 'like', q);
                }
            });

            document.getElementById('adm-refresh').addEventListener('click', function () {
                document.getElementById('adm-search').value = '';
                table.clearFilter();
                table.setData(dataUrl);
            });
        });
    </script>
@endsection
