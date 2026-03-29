@extends('layouts.vertical', ['title' => 'AliExpress Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
        }

        .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'AliExpress Pricing',
        'sub_title' => 'Separate pricing page (sales page unchanged)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <input type="text" id="pricing-sku-search" class="form-control form-control-sm" style="max-width: 260px;"
                            placeholder="Search SKU...">
                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary">Refresh</button>
                        <button type="button" id="export-pricing-btn" class="btn btn-sm btn-success">Export CSV</button>
                        <a href="{{ route('aliexpress.pricing.price.sample') }}" class="btn btn-sm btn-outline-secondary">Download Price Sample</a>
                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadPriceSheetModal">Upload Price Sheet</button>
                    </div>

                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-secondary fs-6 p-2" id="ae-total-sku-badge" style="font-weight: 700;">Total SKU: 0</span>
                            <span class="badge bg-primary fs-6 p-2" id="ae-total-sales-badge" style="font-weight: 700;">Total Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2" id="ae-total-al30-badge" style="font-weight: 700; color: #111;">Total AL30: 0</span>
                            <span class="badge bg-success fs-6 p-2" id="ae-total-profit-badge" style="font-weight: 700;">Total Profit: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="ae-avg-gpft-badge" style="font-weight: 700; color: #111;">AVG GPFT: 0%</span>
                            <span class="badge bg-danger fs-6 p-2" id="ae-missing-badge" style="font-weight: 700;">Missing: 0</span>
                            <span class="badge bg-dark fs-6 p-2" id="ae-map-badge" style="font-weight: 700;">Map: 0</span>
                        </div>
                    </div>

                    <div id="aliexpress-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadPriceSheetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Pricing Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="priceSheetFile" accept=".xlsx,.xls,.csv">
                    <small class="text-muted">Required headers: sku, price</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="uploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let table = null;
        let summaryDataCache = [];

        function money(value) {
            return `$${(parseFloat(value) || 0).toFixed(2)}`;
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === "object") {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === "function") {
                        return row.getData();
                    }
                    return row || {};
                });
            }
            return [];
        }

        function updateSummary(rowsInput = null) {
            let rows = normalizeRows(rowsInput);

            if (!rows.length && table && typeof table.getData === "function") {
                const activeRows = normalizeRows(table.getData("active"));
                const allRows = normalizeRows(table.getData());
                rows = activeRows.length ? activeRows : allRows;
            }
            if (!rows.length) {
                rows = normalizeRows(summaryDataCache);
            }

            let totalSales = 0;
            let totalAl30 = 0;
            let totalProfit = 0;
            let gpftSum = 0;
            let gpftCount = 0;
            let missingCount = 0;
            let mapCount = 0;

            rows.forEach(row => {
                totalSales += parseFloat(row.sales) || 0;
                totalAl30 += parseInt(row.al30, 10) || 0;
                totalProfit += parseFloat(row.profit) || 0;

                const gpft = parseFloat(row.gpft);
                if (Number.isFinite(gpft)) {
                    gpftSum += gpft;
                    gpftCount++;
                }

                if ((row.missing || '').toString().trim().toUpperCase() === 'M') {
                    missingCount++;
                }
                if ((row.map || '').toString().trim().toUpperCase() === 'MAP') {
                    mapCount++;
                }
            });

            const avgGpft = gpftCount > 0 ? gpftSum / gpftCount : 0;
            $('#ae-total-sku-badge').text(`Total SKU: ${rows.length.toLocaleString()}`);
            $('#ae-total-sales-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#ae-total-al30-badge').text(`Total AL30: ${totalAl30.toLocaleString()}`);
            $('#ae-total-profit-badge').text(`Total Profit: $${Math.round(totalProfit).toLocaleString()}`);
            $('#ae-avg-gpft-badge').text(`AVG GPFT: ${avgGpft.toFixed(1)}%`);
            $('#ae-missing-badge').text(`Missing: ${missingCount.toLocaleString()}`);
            $('#ae-map-badge').text(`Map: ${mapCount.toLocaleString()}`);
        }

        $(document).ready(function() {
            table = new Tabulator("#aliexpress-pricing-table", {
                ajaxURL: "/aliexpress/pricing-data",
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    return response;
                },
                layout: "fitColumns",
                pagination: true,
                paginationSize: 100,
                initialSort: [{
                    column: "sku",
                    dir: "asc"
                }],
                columns: [{
                        title: "SKU",
                        field: "sku",
                        minWidth: 220,
                        headerFilter: "input",
                        frozen: true,
                        cssClass: "fw-bold text-primary",
                        sorter: function(a, b) {
                            const av = (a || '').toString().trim().toUpperCase();
                            const bv = (b || '').toString().trim().toUpperCase();
                            const aLetter = /^[A-Z]/.test(av);
                            const bLetter = /^[A-Z]/.test(bv);

                            if (aLetter !== bLetter) {
                                return aLetter ? -1 : 1;
                            }

                            return av.localeCompare(bv, undefined, {
                                numeric: true,
                                sensitivity: "base"
                            });
                        }
                    },
                    {
                        title: "Price",
                        field: "price",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "Missing",
                        field: "missing",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = (cell.getValue() || '').toString().trim().toUpperCase();
                            if (value === 'M') {
                                return '<span class="badge bg-danger">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "Map",
                        field: "map",
                        hozAlign: "center"
                    },
                    {
                        title: "GPFT",
                        field: "gpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => `${(parseFloat(cell.getValue()) || 0).toFixed(2)}%`
                    },
                    {
                        title: "GROI",
                        field: "groi",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => `${(parseFloat(cell.getValue()) || 0).toFixed(2)}%`
                    },
                    {
                        title: "Profit",
                        field: "profit",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "Sales",
                        field: "sales",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "AL30",
                        field: "al30",
                        sorter: "number",
                        hozAlign: "center",
                        formatter: cell => parseInt(cell.getValue(), 10) || 0
                    },
                    {
                        title: "LP",
                        field: "lp",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "Ship",
                        field: "ship",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "Sprice",
                        field: "sprice",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => money(cell.getValue())
                    },
                    {
                        title: "SGPFT",
                        field: "sgpft",
                        sorter: "number",
                        hozAlign: "right",
                        formatter: cell => `${(parseFloat(cell.getValue()) || 0).toFixed(2)}%`
                    },
                ],
                dataLoaded: function(data) {
                    updateSummary(data);
                },
                dataFiltered: function(filters, rows) {
                    updateSummary(rows);
                },
                dataProcessed: function() {
                    updateSummary();
                },
                renderComplete: function() {
                    updateSummary();
                }
            });

            $('#pricing-sku-search').on('input', function() {
                table.setFilter("sku", "like", $(this).val());
            });

            $('#refresh-pricing-table').on('click', function() {
                table.setData("/aliexpress/pricing-data");
            });

            $('#export-pricing-btn').on('click', function() {
                table.download("csv", "aliexpress_pricing_data.csv");
            });

            $('#uploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('priceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }

                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');

                $.ajax({
                    url: '/aliexpress/pricing-upload-price',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (window.toastr) {
                            toastr.success(response.message || 'Price upload completed.');
                        } else {
                            alert(response.message || 'Price upload completed.');
                        }
                        $('#uploadPriceSheetModal').modal('hide');
                        $('#priceSheetFile').val('');
                        table.setData('/aliexpress/pricing-data');
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.message || 'Price upload failed.';
                        if (window.toastr) {
                            toastr.error(message);
                        } else {
                            alert(message);
                        }
                    }
                });
            });
        });
    </script>
@endsection
