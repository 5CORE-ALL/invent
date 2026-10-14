@extends('layouts.vertical', ['title' => 'Newegg B2B', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #newegg-b2b-table .tabulator-header .tabulator-col.tabulator-sortable,
        #newegg-b2b-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-content,
        #newegg-b2b-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            cursor: pointer;
            pointer-events: auto;
        }
        #newegg-b2b-table .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="asc"] .tabulator-col-title,
        #newegg-b2b-table .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="desc"] .tabulator-col-title {
            text-decoration: underline;
        }
        .ne-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: zoom-in;
        }
        #ne-img-preview {
            position: fixed;
            display: none;
            z-index: 99999;
            pointer-events: none;
            border: 2px solid #0d6efd;
            border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
            background: #fff;
            padding: 3px;
        }
        #ne-img-preview img {
            display: block;
            max-width: 320px;
            max-height: 320px;
            object-fit: contain;
        }
        #newegg-b2b-wrapper {
            overflow: hidden;
        }
        #newegg-b2b-table {
            overflow: hidden;
        }
        #newegg-b2b-table .tabulator-tableholder {
            overflow-y: scroll !important;
        }
        .copy-sku-btn {
            cursor: pointer;
            margin-left: 8px;
            font-size: 13px;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Newegg B2B',
        'sub_title' => 'Image, Parent, SKU, DIL, OVL30, INV',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3 d-flex flex-column">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <input type="text" id="sku-search" class="form-control form-control-sm flex-shrink-0"
                        placeholder="Search SKU or Parent..." style="width: 200px;">

                    <select id="inventory-filter" class="form-select form-select-sm flex-shrink-0" style="width: 130px;">
                        <option value="all" selected>All INV</option>
                        <option value="zero">0 INV</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm flex-shrink-0" style="width: 140px;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>
                </div>

                <div id="newegg-b2b-wrapper" style="height: calc(100vh - 220px); min-height: 480px;">
                    <div id="newegg-b2b-table"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="ne-img-preview"><img src="" alt="preview"></div>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        let skuQuery = '';
        let invFilter = 'all';
        let dilFilter = 'all';

        function showToast(message, type) {
            const bg = type === 'error' ? 'bg-danger' : 'bg-success';
            const el = document.createElement('div');
            el.className = 'toast align-items-center text-white ' + bg + ' border-0 show';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div></div>';
            document.querySelector('.toast-container').appendChild(el);
            setTimeout(function() { el.remove(); }, 3500);
        }

        function dilFormatter(cell) {
            const v = cell.getValue();
            if (v === null || v === undefined || v === '') {
                return '<span style="color:#a00211;font-weight:bold;">0%</span>';
            }
            const n = parseFloat(v);
            let color = '#a00211';
            if (n < 25) color = '#a00211';
            else if (n < 50) color = '#28a745';
            else color = '#e83e8c';
            return '<span style="color:' + color + ';font-weight:bold;">' + n.toFixed(0) + '%</span>';
        }

        function rowPassesFilters(data) {
            const q = skuQuery.trim().toLowerCase();
            if (q) {
                const sku = String(data.sku || '').toLowerCase();
                const parent = String(data.parent || '').toLowerCase();
                if (sku.indexOf(q) === -1 && parent.indexOf(q) === -1) return false;
            }
            const inv = parseInt(data.inv, 10) || 0;
            if (invFilter === 'zero' && inv !== 0) return false;
            if (invFilter === 'more' && inv <= 0) return false;
            const dil = parseFloat(data.dil) || 0;
            if (dilFilter === 'red' && dil >= 25) return false;
            if (dilFilter === 'green' && (dil < 25 || dil >= 50)) return false;
            if (dilFilter === 'pink' && dil < 50) return false;
            return true;
        }

        function applyFilters() {
            if (!table) return;
            table.setFilter(rowPassesFilters);
        }

        document.addEventListener('DOMContentLoaded', function() {
            table = new Tabulator('#newegg-b2b-table', {
                ajaxURL: '/newegg-b2b-pricing-data',
                ajaxConfig: {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                },
                ajaxSorting: false,
                sortMode: 'local',
                filterMode: 'local',
                headerSortClickElement: 'icon',
                columnDefaults: { headerSort: true, headerSortStartingDir: 'desc' },
                height: '100%',
                layout: 'fitDataStretch',
                responsiveLayout: false,
                pagination: true,
                paginationMode: 'local',
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200, true],
                paginationCounter: 'rows',
                placeholder: 'No Data Available',
                ajaxResponse: function(url, params, response) {
                    if (Array.isArray(response)) return response;
                    if (response && Array.isArray(response.data)) return response.data;
                    return [];
                },
                ajaxError: function(error) {
                    const msg = (error && error.statusText) ? error.statusText : 'Failed to load Newegg B2B data';
                    showToast(msg, 'error');
                },
                initialSort: [{ column: 'ovl30', dir: 'desc' }],
                columns: [
                    {
                        title: 'Image',
                        field: 'image',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 70,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '';
                            const src = String(v).replace(/"/g, '&quot;');
                            return '<img src="' + src + '" class="ne-thumb" alt="img" loading="lazy">';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'parent',
                        sorter: 'string',
                        headerSort: true,
                        cssClass: 'text-primary',
                        width: 180,
                        tooltip: true
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        sorter: 'string',
                        headerSort: true,
                        cssClass: 'text-primary fw-bold',
                        minWidth: 220,
                        formatter: function(cell) {
                            const sku = cell.getValue() || '';
                            const safe = String(sku).replace(/"/g, '&quot;');
                            return '<span>' + safe + '</span>'
                                + '<i class="fa fa-copy text-secondary copy-sku-btn" data-sku="' + safe + '" title="Copy SKU"></i>';
                        }
                    },
                    { title: 'DIL %', field: 'dil', hozAlign: 'center', sorter: 'number', width: 90, formatter: dilFormatter },
                    { title: 'OVL30', field: 'ovl30', hozAlign: 'center', sorter: 'number', width: 90 },
                    { title: 'INV', field: 'inv', hozAlign: 'center', sorter: 'number', width: 80 }
                ]
            });

            table.on('tableBuilt', function() {
                const wrap = document.getElementById('newegg-b2b-wrapper');
                if (wrap && typeof table.setHeight === 'function') {
                    table.setHeight(wrap.clientHeight);
                }
            });

            $('#sku-search').on('input', function() {
                skuQuery = $(this).val() || '';
                applyFilters();
            });
            $('#inventory-filter').on('change', function() {
                invFilter = $(this).val();
                applyFilters();
            });
            $('#dil-filter').on('change', function() {
                dilFilter = $(this).val();
                applyFilters();
            });

            document.getElementById('newegg-b2b-table').addEventListener('click', function(e) {
                const btn = e.target.closest('.copy-sku-btn');
                if (!btn) return;
                const sku = btn.getAttribute('data-sku') || '';
                if (!sku) return;
                navigator.clipboard.writeText(sku).then(function() {
                    showToast('Copied ' + sku, 'success');
                });
            });

            const imgPreview = document.getElementById('ne-img-preview');
            const imgPreviewImg = imgPreview ? imgPreview.querySelector('img') : null;
            const tableEl = document.getElementById('newegg-b2b-table');

            function positionPreview(e) {
                const pad = 16;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                const w = imgPreview.offsetWidth || 326;
                const h = imgPreview.offsetHeight || 326;
                if (x + w > window.innerWidth) x = e.clientX - w - pad;
                if (y + h > window.innerHeight) y = window.innerHeight - h - pad;
                if (y < 0) y = pad;
                imgPreview.style.left = x + 'px';
                imgPreview.style.top = y + 'px';
            }

            if (tableEl && imgPreview && imgPreviewImg) {
                tableEl.addEventListener('mouseover', function(e) {
                    const thumb = e.target.closest('.ne-thumb');
                    if (!thumb) return;
                    imgPreviewImg.src = thumb.getAttribute('src');
                    imgPreview.style.display = 'block';
                    positionPreview(e);
                });
                tableEl.addEventListener('mousemove', function(e) {
                    if (imgPreview.style.display === 'block') positionPreview(e);
                });
                tableEl.addEventListener('mouseout', function(e) {
                    if (e.target.closest('.ne-thumb')) imgPreview.style.display = 'none';
                });
            }
        });
    </script>
@endsection
