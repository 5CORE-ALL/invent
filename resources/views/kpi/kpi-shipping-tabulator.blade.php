@extends('layouts.vertical', ['title' => 'Kpi Shipping', 'sidenav' => 'condensed'])

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
        'page_title' => 'Label Created & Uploaded On time.',
        'sub_title' => 'Shipping KPI overview.',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Label Created & Uploaded On time.</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>
                    <span class="badge bg-primary fs-6 p-2" id="avg-pct-badge" style="color: #fff; font-weight: bold;">Avg %: 0%</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="kpi-shipping-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="global-search" class="form-control form-control-sm" placeholder="Search..." style="max-width: 220px;">
                    </div>
                    <div id="kpi-shipping-table" style="flex: 1;"></div>
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

        table = new Tabulator("#kpi-shipping-table", {
            ajaxURL: "{{ route('kpi.shipping.tabulator.data') }}",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            placeholder: "No data available",
            ajaxResponse: function(url, params, response) {
                return Array.isArray(response) ? response : (response.data || []);
            },
            columns: [
                { title: "Channel", field: "channel", width: 220 },
                {
                    title: "Label Created & Uploaded On Time %",
                    field: "on_time_pct",
                    width: 260,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const color = value >= 95 ? '#28a745' : (value >= 80 ? '#ffc107' : '#dc3545');
                        return `<span style="color: ${color}; font-weight: bold;">${value.toFixed(2)}%</span>`;
                    }
                },
            ]
        });

        function updateAvgBadge() {
            const data = table.getData("active");
            let sum = 0;
            let count = 0;
            data.forEach(function(row) {
                const value = parseFloat(row.on_time_pct);
                if (!isNaN(value)) {
                    sum += value;
                    count++;
                }
            });
            const avg = count > 0 ? sum / count : 0;
            $('#avg-pct-badge').text('Avg %: ' + avg.toFixed(2) + '%');
        }

        table.on('dataLoaded', updateAvgBadge);
        table.on('dataProcessed', updateAvgBadge);
        table.on('dataFiltered', updateAvgBadge);

        $('#global-search').on('keyup', function() {
            const value = $(this).val() || '';
            table.setFilter('channel', 'like', value);
        });

        $('#export-btn').on('click', function() {
            table.download("csv", "kpi_shipping.csv");
        });
    });
</script>
@endsection
