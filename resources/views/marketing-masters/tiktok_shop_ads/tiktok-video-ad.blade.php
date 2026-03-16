@extends('layouts.vertical', ['title' => 'Tiktok - VIDEO AD', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }
        .status-active {
            background-color: #28a745 !important;    /* green */
            color: #fff !important;
            font-weight: 600;
            border: none;
            border-radius: 4px;
        }

        .status-inactive {
            background-color: #dc3545 !important;   /* red */
            color: #fff !important;
            font-weight: 600;
            border: none;
            border-radius: 4px;
        }

        /* Dropdown options colors */
        .status-active-option {
            background-color: #28a745 !important;
            color: white;
        }

        .status-inactive-option {
            background-color: #dc3545 !important;
            color: white;
        }

        /* This ensures the background shows properly inside Tabulator */
        .tabulator-cell select {
            width: 100%;
            padding: 3px 5px;
        }
        .kpi-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: 0.2s ease-in-out;
            color: #000;
        }

        .kpi-blue {
            background-color: #DCEBFF !important;
        }

        .kpi-green {
            background-color: #DFF5E3 !important;
        }

        .kpi-yellow {
            background-color: #FFF1CC !important;
        }

        .kpi-red {
            background-color: #FFE0E0 !important;
        }

        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        
        .kpi-title {
            font-size: 14px;
            font-weight: 700;
            color: #000 !important;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 22px;
            font-weight: 800;
            color: #000;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Tiktok VIDEO AD',
        'sub_title' => 'Tiktok VIDEO AD',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <div class="row mb-2">
                            <div class="col-12 text-end">
                                @if($latestUpdatedAt)
                                    <span style="font-weight: bold; font-style: italic; font-size: 15px;">
                                        Last Updated At: {{ $latestUpdatedAt }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search SKU...">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" id="import-btn">Import</button>
                                    <button type="button" class="btn btn-sm btn-success" id="export-btn">Export</button>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-12">
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Import Editable Fields</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">

                                <a href="{{ asset('sample_excel/sample_gmv_ads.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

                                <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv" class="form-control" />
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function showNotification(type, message) {
                const notification = $(`
                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                `);

                $('body').append(notification);

                setTimeout(() => {
                    notification.find('.alert').alert('close');
                }, 3000);
            }

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/tiktok-video-ad-analytics-data",
                layout: "fitDataFill",
                pagination: "local",
                paginationSize: 25,
                paginationSizeSelector: [25, 50, 100, 200],
                movableColumns: true,
                resizableColumns: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["Sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                    data-sku="${sku}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: false
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
                        formatter: function(cell) {
                            const data = cell.getData();
                            const l30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (l30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            const combinedFilter = function(data) {
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.sku?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                return true;
            }

            table.setFilter(combinedFilter);

            $("#global-search").on("keyup", function() {
                table.setFilter(combinedFilter);
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let colsToToggle = ["INV", "L30", "DIL %"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.body.style.zoom = "78%";

            $('#import-btn').on('click', function () {
                $('#importModal').modal('show');
            });

            $('#export-btn').on('click', function () {
                const data = table.getData();
                if (data.length === 0) {
                    showNotification('warning', 'No data available to export');
                    return;
                }

                // Create CSV content
                const headers = ['Parent', 'SKU', 'INV', 'OV L30', 'DIL %'];
                let csvContent = headers.join(',') + '\n';

                data.forEach(row => {
                    const l30 = parseFloat(row.L30) || 0;
                    const inv = parseFloat(row.INV) || 0;
                    const dilPercent = inv > 0 ? Math.round((l30 / inv) * 100) : 0;

                    const rowData = [
                        row.parent || '',
                        row.sku || '',
                        row.INV || 0,
                        row.L30 || 0,
                        dilPercent + '%'
                    ];
                    csvContent += rowData.join(',') + '\n';
                });

                // Create download link
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'tiktok_video_ad_' + new Date().getTime() + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showNotification('success', 'Data exported successfully');
            });

            $(document).on('click', '#confirmImportBtn', function () {
                let file = $('#importFile')[0].files[0];
                if (!file) {
                    alert('Please select a file to import.');
                    return;
                }

                let formData = new FormData();
                formData.append('file', file);

                $.ajax({
                    url: "{{ route('tiktok.import') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (response) {
                        $('#importModal').modal('hide');
                        $('#importFile').val('');
                        showNotification('success', 'Import successful! Processed: ' + response.processed);
                        location.reload();
                    },
                    error: function (xhr) {
                        let message = 'Import failed';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.error) {
                                message = xhr.responseJSON.error;
                            }

                            else if (xhr.responseJSON.errors && Array.isArray(xhr.responseJSON.errors)) {
                                message = xhr.responseJSON.errors.join('<br>');
                            }
                        }

                        showNotification('danger', message);
                    }
                });
            });
        });
    </script>
@endsection
