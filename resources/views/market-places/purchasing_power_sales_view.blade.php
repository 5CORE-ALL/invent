@extends('layouts.vertical', ['title' => 'Purchasing Power Sales', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; white-space: nowrap;
            transform: rotate(180deg); height: 80px; display: flex;
            align-items: center; justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0px !important; }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Purchasing Power Sales',
        'sub_title'  => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Purchasing Power Sales</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">

                    <!-- Status Filter -->
                    <select id="status-filter" class="form-select form-select-sm" style="width:auto;">
                        <option value="all">All Status</option>
                        <option value="Received">Received</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Awaiting shipment">Awaiting shipment</option>
                        <option value="Canceled">Canceled</option>
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

                    <!-- Upload -->
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadSalesModal">
                        <i class="fa fa-upload"></i> Upload Sales File
                    </button>
                </div>

                <!-- Summary Badges (margin from marketplace_percentages.marketplace = Purchase; default 65%) -->
                <div class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary — matches All Marketplace Master rollups (full dataset; Purchase margin: {{ number_format($ppMargin ?? 65, 2) }}%)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color:white;font-weight:bold;">Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-qty-badge" style="color:white;font-weight:bold;">Total Qty: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-revenue-badge" style="color:black;font-weight:bold;">Revenue: $0</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-commission-badge" style="color:black;font-weight:bold;">Commission: $0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-transferred-badge" style="color:white;font-weight:bold;">Transferred: $0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-pft-badge" style="color:white;font-weight:bold;">PFT: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="gpft-rev-badge" style="color:white;font-weight:bold;" title="Total PFT ÷ revenue (non-canceled lines)">GPFT % (rev): 0%</span>
                        <span class="badge fs-6 p-2" id="groi-badge" style="background-color:#6f42c1;color:white;font-weight:bold;" title="Total PFT ÷ COGS (LP × qty), non-canceled">GROI %: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="canceled-badge" style="color:white;font-weight:bold;">Canceled: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="avg-price-badge" style="color:white;font-weight:bold;">Avg Price: $0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div style="height:calc(100vh - 220px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU or Order...">
                    </div>
                    <div id="pp-sales-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadSalesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa fa-upload me-2"></i>Upload Purchasing Power Sales File</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Choose File</label>
                        <input type="file" class="form-control" id="salesFile" accept=".xlsx,.xls,.csv,.tsv,.txt">
                        <small class="text-muted">Supported: Excel (.xlsx/.xls), CSV, TSV, TXT (tab-separated from Purchasing Power portal)</small>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This will replace all existing sales data.
                    </div>
                    <div id="upload-progress-wrap" style="display:none;" class="mt-3">
                        <div class="progress" style="height:25px;">
                            <div id="upload-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width:0%">Uploading...</div>
                        </div>
                    </div>
                    <div id="upload-result" class="alert mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="upload-sales-btn" class="btn btn-success">
                        <i class="fa fa-upload me-1"></i> Upload
                    </button>
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

        // Upload button
        $('#upload-sales-btn').on('click', function () {
            const file = $('#salesFile')[0].files[0];
            if (!file) { showToast('Please select a file first', 'error'); return; }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            $('#upload-progress-wrap').show();
            $('#upload-progress-bar').css('width', '50%').text('Uploading...');
            $('#upload-result').hide();
            $(this).prop('disabled', true);

            $.ajax({
                url: '/pp-sales-upload',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (res) {
                    $('#upload-progress-bar').css('width', '100%').text('Done!');
                    $('#upload-result')
                        .removeClass('alert-danger').addClass('alert-success')
                        .html(`<i class="fa fa-check-circle me-2"></i>${res.message}`)
                        .show();
                    showToast(res.message, 'success');
                    setTimeout(() => {
                        $('#uploadSalesModal').modal('hide');
                        table.setData();
                    }, 1200);
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.error || 'Upload failed';
                    $('#upload-progress-bar').css('width', '100%').addClass('bg-danger').text('Error');
                    $('#upload-result')
                        .removeClass('alert-success').addClass('alert-danger')
                        .html(`<i class="fa fa-times-circle me-2"></i>${msg}`)
                        .show();
                    showToast(msg, 'error');
                },
                complete: function () {
                    $('#upload-sales-btn').prop('disabled', false);
                }
            });
        });

        // Reset modal on close
        $('#uploadSalesModal').on('hidden.bs.modal', function () {
            $('#salesFile').val('');
            $('#upload-progress-wrap').hide();
            $('#upload-progress-bar').css('width', '0%').removeClass('bg-danger').text('Uploading...');
            $('#upload-result').hide();
        });

        // Status filter
        $('#status-filter').on('change', function () { applyFilters(); });

        // SKU search
        $('#sku-search').on('keyup', function () {
            const val = $(this).val();
            if (val) {
                table.addFilter(function (data) {
                    return (data.product_sku || '').toLowerCase().includes(val.toLowerCase()) ||
                           (data.offer_sku || '').toLowerCase().includes(val.toLowerCase()) ||
                           (data.order_number || '').toLowerCase().includes(val.toLowerCase());
                });
            } else {
                table.clearFilter();
                applyFilters();
            }
        });

        function applyFilters() {
            table.clearFilter();
            const status = $('#status-filter').val();
            if (status !== 'all') table.addFilter('status', '=', status);
            updateSummary();
        }

        function statusColor(status) {
            if (!status) return '#6c757d';
            const s = status.toLowerCase();
            if (s === 'received') return '#28a745';
            if (s === 'shipped') return '#17a2b8';
            if (s.includes('await')) return '#ffc107';
            if (s === 'canceled') return '#dc3545';
            return '#6c757d';
        }

        // Initialize Tabulator
        table = new Tabulator('#pp-sales-table', {
            ajaxURL: '/pp-sales-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            paginationCounter: 'rows',
            langs: { default: { pagination: { page_size: 'Rows' } } },
            initialSort: [{ column: 'date_created', dir: 'desc' }],
            columns: [
                {
                    title: 'Date', field: 'date_created', width: 90, hozAlign: 'center', sorter: 'string',
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Order #', field: 'order_number', width: 160, sorter: 'string',
                    formatter: function (cell) {
                        return `<span style="font-size:11px;font-family:monospace;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Status', field: 'status', width: 110, hozAlign: 'center',
                    headerFilter: 'input',
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const color = statusColor(v);
                        return `<span style="color:${color};font-weight:600;font-size:11px;">${v}</span>`;
                    }
                },
                {
                    title: 'Offer SKU', field: 'product_sku', width: 160,
                    headerFilter: 'input', headerFilterPlaceholder: 'Search SKU...',
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const mirakl = cell.getRow().getData().mirakl_product_sku || '';
                        return `<span style="font-weight:600;color:#0d6efd;">${v}</span>
                                <br><span style="font-size:10px;color:#6c757d;">ID: ${mirakl}</span>`;
                    }
                },
                {
                    title: 'Offer SKU (dup)', field: 'offer_sku', width: 150, visible: false,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Product Name', field: 'product_name', width: 250, tooltip: true,
                    formatter: function (cell) {
                        const v = cell.getValue() || '';
                        const short = v.length > 60 ? v.substring(0, 60) + '…' : v;
                        return `<span style="font-size:11px;" title="${v.replace(/"/g, '&quot;')}">${short}</span>`;
                    }
                },
                { title: 'Qty', field: 'quantity', width: 50, hozAlign: 'center', sorter: 'number' },
                {
                    title: 'Unit Price', field: 'unit_price', width: 70, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`;
                    }
                },
                {
                    title: 'Amount', field: 'amount', width: 70, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight:600;color:#28a745;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Commission', field: 'commission', width: 80, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="color:#dc3545;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Comm Rule', field: 'commission_rule', width: 110,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Transferred', field: 'amount_transferred', width: 80, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight:600;color:#17a2b8;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'GPFT%', field: 'gpft_pct', width: 60, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const color = v < 10 ? '#dc3545' : v < 20 ? '#ffc107' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${v.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'GROI%', field: 'groi_pct', width: 58, hozAlign: 'center', sorter: 'number',
                    formatter: function (cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const color = v < 40 ? '#dc3545' : v < 100 ? '#ffc107' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${v.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'Category', field: 'category_label', width: 140,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Carrier', field: 'shipping_company', width: 100,
                    formatter: function (cell) {
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>`;
                    }
                },
                {
                    title: 'Tracking', field: 'tracking_number', width: 160,
                    formatter: function (cell) {
                        const num = cell.getValue() || '';
                        const url = cell.getRow().getData().tracking_url || '';
                        if (url) {
                            return `<a href="${url}" target="_blank" style="font-size:11px;font-family:monospace;">${num}</a>`;
                        }
                        return `<span style="font-size:11px;font-family:monospace;">${num}</span>`;
                    }
                },
                {
                    title: 'Customer', field: 'customer', width: 140,
                    formatter: function (cell) {
                        const d = cell.getRow().getData();
                        const loc = [d.city, d.state, d.country].filter(Boolean).join(', ');
                        return `<span style="font-size:11px;">${cell.getValue() || ''}</span>
                                <br><span style="font-size:10px;color:#6c757d;">${loc}</span>`;
                    }
                },
                {
                    title: 'Order ID', field: 'order_id', width: 90, hozAlign: 'center',
                    formatter: function (cell) {
                        return `<span style="font-size:11px;font-family:monospace;">${cell.getValue() || ''}</span>`;
                    }
                },
            ]
        });

        function isCanceledRow(row) {
            const s = (row.status || '').toLowerCase().replace(/\s+/g, ' ').trim();
            return s === 'canceled' || s === 'cancelled';
        }

        /**
         * Same line revenue as UpdateMarketplaceDailyMetrics::calculatePurchasingPowerMetrics (non-canceled, qty > 0).
         */
        function lineRevenueRollup(row) {
            if (isCanceledRow(row)) {
                return 0;
            }
            const qty = parseInt(row.quantity, 10) || 0;
            if (qty <= 0) {
                return 0;
            }
            let unit = parseFloat(row.unit_price) || 0;
            if (unit <= 0) {
                const amt = parseFloat(row.amount) || 0;
                unit = qty > 0 ? amt / qty : 0;
            }
            return unit * qty;
        }

        // Summary update — use full table data (not filtered) so totals match All Marketplace Master / artisan metrics
        function updateSummary() {
            const data = table.getData();
            let rollupOrders = 0;
            let rollupQty = 0;
            let rollupRevenue = 0;
            let rollupPft = 0;
            let rollupCogs = 0;
            let totalCommission = 0;
            let totalTransferred = 0;
            let canceledCount = 0;
            let totalPrice = 0;
            let priceCount = 0;

            data.forEach(row => {
                const qty = parseInt(row.quantity, 10) || 0;
                const amount = parseFloat(row.amount) || 0;
                const comm = parseFloat(row.commission) || 0;
                const transferred = parseFloat(row.amount_transferred) || 0;
                const pft = parseFloat(row.pft) || 0;
                const price = parseFloat(row.unit_price) || 0;

                if (isCanceledRow(row)) {
                    canceledCount++;
                } else {
                    rollupOrders++;
                }

                totalCommission += comm;
                totalTransferred += transferred;

                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }

                if (!isCanceledRow(row) && qty > 0) {
                    const lr = lineRevenueRollup(row);
                    rollupRevenue += lr;
                    rollupQty += qty;
                    rollupPft += pft;
                    rollupCogs += parseFloat(row.cogs) || 0;
                }
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const gpftRevPct = rollupRevenue > 0 ? (rollupPft / rollupRevenue) * 100 : 0;
            const groiPct = rollupCogs > 0 ? (rollupPft / rollupCogs) * 100 : 0;

            $('#total-orders-badge').text(`Orders: ${rollupOrders.toLocaleString()}`);
            $('#total-qty-badge').text(`Total Qty: ${rollupQty.toLocaleString()}`);
            $('#total-revenue-badge').text(`Revenue: $${Math.round(rollupRevenue).toLocaleString()}`);
            $('#total-commission-badge').text(`Commission: $${Math.round(totalCommission).toLocaleString()}`);
            $('#total-transferred-badge').text(`Transferred: $${Math.round(totalTransferred).toLocaleString()}`);
            $('#total-pft-badge').text(`PFT: $${Math.round(rollupPft).toLocaleString()}`);
            $('#gpft-rev-badge').text(`GPFT % (rev): ${gpftRevPct.toFixed(1)}%`);
            $('#groi-badge').text(`GROI %: ${groiPct.toFixed(1)}%`);
            $('#canceled-badge').text(`Canceled: ${canceledCount}`);
            $('#avg-price-badge').text(`Avg Price: $${avgPrice.toFixed(2)}`);

            const gpftEl = $('#gpft-rev-badge');
            if (gpftRevPct >= 0) {
                gpftEl.removeClass('bg-danger').addClass('bg-success');
            } else {
                gpftEl.removeClass('bg-success').addClass('bg-danger');
            }

            const groiEl = $('#groi-badge');
            if (groiPct >= 0) { groiEl.css({ backgroundColor: '#6f42c1', color: '#fff' }); }
            else { groiEl.css({ backgroundColor: '#dc3545', color: '#fff' }); }
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
            link.download = 'pp_sales_' + new Date().toISOString().slice(0, 10) + '.csv';
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });
    });
</script>
@endsection
