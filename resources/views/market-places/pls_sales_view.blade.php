@extends('layouts.vertical', ['title' => 'PLS Sales (Last 30 Days)', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap; font-size: 12px; font-weight: 600;
        }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'PLS Sales (Last 30 Days)',
        'sub_title'  => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>PLS Sales - Last 30 Days</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">

                    <!-- Financial Status Filter -->
                    <select id="financial-status-filter" class="form-select form-select-sm" style="width:auto;">
                        <option value="all">All Financial Status</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="refunded">Refunded</option>
                        <option value="voided">Voided</option>
                    </select>

                    <!-- Fulfillment Status Filter -->
                    <select id="fulfillment-status-filter" class="form-select form-select-sm" style="width:auto;">
                        <option value="all">All Fulfillment Status</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="unfulfilled">Unfulfilled</option>
                        <option value="partial">Partial</option>
                    </select>

                    <!-- Column Visibility -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            data-bs-toggle="dropdown">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" style="max-height:400px;overflow-y:auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <!-- Export -->
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                </div>

                <!-- Summary Badges -->
                <div class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (Last 30 Days)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge">Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-qty-badge">Total Qty: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge">Revenue: $0</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-discount-badge">Discount: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="avg-price-badge">Avg Price: $0</span>
                        <span class="badge bg-success fs-6 p-2" id="paid-count-badge">Paid: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="fulfilled-count-badge">Fulfilled: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div style="height:calc(100vh - 220px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU or Order...">
                    </div>
                    <div id="pls-sales-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;

    function showToast(msg, type = 'info') {
        const c = document.querySelector('.toast-container');
        if (!c) return;
        const t = document.createElement('div');
        t.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        t.setAttribute('role', 'alert');
        t.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        c.appendChild(t);
        new bootstrap.Toast(t).show();
        t.addEventListener('hidden.bs.toast', () => t.remove());
    }

    $(document).ready(function () {

        // Financial Status filter
        $('#financial-status-filter').on('change', function () { applyFilters(); });

        // Fulfillment Status filter
        $('#fulfillment-status-filter').on('change', function () { applyFilters(); });

        // SKU search
        $('#sku-search').on('keyup', function () {
            const val = $(this).val();
            if (val) {
                table.addFilter(function (data) {
                    return (data.sku || '').toLowerCase().includes(val.toLowerCase()) ||
                           (data.order_number || '').toLowerCase().includes(val.toLowerCase()) ||
                           (data.order_name || '').toLowerCase().includes(val.toLowerCase());
                });
            } else {
                table.clearFilter();
                applyFilters();
            }
        });

        function applyFilters() {
            table.clearFilter();
            const financialStatus = $('#financial-status-filter').val();
            const fulfillmentStatus = $('#fulfillment-status-filter').val();
            
            if (financialStatus !== 'all') table.addFilter('financial_status', '=', financialStatus);
            if (fulfillmentStatus !== 'all') table.addFilter('fulfillment_status', '=', fulfillmentStatus);
            
            updateSummary();
        }

        function statusColor(status) {
            if (!status) return '#6c757d';
            const s = status.toLowerCase();
            if (s === 'paid') return '#28a745';
            if (s === 'fulfilled') return '#28a745';
            if (s === 'pending') return '#ffc107';
            if (s === 'unfulfilled') return '#ffc107';
            if (s === 'refunded' || s === 'voided') return '#dc3545';
            return '#6c757d';
        }

        // Initialize Tabulator
        table = new Tabulator('#pls-sales-table', {
            ajaxURL: '/pls-sales-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            paginationCounter: 'rows',
            langs: { default: { pagination: { page_size: 'Rows' } } },
            initialSort: [{ column: 'order_date', dir: 'desc' }],
            columns: [
                {
                    title: 'Date', field: 'order_date', width: 100, hozAlign: 'center', sorter: 'string',
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Order #', field: 'order_name', width: 120, sorter: 'string',
                    formatter: function (cell) {
                        return `<span style="font-size:11px;font-family:monospace;font-weight:600;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Order Number', field: 'order_number', width: 100, visible: false,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'SKU', field: 'sku', width: 160,
                    headerFilter: 'input', headerFilterPlaceholder: 'Search SKU...',
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        return `<span style="font-weight:600;color:#0d6efd;font-size:11px;">${v}</span>`;
                    }
                },
                {
                    title: 'Product', field: 'product_title', width: 250, tooltip: true,
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const short = v.length > 60 ? v.substring(0, 60) + '…' : v;
                        return `<span style="font-size:11px;" title="${v.replace(/"/g, '&quot;')}">${short}</span>`;
                    }
                },
                {
                    title: 'Variant', field: 'variant_title', width: 120,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || 'Default'}</span>`;
                    }
                },
                { 
                    title: 'Qty', field: 'quantity', width: 60, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        return `<span style="font-weight:600;">${cell.getValue() || 0}</span>`;
                    }
                },
                {
                    title: 'Price', field: 'price', width: 80, hozAlign: 'right', sorter: 'number',
                    formatter: function (cell) {
                        return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`;
                    }
                },
                {
                    title: 'Total', field: 'total_amount', width: 90, hozAlign: 'right', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight:600;color:#28a745;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Discount', field: 'discount_amount', width: 80, hozAlign: 'right', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="color:#dc3545;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Tax', field: 'tax_amount', width: 70, hozAlign: 'right', sorter: 'number',
                    formatter: function (cell) {
                        return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`;
                    }
                },
                {
                    title: 'Financial', field: 'financial_status', width: 100, hozAlign: 'center',
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const color = statusColor(v);
                        return `<span style="color:${color};font-weight:600;font-size:11px;text-transform:capitalize;">${v}</span>`;
                    }
                },
                {
                    title: 'Fulfillment', field: 'fulfillment_status', width: 110, hozAlign: 'center',
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const color = statusColor(v);
                        return `<span style="color:${color};font-weight:600;font-size:11px;text-transform:capitalize;">${v}</span>`;
                    }
                },
                {
                    title: 'Customer', field: 'customer_name', width: 150,
                    formatter: function (cell) {
                        const name = cell.getValue() || '';
                        const email = cell.getRow().getData().customer_email || '';
                        return `<span style="font-size:11px;">${name}</span>
                                <br><span style="font-size:10px;color:#6c757d;">${email}</span>`;
                    }
                },
                {
                    title: 'Customer Email', field: 'customer_email', width: 150, visible: false,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
            ]
        });

        // Summary update
        function updateSummary() {
            const data = table.getData('active');
            let uniqueOrders = new Set();
            let totalQty = 0;
            let totalRevenue = 0;
            let totalDiscount = 0;
            let totalPrice = 0;
            let priceCount = 0;
            let paidCount = 0;
            let fulfilledCount = 0;

            data.forEach(row => {
                const qty = parseInt(row.quantity, 10) || 0;
                const amount = parseFloat(row.total_amount) || 0;
                const discount = parseFloat(row.discount_amount) || 0;
                const price = parseFloat(row.price) || 0;

                if (row.order_name) uniqueOrders.add(row.order_name);
                
                totalQty += qty;
                totalRevenue += amount;
                totalDiscount += discount;

                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }

                if ((row.financial_status || '').toLowerCase() === 'paid') paidCount++;
                if ((row.fulfillment_status || '').toLowerCase() === 'fulfilled') fulfilledCount++;
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;

            $('#total-orders-badge').text(`Orders: ${uniqueOrders.size}`);
            $('#total-qty-badge').text(`Total Qty: ${totalQty.toLocaleString()}`);
            $('#total-revenue-badge').text(`Revenue: $${Math.round(totalRevenue).toLocaleString()}`);
            $('#total-discount-badge').text(`Discount: $${Math.round(totalDiscount).toLocaleString()}`);
            $('#avg-price-badge').text(`Avg Price: $${avgPrice.toFixed(2)}`);
            $('#paid-count-badge').text(`Paid: ${paidCount}`);
            $('#fulfilled-count-badge').text(`Fulfilled: ${fulfilledCount}`);
        }

        table.on('dataLoaded', function () { setTimeout(updateSummary, 100); });
        table.on('dataFiltered', function () { setTimeout(updateSummary, 100); });
        table.on('renderComplete', function () { setTimeout(updateSummary, 100); });

        // Column dropdown
        function buildColumnDropdown() {
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        table.on('tableBuilt', buildColumnDropdown);

        document.getElementById('column-dropdown-menu').addEventListener('change', function (e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) e.target.checked ? col.show() : col.hide();
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function () {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
        });

        // Export CSV
        document.getElementById('export-btn').addEventListener('click', function () {
            const visibleCols = table.getColumns().filter(c => c.isVisible());
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField());
            const rows = table.getData('active').map(row =>
                visibleCols.map(col => {
                    let v = row[col.getField()];
                    if (v === null || v === undefined) return '';
                    if (typeof v === 'number') return parseFloat(v.toFixed(2));
                    if (typeof v === 'string' && (v.includes(',') || v.includes('"')))
                        return '"' + v.replace(/"/g, '""') + '"';
                    return v;
                })
            );
            const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
            link.download = 'pls_sales_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });
    });
</script>
@endsection
