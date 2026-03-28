@extends('layouts.vertical', ['title' => 'Faire Daily Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator {
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 6px 4px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            font-weight: 600;
            color: #212529;
            white-space: nowrap;
        }

        .tabulator .tabulator-row {
            min-height: 32px;
        }

        .tabulator .tabulator-row:nth-child(even) {
            background-color: #fcfcfd;
        }

        .tabulator .tabulator-row:hover {
            background-color: #f1f5ff;
        }

        .tabulator .tabulator-cell {
            padding: 6px 8px;
            border-right: 1px solid #f1f3f5;
            white-space: nowrap;
        }

        .tabulator .tabulator-footer {
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Faire Daily Data',
        'sub_title' => 'Faire Daily Data Upload and View',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Faire Daily Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDailyDataModal">
                        <i class="fa fa-upload"></i> Upload Daily Data
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-sales-badge" style="color: white; font-weight: bold;">Total Sales: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;">PFT %: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;">PFT Total: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">Total COGS: $0.00</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="faire-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="faire-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadDailyDataModal" tabindex="-1" aria-labelledby="uploadDailyDataModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDailyDataModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Faire Daily Data
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dailyDataFile" class="form-label">Select Excel File</label>
                        <input type="file" class="form-control" id="dailyDataFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">Supported formats: Excel (.xlsx, .xls) or CSV</div>
                    </div>

                    <div id="uploadProgressContainer" style="display: none;">
                        <div class="mb-2"><strong>Upload Progress:</strong></div>
                        <div class="progress mb-2" style="height: 25px;">
                            <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated"
                                role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="uploadStatus" class="text-muted small"></div>
                    </div>

                    <div id="uploadResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startUploadBtn">
                        <i class="fa fa-upload me-1"></i>Start Upload
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        table = new Tabulator("#faire-table", {
            ajaxURL: "/faire/daily-data",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [{
                column: "order_date",
                dir: "desc"
            }],
            columns: [
                { title: "Order Date", field: "order_date", width: 120 },
                { title: "Order Number", field: "order_number", width: 150 },
                { title: "PO Number", field: "purchase_order_number", width: 150, visible: false },
                { title: "Retailer Name", field: "retailer_name", width: 180, visible: false },
                { title: "Address 1", field: "address_1", width: 180, visible: false },
                { title: "Address 2", field: "address_2", width: 150, visible: false },
                { title: "City", field: "city", width: 120, visible: false },
                { title: "State", field: "state", width: 90, visible: false },
                { title: "Zip Code", field: "zip_code", width: 110, visible: false },
                { title: "Country", field: "country", width: 130, visible: false },
                { title: "Product Name", field: "product_name", width: 250, visible: false },
                { title: "Option Name", field: "option_name", width: 120, visible: false },
                { title: "SKU", field: "sku", width: 150 },
                { title: "GTIN", field: "gtin", width: 130, visible: false },
                { title: "Status", field: "status", width: 110 },
                { title: "Quantity", field: "quantity", width: 90, hozAlign: "center", sorter: "number" },
                {
                    title: "Price",
                    field: "price",
                    width: 130,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: { symbol: "$", precision: 2 }
                },
                {
                    title: "LP",
                    field: "lp",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: { symbol: "$", precision: 2 }
                },
                {
                    title: "PFT Each",
                    field: "pft_each",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "PFT %",
                    field: "pft_each_pct",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
                {
                    title: "T PFT",
                    field: "pft",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${value.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "ROI %",
                    field: "roi",
                    width: 100,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#6c757d';
                        if (value < 50) color = '#dc3545';
                        else if (value >= 50 && value < 75) color = '#ffc107';
                        else if (value >= 75 && value <= 125) color = '#28a745';
                        else if (value > 125) color = '#e83e8c';
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(0)}%</span>`;
                    }
                },
                { title: "Ship Date", field: "ship_date", width: 120 },
                { title: "Scheduled Order Date", field: "scheduled_order_date", width: 170, visible: false },
                { title: "Notes", field: "notes", width: 150, visible: false },
            ]
        });

        function updateSummary() {
            const data = table.getData("active");
            const uniqueOrders = new Set();
            let totalQuantity = 0;
            let totalSales = 0;
            let totalPft = 0;
            let totalCogs = 0;
            let totalWeightedPrice = 0;
            let totalQuantityForPrice = 0;

            data.forEach(row => {
                const orderNumber = (row.order_number || '').toString().trim();
                if (orderNumber !== '') {
                    uniqueOrders.add(orderNumber);
                }

                const quantity = parseInt(row.quantity, 10) || 0;
                const price = parseFloat(row.price) || 0;
                const pft = parseFloat(row.pft) || 0;
                const cogs = parseFloat(row.cogs) || 0;

                totalQuantity += quantity;
                totalSales += price;
                totalPft += pft;
                totalCogs += cogs;

                if (quantity > 0 && price > 0) {
                    totalWeightedPrice += price * quantity;
                    totalQuantityForPrice += quantity;
                }
            });

            const avgPrice = totalQuantityForPrice > 0 ? totalWeightedPrice / totalQuantityForPrice : 0;
            const pftPercentage = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
            const roiPercentage = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-orders-badge').text('Total Orders: ' + uniqueOrders.size.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#total-sales-badge').text('Total Sales: $' + totalSales.toFixed(2));
            $('#pft-percentage-badge').text('PFT %: ' + pftPercentage.toFixed(1) + '%');
            $('#roi-percentage-badge').text('ROI %: ' + roiPercentage.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#pft-total-badge').text('PFT Total: $' + totalPft.toFixed(2));
            $('#total-cogs-badge').text('Total COGS: $' + totalCogs.toFixed(2));

            const pftPctBadge = $('#pft-percentage-badge');
            if (pftPercentage >= 0) {
                pftPctBadge.removeClass('bg-danger').addClass('bg-success');
            } else {
                pftPctBadge.removeClass('bg-success').addClass('bg-danger');
            }

            const roiPctBadge = $('#roi-percentage-badge');
            if (roiPercentage < 50) {
                roiPctBadge.css('background-color', '#dc3545');
                roiPctBadge.css('color', '#fff');
            } else if (roiPercentage >= 50 && roiPercentage < 75) {
                roiPctBadge.css('background-color', '#ffc107');
                roiPctBadge.css('color', '#111');
            } else if (roiPercentage >= 75 && roiPercentage <= 125) {
                roiPctBadge.css('background-color', '#28a745');
                roiPctBadge.css('color', '#fff');
            } else {
                roiPctBadge.css('background-color', '#e83e8c');
                roiPctBadge.css('color', '#fff');
            }

            const pftBadge = $('#pft-total-badge');
            if (totalPft >= 0) {
                pftBadge.removeClass('bg-danger').addClass('bg-dark');
            } else {
                pftBadge.removeClass('bg-dark').addClass('bg-danger');
            }
        }

        table.on('dataLoaded', function() {
            updateSummary();
        });

        table.on('dataProcessed', function() {
            updateSummary();
        });

        table.on('renderComplete', function() {
            updateSummary();
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
        });

        $('#export-btn').on('click', function() {
            table.download("csv", "faire_daily_data.csv");
        });

        $('#startUploadBtn').on('click', function() {
            const fileInput = document.getElementById('dailyDataFile');
            const file = fileInput.files[0];

            if (!file) {
                showToast('Please select a file to upload', 'error');
                return;
            }

            const validTypes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv'
            ];
            if (!validTypes.includes(file.type)) {
                showToast('Please select a valid Excel or CSV file', 'error');
                return;
            }

            $('#uploadProgressContainer').show();
            $('#uploadResult').hide();
            $('#startUploadBtn').prop('disabled', true);

            const totalChunks = 1;
            const uploadId = 'faire_' + Date.now();
            let currentChunk = 0;
            let totalImported = 0;

            function uploadChunk() {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('chunk', currentChunk);
                formData.append('totalChunks', totalChunks);
                formData.append('uploadId', uploadId);
                formData.append('_token', '{{ csrf_token() }}');

                $.ajax({
                    url: '/faire/upload-daily-data',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            totalImported += response.imported || 0;
                            const progress = Math.round(((currentChunk + 1) / totalChunks) * 100);

                            $('#uploadProgressBar')
                                .css('width', progress + '%')
                                .text(progress + '%');

                            $('#uploadStatus').text(
                                `Processing chunk ${currentChunk + 1} of ${totalChunks}... (${totalImported} records imported so far)`
                            );

                            if (currentChunk < totalChunks - 1) {
                                currentChunk++;
                                setTimeout(uploadChunk, 500);
                            } else {
                                $('#uploadProgressBar')
                                    .removeClass('progress-bar-animated')
                                    .addClass('bg-success');

                                $('#uploadResult')
                                    .removeClass('alert-danger')
                                    .addClass('alert-success')
                                    .html(`<i class="fa fa-check-circle me-2"></i>Upload completed successfully! ${totalImported} records imported.`)
                                    .show();

                                $('#startUploadBtn').prop('disabled', false);
                                showToast(`Upload completed! ${totalImported} records imported.`, 'success');

                                setTimeout(function() {
                                    $('#uploadDailyDataModal').modal('hide');
                                    resetUploadForm();
                                    table.setData('/faire/daily-data');
                                }, 1500);
                            }
                        } else {
                            throw new Error(response.message || 'Upload failed');
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'Upload failed. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        $('#uploadProgressBar')
                            .removeClass('progress-bar-animated')
                            .addClass('bg-danger');

                        $('#uploadResult')
                            .removeClass('alert-success')
                            .addClass('alert-danger')
                            .html(`<i class="fa fa-exclamation-circle me-2"></i>${errorMessage}`)
                            .show();

                        $('#startUploadBtn').prop('disabled', false);
                        showToast(errorMessage, 'error');
                    }
                });
            }

            uploadChunk();
        });

        $('#uploadDailyDataModal').on('hidden.bs.modal', function() {
            resetUploadForm();
        });

        function resetUploadForm() {
            $('#dailyDataFile').val('');
            $('#uploadProgressContainer').hide();
            $('#uploadResult').hide();
            $('#uploadProgressBar')
                .removeClass('bg-success bg-danger')
                .addClass('progress-bar-animated')
                .css('width', '0%')
                .text('0%');
            $('#uploadStatus').text('');
            $('#startUploadBtn').prop('disabled', false);
        }
    });
</script>
@endsection
