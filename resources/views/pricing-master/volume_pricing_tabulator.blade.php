@extends('layouts.vertical', ['title' => 'Volume Pricing', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #vpt-filter-bar { gap: 6px; align-items: center; }
        #vpt-filter-bar .form-control { height: 28px; font-size: 12px; }
        #vpt-table { min-height: 70vh; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Volume Pricing',
        'sub_title' => 'Parent, SKU, INV, Dil, and OV L30',
    ])

    <div class="card shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center mb-2" id="vpt-filter-bar">
                <input type="text" id="vpt-parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 170px;">
                <input type="text" id="vpt-sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 170px;">
                <span class="badge bg-dark" id="vpt-rows-badge">Rows: 0</span>
                <span class="small text-muted" id="vpt-status">Loading…</span>
            </div>
            <div id="vpt-table"></div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const DATA_URL = @json(route('volume.pricing.data'));
            const status = document.getElementById('vpt-status');
            const badge = document.getElementById('vpt-rows-badge');

            function num(v) {
                const n = Number(v);
                return isFinite(n) ? n : 0;
            }

            // Dil = OV L30 ÷ INV, same as the eBay volume pricing page.
            function dil(d) {
                const inv = num(d && d.inv);
                return inv > 0 ? (num(d.ov_l30) / inv) * 100 : 0;
            }

            const table = new Tabulator('#vpt-table', {
                data: [],
                layout: 'fitDataStretch',
                height: 'calc(100vh - 210px)',
                placeholder: 'Loading rows…',
                pagination: true,
                paginationMode: 'local',
                paginationSize: 100,
                paginationCounter: 'rows',
                columnDefaults: { headerSort: true, resizable: true, vertAlign: 'middle' },
                columns: [
                    { title: 'Parent', field: 'parent', width: 160, frozen: true, headerFilter: 'input', cssClass: 'text-primary' },
                    { title: 'SKU', field: 'sku', width: 200, frozen: true, headerFilter: 'input', cssClass: 'fw-bold text-primary' },
                    { title: 'INV', field: 'inv', hozAlign: 'center', width: 80, sorter: 'number',
                        formatter: function(cell) { return Math.round(num(cell.getValue())); } },
                    { title: 'Dil', field: 'dil', hozAlign: 'center', width: 80,
                        headerTooltip: 'Dil = OV L30 ÷ INV',
                        sorter: function(a, b, aRow, bRow) { return dil(aRow.getData()) - dil(bRow.getData()); },
                        formatter: function(cell) {
                            const v = dil(cell.getRow().getData());
                            let color = '#6c757d';
                            if (v > 0 && v < 25) color = '#a00211';
                            else if (v >= 25 && v < 50) color = '#28a745';
                            else if (v >= 50) color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        } },
                    { title: 'OV L30', field: 'ov_l30', hozAlign: 'center', width: 90, sorter: 'number',
                        formatter: function(cell) { return Math.round(num(cell.getValue())); } },
                ],
            });

            function applySearch() {
                const parent = document.getElementById('vpt-parent-search').value.trim().toLowerCase();
                const sku = document.getElementById('vpt-sku-search').value.trim().toLowerCase();
                table.setFilter(function(d) {
                    if (parent && String(d.parent || '').toLowerCase().indexOf(parent) === -1) return false;
                    if (sku && String(d.sku || '').toLowerCase().indexOf(sku) === -1) return false;
                    return true;
                });
            }
            document.getElementById('vpt-parent-search').addEventListener('input', applySearch);
            document.getElementById('vpt-sku-search').addEventListener('input', applySearch);

            fetch(DATA_URL, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                if (!res.ok) throw new Error('Data failed (' + res.status + ')');
                return res.json();
            }).then(function(payload) {
                const rows = (payload && payload.data) || [];
                return table.setData(rows).then(function() {
                    status.textContent = rows.length + ' rows';
                    badge.textContent = 'Rows: ' + rows.length;
                });
            }).catch(function(err) {
                status.textContent = (err && err.message) || 'Could not load data';
            });
        });
    </script>
@endsection
