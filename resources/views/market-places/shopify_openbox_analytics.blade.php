@extends('layouts.vertical', ['title' => 'Shopify Open Box Analytics', 'sidenav' => 'condensed', 'skipHighcharts' => true])

@section('css')
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator .tabulator-tableholder { overflow-x: auto !important; }
        .tabulator .tabulator-row .tabulator-cell { white-space: nowrap !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: normal !important;
            font-size: 12px;
        }
        .sob-thumb { max-width: 46px; max-height: 46px; object-fit: contain; }
        .sob-red { color: #dc3545; font-weight: 600; }
        .sob-amber { color: #b45309; font-weight: 600; }
        .sob-green { color: #198754; font-weight: 600; }
        #sob-status { font-size: 12px; }
    </style>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shopify Open Box Analytics',
        'sub_title' => 'Shopify Open Box Analytics',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Shopify Open Box Analytics</h4>
                <p class="text-muted mb-2" style="font-size: 12px;">
                    Shopify SKUs containing <b>OPEN BOX</b>. Cost (LP / Ship) is taken from the base product (SKU without
                    "OPEN BOX"). GPFT % = (Price &times; {{ $takeHome }} &minus; LP &minus; Ship) &divide; Price;
                    ROI % = same profit &divide; LP; Dil % = OV L30 &divide; INV.
                </p>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="stock-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Stock</option>
                        <option value="zero">0 Stock</option>
                        <option value="more" selected>Stock &gt; 0</option>
                    </select>
                    <select id="l30-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all" selected>All OV L30</option>
                        <option value="zero">0 OV L30</option>
                        <option value="more">OV L30 &gt; 0</option>
                    </select>
                    <select id="base-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all" selected>All Base SKU</option>
                        <option value="missing">Base SKU not found</option>
                        <option value="found">Base SKU found</option>
                    </select>
                    <input type="text" id="sku-search" class="form-control form-control-sm" style="width: 220px;"
                        placeholder="Search Open Box SKU...">
                    <button id="reload-btn" class="btn btn-sm btn-primary"><i class="fas fa-sync"></i> Reload</button>
                    <button id="export-btn" class="btn btn-sm btn-success"><i class="fas fa-file-csv"></i> Export CSV</button>
                    <span id="sob-status" class="text-muted"></span>
                </div>

                <div class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (rows in view)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-dark fs-6 p-2" id="b-skus">SKUs: 0</span>
                        <span class="badge bg-primary fs-6 p-2" id="b-stock" style="color:#000">S Stock: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="b-stock-cost">Stock @ Cost: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="b-stock-value" style="color:#000">Stock @ Price: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="b-zero-sold">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="b-more-sold" style="background:#b6e0fe;color:#0f172a">&gt; 0 Sold: 0</span>
                        <span class="badge bg-primary fs-6 p-2" id="b-sold-units" style="color:#000">OV L30: 0</span>
                        <span class="badge bg-primary fs-6 p-2" id="b-sales" style="color:#000">Sales L30: $0</span>
                        <span class="badge bg-success fs-6 p-2" id="b-profit">Profit L30: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="b-gpft" style="color:#000">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="b-groi">GROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="b-dil" style="color:#000">Dil: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="b-nobase" style="color:#000">No Base SKU: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="sob-table"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const DATA_URL = @json(route('shopify.openbox.analytics.data'));
            const num = (v) => parseFloat(v) || 0;
            const money = (v) => '$' + Math.round(num(v)).toLocaleString();
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
            const pctCell = (cell, good, ok) => {
                const v = num(cell.getValue());
                const cls = v >= good ? 'sob-green' : (v >= ok ? 'sob-amber' : 'sob-red');
                return `<span class="${cls}">${Math.round(v)}%</span>`;
            };
            const moneyCell = (cell, dp) => {
                const v = cell.getValue();
                return (v === null || v === undefined || v === '') ? '<span class="text-muted">—</span>' : '$' + num(v).toFixed(dp ?? 2);
            };

            const table = new Tabulator('#sob-table', {
                layout: 'fitData',
                height: '70vh',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                placeholder: 'No Shopify Open Box SKUs',
                initialSort: [{column: 'inv', dir: 'desc'}],
                columns: [
                    {title: 'Image', field: 'image_path', width: 70, headerSort: false, formatter: (c) => {
                        const v = c.getValue();
                        return v ? `<img class="sob-thumb" loading="lazy" src="${esc(v)}" alt="">` : '';
                    }},
                    {title: 'Open Box SKU', field: 'sku', width: 250, frozen: true, headerFilter: 'input',
                        headerFilterPlaceholder: 'Search SKU...', cssClass: 'text-primary fw-bold', formatter: (c) => {
                            const d = c.getRow().getData();
                            const link = d.product_link
                                ? ` <a href="${esc(d.product_link)}" target="_blank" rel="noopener" title="Open in Shopify"><i class="fa fa-external-link-alt"></i></a>` : '';
                            return `${esc(c.getValue())}${link} <i class="fa fa-copy text-secondary sob-copy" style="cursor:pointer" data-sku="${esc(c.getValue())}" title="Copy SKU"></i>`;
                        }},
                    {title: 'Base SKU', field: 'base_sku', width: 170, headerFilter: 'input', formatter: (c) => {
                        const d = c.getRow().getData();
                        return d.base_missing ? '<span class="sob-red" title="No product master row for the base SKU — LP / Ship are 0">not found</span>' : esc(c.getValue());
                    }},
                    {title: 'Base INV', field: 'base_inv', hozAlign: 'center', sorter: 'number', width: 80},
                    {title: 'S Stock', field: 'inv', hozAlign: 'center', sorter: 'number', width: 80, formatter: (c) => {
                        const v = num(c.getValue());
                        return v === 0 ? '<span class="sob-red">0</span>' : `<b>${v}</b>`;
                    }},
                    {title: 'OV L30', field: 'l30', hozAlign: 'center', sorter: 'number', width: 75},
                    {title: 'Views', field: 'views', hozAlign: 'center', sorter: 'number', width: 70},
                    {title: 'Dil %', field: 'dil', hozAlign: 'center', sorter: 'number', width: 70, formatter: (c) => {
                        const v = num(c.getValue());
                        const cls = v === 0 ? 'sob-red' : (v >= 25 ? 'sob-green' : 'sob-amber');
                        return `<span class="${cls}">${Math.round(v)}%</span>`;
                    }},
                    {title: 'CVR %', field: 'cvr', hozAlign: 'center', sorter: 'number', width: 70, formatter: (c) => num(c.getValue()).toFixed(1) + '%'},
                    {title: 'Price', field: 'price', hozAlign: 'right', sorter: 'number', width: 80, formatter: (c) => moneyCell(c)},
                    {title: 'Base Price', field: 'base_price', hozAlign: 'right', sorter: 'number', width: 90, formatter: (c) => moneyCell(c)},
                    {title: 'Disc vs Base', field: 'discount_vs_base', hozAlign: 'center', sorter: 'number', width: 90, formatter: (c) => {
                        const v = c.getValue();
                        return (v === null || v === undefined) ? '<span class="text-muted">—</span>' : `${Math.round(num(v))}%`;
                    }},
                    {title: 'LP', field: 'lp', hozAlign: 'right', sorter: 'number', width: 70, formatter: (c) => moneyCell(c)},
                    {title: 'Ship', field: 'ship', hozAlign: 'right', sorter: 'number', width: 70, formatter: (c) => moneyCell(c)},
                    {title: 'Unit Profit', field: 'unit_profit', hozAlign: 'right', sorter: 'number', width: 85, formatter: (c) => {
                        const v = num(c.getValue());
                        return `<span class="${v < 0 ? 'sob-red' : ''}">$${v.toFixed(2)}</span>`;
                    }},
                    {title: 'GPFT %', field: 'gpft', hozAlign: 'center', sorter: 'number', width: 75, formatter: (c) => pctCell(c, 25, 10)},
                    {title: 'ROI %', field: 'roi', hozAlign: 'center', sorter: 'number', width: 75, formatter: (c) => pctCell(c, 50, 20)},
                    {title: 'Sales L30', field: 'sales_l30', hozAlign: 'right', sorter: 'number', width: 90, formatter: (c) => moneyCell(c, 0)},
                    {title: 'Profit L30', field: 'profit_l30', hozAlign: 'right', sorter: 'number', width: 90, formatter: (c) => moneyCell(c, 0)},
                    {title: 'Stock @ Cost', field: 'stock_cost', hozAlign: 'right', sorter: 'number', width: 95, formatter: (c) => moneyCell(c, 0)},
                    {title: 'Stock @ Price', field: 'stock_value', hozAlign: 'right', sorter: 'number', width: 100, formatter: (c) => moneyCell(c, 0)},
                ],
            });

            function applyFilters() {
                const stock = document.getElementById('stock-filter').value;
                const l30 = document.getElementById('l30-filter').value;
                const base = document.getElementById('base-filter').value;
                const q = document.getElementById('sku-search').value.trim().toLowerCase();

                table.clearFilter(false);
                if (stock === 'zero') table.addFilter((d) => num(d.inv) === 0);
                if (stock === 'more') table.addFilter((d) => num(d.inv) > 0);
                if (l30 === 'zero') table.addFilter((d) => num(d.l30) === 0);
                if (l30 === 'more') table.addFilter((d) => num(d.l30) > 0);
                if (base === 'missing') table.addFilter((d) => !!d.base_missing);
                if (base === 'found') table.addFilter((d) => !d.base_missing);
                if (q) table.addFilter((d) => String(d.sku || '').toLowerCase().includes(q));
                updateSummary();
            }

            // Summary is its own calculation over the rows currently in view.
            function updateSummary() {
                const rows = table.getData('active');
                let stock = 0, stockCost = 0, stockValue = 0, zeroSold = 0, moreSold = 0;
                let soldUnits = 0, sales = 0, profit = 0, cogs = 0, noBase = 0;
                rows.forEach((r) => {
                    stock += num(r.inv);
                    stockCost += num(r.stock_cost);
                    stockValue += num(r.stock_value);
                    soldUnits += num(r.l30);
                    sales += num(r.sales_l30);
                    profit += num(r.profit_l30);
                    cogs += num(r.lp) * num(r.l30);
                    if (num(r.l30) === 0) zeroSold++; else moreSold++;
                    if (r.base_missing) noBase++;
                });
                const gpft = sales > 0 ? (profit / sales) * 100 : 0;
                const groi = cogs > 0 ? (profit / cogs) * 100 : 0;
                const dil = stock > 0 ? (soldUnits / stock) * 100 : 0;
                const set = (id, t) => { document.getElementById(id).textContent = t; };
                set('b-skus', 'SKUs: ' + rows.length.toLocaleString());
                set('b-stock', 'S Stock: ' + Math.round(stock).toLocaleString());
                set('b-stock-cost', 'Stock @ Cost: ' + money(stockCost));
                set('b-stock-value', 'Stock @ Price: ' + money(stockValue));
                set('b-zero-sold', '0 Sold: ' + zeroSold.toLocaleString());
                set('b-more-sold', '> 0 Sold: ' + moreSold.toLocaleString());
                set('b-sold-units', 'OV L30: ' + Math.round(soldUnits).toLocaleString());
                set('b-sales', 'Sales L30: ' + money(sales));
                set('b-profit', 'Profit L30: ' + money(profit));
                set('b-gpft', 'GPFT: ' + Math.round(gpft) + '%');
                set('b-groi', 'GROI: ' + Math.round(groi) + '%');
                set('b-dil', 'Dil: ' + Math.round(dil) + '%');
                set('b-nobase', 'No Base SKU: ' + noBase.toLocaleString());
            }

            function load() {
                const status = document.getElementById('sob-status');
                status.textContent = 'Loading…';
                fetch(DATA_URL, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                    .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                    .then((res) => table.setData(res.data || []))
                    .then(() => { status.textContent = ''; applyFilters(); })
                    .catch((e) => { status.textContent = 'Failed to load: ' + e.message; });
            }

            ['stock-filter', 'l30-filter', 'base-filter'].forEach((id) =>
                document.getElementById(id).addEventListener('change', applyFilters));
            document.getElementById('sku-search').addEventListener('input', applyFilters);
            document.getElementById('reload-btn').addEventListener('click', load);
            document.getElementById('export-btn').addEventListener('click', () =>
                table.download('csv', 'shopify-open-box-analytics.csv'));
            document.getElementById('sob-table').addEventListener('click', (e) => {
                const el = e.target.closest('.sob-copy');
                if (el && navigator.clipboard) navigator.clipboard.writeText(el.dataset.sku);
            });

            table.on('tableBuilt', load);
        });
    </script>
@endsection
