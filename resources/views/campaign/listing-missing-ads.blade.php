@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #listing-missing-ads-table .tabulator-header {
            background: #fd7e14 !important;
            font-size: 0.8rem;
            color: #fff !important;
        }
        #listing-missing-ads-table .tabulator-header .tabulator-col {
            background: #fd7e14 !important;
            color: #fff !important;
            border-right: 1px solid rgba(255,255,255,0.25);
            text-align: center;
            padding: 0 !important;
        }
        #listing-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-content,
        #listing-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-title-holder,
        #listing-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-title {
            text-align: center;
            justify-content: center;
            color: #fff !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #listing-missing-ads-table .tabulator-col .tabulator-col-sorter,
        #listing-missing-ads-table .tabulator-col .tabulator-col-sorter-element,
        #listing-missing-ads-table .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        #listing-missing-ads-table .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #listing-missing-ads-table .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 6px 4px;
        }
        #listing-missing-ads-table .tabulator-cell {
            font-size: 0.85rem;
            text-align: center !important;
            justify-content: center;
            align-items: center;
            padding: 4px 6px !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #listing-missing-ads-table .tabulator-cell[tabulator-field="sku"],
        #listing-missing-ads-table .tabulator-cell[tabulator-field="{{ $idField }}"] {
            text-align: left !important;
            justify-content: flex-start;
        }
        #listing-missing-ads-table .tabulator-cell[tabulator-field="image_path"] {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2px !important;
            overflow: hidden;
        }
        #listing-missing-ads-table .missing-ads-thumb {
            width: auto;
            height: 22px;
            max-width: 100%;
            max-height: 22px;
            object-fit: contain;
            border-radius: 2px;
            vertical-align: middle;
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
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            @if ($idField !== 'sku')
                                <input type="text" id="search-id" class="form-control form-control-sm"
                                       placeholder="Search {{ $idLabel }}" style="width: 180px;">
                            @endif
                            <input type="text" id="search-sku" class="form-control form-control-sm"
                                   placeholder="Search SKU" style="width: 160px;">
                            <a href="{{ $adsUrl }}" class="btn btn-sm btn-outline-secondary">{{ $adsLabel }}</a>
                        </div>
                        <button type="button" id="export-btn" class="btn btn-sm btn-success" title="Export CSV">
                            <i class="fa fa-download"></i>
                        </button>
                    </div>
                    <div class="mt-2 p-3 bg-light rounded">
                        <span class="badge fs-6 p-2" id="missing-count"
                              style="background-color: #dc3545; color: white; font-weight: bold;"
                              title="{{ $pageSubtitle }}">
                            Missing: <span class="missing-ads-badge-val">0</span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="listing-missing-ads-table"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dataUrl = @json($dataUrl);
            const idField = @json($idField);
            const idLabel = @json($idLabel);
            const pageTitle = @json($pageTitle);
            let table = null;

            function numFmt(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toLocaleString();
            }

            function paintMissing() {
                const n = table ? (table.getData('active') || []).length : 0;
                const el = document.getElementById('missing-count');
                if (!el) return;
                const val = el.querySelector('.missing-ads-badge-val');
                if (val) val.textContent = Number(n).toLocaleString();
            }

            function applySearch() {
                if (!table) return;
                const idQ = ((document.getElementById('search-id') || {}).value || '').trim().toLowerCase();
                const skuQ = ((document.getElementById('search-sku') || {}).value || '').trim().toLowerCase();
                table.setFilter(function (data) {
                    if (idQ && String(data[idField] || '').toLowerCase().indexOf(idQ) === -1) return false;
                    if (skuQ && String(data.sku || '').toLowerCase().indexOf(skuQ) === -1) return false;
                    return true;
                });
                paintMissing();
            }

            const columns = [
                {
                    title: 'Image',
                    field: 'image_path',
                    width: 70,
                    headerSort: false,
                    formatter: function (cell) {
                        const src = cell.getValue();
                        if (!src) return '';
                        const safe = String(src).replace(/"/g, '&quot;');
                        return '<img class="missing-ads-thumb" src="' + safe + '" alt="">';
                    },
                },
                { title: 'SKU', field: 'sku', minWidth: 140, headerSort: true },
            ];
            if (idField !== 'sku') {
                columns.push({ title: idLabel, field: idField, minWidth: 150, headerSort: true });
            }
            columns.push(
                { title: 'Status', field: 'ad_status', width: 90, headerSort: true },
                { title: 'Price', field: 'price', width: 90, headerSort: true, formatter: function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '';
                    return '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }},
                { title: 'Inv', field: 'inv', width: 80, headerSort: true, formatter: numFmt },
                { title: 'L30', field: 'ovl30', width: 80, headerSort: true, formatter: numFmt },
                { title: 'DIL %', field: 'dil_percent', width: 90, headerSort: true, formatter: function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '';
                    return Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) + '%';
                }}
            );

            table = new Tabulator('#listing-missing-ads-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                index: idField,
                layout: 'fitDataStretch',
                height: 'calc(100vh - 280px)',
                placeholder: 'No missing ads (Inv > 0)',
                initialSort: [{ column: 'sku', dir: 'asc' }],
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    vertAlign: 'middle',
                    headerVertAlign: 'middle',
                },
                columns: columns,
                dataLoaded: paintMissing,
                dataFiltered: paintMissing,
            });

            const searchId = document.getElementById('search-id');
            if (searchId) searchId.addEventListener('input', applySearch);
            document.getElementById('search-sku').addEventListener('input', applySearch);

            document.getElementById('export-btn').addEventListener('click', function () {
                if (!table) return;
                const rows = table.getData('active') || [];
                const headers = columns.map(function (col) { return col.title; });
                const fields = columns.map(function (col) { return col.field; });
                const lines = [headers.join(',')];
                rows.forEach(function (row) {
                    lines.push(fields.map(function (field) {
                        const value = field === 'image_path' ? '' : (row[field] ?? '');
                        return '"' + String(value).replace(/"/g, '""') + '"';
                    }).join(','));
                });
                const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = pageTitle.replace(/\s+/g, '-').toLowerCase() + '.csv';
                link.click();
            });
        });
    </script>
@endsection
