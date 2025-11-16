@extends('layouts.vertical', ['title' => 'A-Z Claims'])

@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style type="text/css">
        table#campaigns-table td.text-end {
            background-color: transparent !important;
            /* Reset default */
        }

        table#campaigns-table td.text-end.green-bg {
            background-color: #23c548 !important;
            color: #ffffff !important;
        }

        table#campaigns-table td.text-end.pink-bg {
            background-color: #f20888 !important;
            color: #ffffff !important;
        }

        table#campaigns-table td.text-end.red-bg {
            background-color: #c71123 !important;
            color: #ffffff !important;
        }

        .green-bg {
            background-color: #d4edda !important;
            color: #155724 !important;
        }

        .pink-bg {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }

        .red-bg {
            background-color: #f5c6cb !important;
            color: #721c24 !important;
        }

        .editable .cell-value {
            display: inline-block;
            min-width: 100px;
            max-width: 150px;
            overflow-wrap: break-word;
        }

        .editable {
            position: relative;
        }

        .editable .cell-value {
            display: inline-block;
            width: 100%;
            padding-right: 60px;
            /* Space for Done button in sbid column */
        }

        .editable .cell-value:focus {
            outline: none;
        }

        .saving {
            background-color: #fff3cd;
            /* Light yellow for saving state */
        }

        .text-end {
            text-align: right;
            font-weight: bold;
        }

        .dataTables_wrapper td,
        .dataTables_wrapper th {
            border-right: 1px solid #ddd;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        table.dataTable {
            width: 100% !important;
        }

        .dataTables_wrapper .dataTables_scroll {
            overflow: auto;
        }
    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style type="text/css">
        /* Global font and table style */
        table.dataTable {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            color: #212529;
            border-collapse: collapse;
        }

        /* Header styling */
        table.dataTable thead th {
            background-color: #e3f2fd;
            /* Soft blue */
            color: #000;
            font-weight: 600;
            text-align: center;
            padding: 8px;
            border-bottom: 2px solid #90caf9;
            white-space: nowrap;
        }

        /* Body cell styling */
        table.dataTable tbody td {
            padding: 6px 8px;
            border: 1px solid #cfd8dc;
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Alternating row colors */
        table.dataTable tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Hover row effect */
        table.dataTable tbody tr:hover {
            background-color: #f1f1f1;
        }

        /* Editable cell */
        .cell-value {
            display: inline-block;
            width: 100%;
            padding: 4px 6px;
            min-height: 28px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: #ffffff;
            cursor: text;
        }

        /* Focus effect */
        .cell-value:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
        }

        /* Saving state */
        .saving {
            background-color: #fff3cd !important;
            border-color: #ffc107 !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="card shadow-lg border-0">
                    <div class="card-body">
                        <h3 class="card-title mb-3 text-success fw-semibold">
                            <i class="bi bi-table"></i> Campaigns Data
                        </h3>
                        <!--                 <div class="mb-3">
        <input type="text" id="campaignSearch" class="form-control" placeholder="Search campaigns...">
    </div> -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="date" id="startDate" class="form-control" placeholder="Start Date">
                            </div>
                            <div class="col-md-4">
                                <input type="date" id="endDate" class="form-control" placeholder="End Date">
                            </div>
                        </div>

                        <div style="max-height: 70vh;">
                            <table id="campaigns-table"
                                class="table table-hover nowrap align-middle table-bordered border-primary w-100">
                                <thead class="table-primary sticky-top">
                                    <tr>
                                        <th>PARENT</th>
                                        <th>Campaigns</th>
                                        <th>AD TYPE</th>
                                        <th>Note</th>
                                        <th>Status</th>
                                        <th>BGT</th>
                                        <th>7 UB%</th>
                                        <th>1 UB%</th>
                                        <th>L60 Clicks</th>
                                        <th>L30 Clicks</th>
                                        <th>L15 Clicks</th>
                                        <th>L7 Clicks</th>
                                        <th>L60 SPEND</th>
                                        <th>L30 SPEND</th>
                                        <th>L15 SPEND</th>
                                        <th>L7 SPEND</th>
                                        <th>L1 SPEND</th>
                                        <th>SALES L60</th>
                                        <th>SALES L30</th>
                                        <th>SALES L15</th>
                                        <th>SALES L7</th>
                                        <th>CPC L60</th>
                                        <th>CPC L30</th>
                                        <th>CPC L15</th>
                                        <th>CPC L7</th>
                                        <th>Orders L60</th>
                                        <th>Orders L30</th>
                                        <th>Orders L15</th>
                                        <th>Orders L7</th>
                                        <th>ACOS L60</th>
                                        <th>ACOS L30</th>
                                        <th>ACOS L15</th>
                                        <th>ACOS L7</th>
                                        <th>PFT</th>
                                        <th>TPFT</th>
                                        <th>sbid</th>
                                        <th>yes sbid</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- DataTables base -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- FixedColumns -->
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
    <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

    <script>
        var $j = jQuery.noConflict();

        $j(document).ready(function() {
            $('#startDate, #endDate').on('change', function() {
                table.draw();
            });
            if (typeof $j.fn.DataTable === 'undefined') {
                console.error('DataTable is not loaded. Check script imports.');
                return;
            }

            const table = $j('#campaigns-table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                scrollY: "400px",
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                // fixedColumns: {
                //       left: 4,
                // },
                searching: true,
                stateSave: true,
                ajax: {
                    url: '{{ route('campaigns.data') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: function(d) {
                        d.start_date = $('#startDate').val();
                        d.end_date = $('#endDate').val();
                    }
                },
                columns: [{
                        data: 'campaignName',
                        render: function(data, type, row) {
                            if (data && data.startsWith("PARENT ")) {
                                return data.replace("PARENT ", "");
                            }
                            return '';
                        },
                        defaultContent: ''
                    },
                    {
                        data: 'campaignName',
                        defaultContent: ''
                    },
                    {
                        data: 'ad_type',
                        defaultContent: ''
                    },
                    {
                        data: 'note',
                        defaultContent: '',
                        render: function(data, type, row) {
                            return `
                            <td class="editable">
                                <span class="cell-value"
                                      contenteditable="true"
                                      data-id="${row.id}"
                                      data-field="note"
                                      style="display: inline-block; min-width: 150px; max-width: 200px; overflow-wrap: break-word;">
                                    ${data || ''}
                                </span>
                            </td>`;
                        }
                    },
                    {
                        data: 'campaignStatus',
                        render: function(data, type, row) {
                            if (!data) {
                                return '';
                            }
                            if (data === 'ENABLED') {
                                return '<span class="badge bg-success">ENABLED</span>';
                            } else if (data === 'PAUSED') {
                                return '<span class="badge bg-warning text-dark">PAUSED</span>';
                            } else {
                                return '<span class="badge bg-secondary">' + data + '</span>';
                            }
                        }
                    },
                    {
                        data: 'campaignBudgetAmount',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            if (data == null || isNaN(data)) return 0;
                            return parseInt(data);
                        }
                    },
                    {
                        data: null,
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                const l7_spend = parseFloat(row.l7_spend) || 0;
                                const budget = parseFloat(row.campaignBudgetAmount) || 0;
                                if (budget === 0 || l7_spend === 0) return '0%';
                                const ub7 = (l7_spend / (budget * 7)) * 100;
                                return ub7.toFixed(0) + '%';
                            }
                            return data;
                        },
                        createdCell: function(td, cellData, rowData, row, col) {
                            console.log('createdCell triggered for row:', row);
                            const l7_spend = parseFloat(rowData.l7_spend) || 0;
                            const budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                            console.log('l7_spend:', l7_spend, 'budget:', budget);
                            if (budget === 0 || l7_spend === 0) {
                                $j(td).addClass('red-bg');
                                console.log('Applying red-bg for 0%');
                                return;
                            }
                            const ub7 = (l7_spend / (budget * 7)) * 100;
                            console.log('Calculated UB7:', ub7);
                            $j(td).removeClass('green-bg pink-bg red-bg');
                            if (ub7 >= 70 && ub7 <= 90) {
                                console.log('Applying green-bg for:', ub7);
                                $j(td).addClass('green-bg');
                            } else if (ub7 > 90) {
                                console.log('Applying pink-bg for:', ub7);
                                $j(td).addClass('pink-bg');
                            } else if (ub7 < 70) {
                                console.log('Applying red-bg for:', ub7);
                                $j(td).addClass('red-bg');
                            }
                        }
                    },
                    {
                        data: null,
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            if (type === 'display') {
                                const l1_spend = parseFloat(row.l1_spend) || 0;
                                const budget = parseFloat(row.campaignBudgetAmount) || 0;
                                if (budget === 0 || l1_spend === 0) return '0%';
                                const ub1 = (l1_spend / budget) * 100;
                                return ub1.toFixed(0) + '%';
                            }
                            return data;
                        },
                        createdCell: function(td, cellData, rowData, row, col) {
                            const l1_spend = parseFloat(rowData.l1_spend) || 0;
                            const budget = parseFloat(rowData.campaignBudgetAmount) || 0;
                            if (budget === 0 || l1_spend === 0) {
                                $j(td).addClass('red-bg');
                                return;
                            }
                            const ub1 = (l1_spend / budget) * 100;
                            if (ub1 >= 70 && ub1 <= 90) {
                                $j(td).addClass('green-bg');
                            } else if (ub1 > 90) {
                                $j(td).addClass('pink-bg');
                            } else if (ub1 < 70) {
                                $j(td).addClass('red-bg');
                            }
                        }
                    },
                    {
                        data: 'l60_clicks',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l30_clicks',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l15_clicks',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l7_clicks',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l60_spend',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l30_spend',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l15_spend',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l7_spend',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l1_spend',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l60_sales',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : '';
                        }
                    },
                    {
                        data: 'l30_sales',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l15_sales',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l7_sales',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l60_cpc',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l30_cpc',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l15_cpc',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l7_cpc',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l60_orders',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l30_orders',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) : 0;
                        }
                    },
                    {
                        data: 'l15_orders',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: 'l7_orders',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: 'l60_acos',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: 'l30_acos',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: 'l15_acos',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: 'l7_acos',
                        defaultContent: '',
                        className: 'text-end',
                        render: function(data, type, row) {
                            return data != null ? parseInt(data) + '%' : '0%';
                        }
                    },
                    {
                        data: null,
                        defaultContent: ''
                    },
                    {
                        data: null,
                        defaultContent: ''
                    },
                    {
                        data: 'sbid',
                        defaultContent: '',
                        render: function(data, type, row) {
                            return `
                            <div class="editable-cell-wrapper d-flex align-items-center justify-content-between" style="gap: 4px;">
                                <span class="cell-value form-control p-1" 
                                      contenteditable="true" 
                                      data-id="${row.id}" 
                                      data-field="sbid"
                                      style="flex: 1; min-width: 100px;">
                                    ${data || ''}
                                </span>
                                <button type="button" class="clear-btn btn btn-sm btn-outline-danger">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        `;
                        }
                    },
                    {
                        data: 'yes_sbid',
                        defaultContent: '',
                        render: function(data, type, row) {
                            return `
                            <td class="editable">
                                <span class="cell-value"
                                      contenteditable="true"
                                      data-id="${row.id}"
                                      data-field="yes_sbid"
                                      style="display: inline-block; min-width: 100px; max-width: 150px; overflow-wrap: break-word;">
                                    ${data || ''}
                                </span>
                            </td>`;
                        }
                    }

                ]
            });

            $j('#campaignSearch').on('keyup', function() {
                table.search($j(this).val()).draw();
            });

            $j(document).on('focus', '.cell-value', function() {
                $j(this).data('original', $j(this).text().trim());
                $j(this).removeClass('saving');
            });

            $j(document).on('blur', '.cell-value', function() {
                const $cell = $j(this);
                const originalValue = $cell.data('original') || '';
                saveCellData($cell, originalValue);
            });

            $j(document).on('keydown', '.cell-value', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $j(this).blur();
                }
            });

            $j(document).on('click', '.clear-btn', function(e) {
                e.preventDefault();
                const $cell = $j(this).siblings('.cell-value');
                const id = $cell.data('id');
                const sbidValue = $cell.text().trim();

                if (!sbidValue) return;

                $cell.text('');
                const $row = $j(this).closest('tr');
                const $yesSbidCell = $row.find('span[data-field="yes_sbid"]');
                $yesSbidCell.text(sbidValue);

                const yesSbidPromise = $j.ajax({
                    url: '{{ route('campaigns.update-note') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $j('meta[name="csrf-token"]').attr('content')
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        id: id,
                        field: 'yes_sbid',
                        value: sbidValue
                    })
                });

                const sbidPromise = $j.ajax({
                    url: '{{ route('campaigns.update-note') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $j('meta[name="csrf-token"]').attr('content')
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        id: id,
                        field: 'sbid',
                        value: ''
                    })
                });

                Promise.all([yesSbidPromise, sbidPromise])
                    .then(([yesSbidRes, sbidRes]) => {
                        console.log('sbid moved to yes_sbid:', yesSbidRes);
                        console.log('sbid cleared:', sbidRes);
                        // table.ajax.reload(null, false); 
                    })
                    .catch(err => {
                        console.error('Error updating fields:', err);
                        $yesSbidCell.text('');
                        $cell.text(sbidValue);
                    });
            });

            function saveCellData($cell, originalValue) {
                const id = $cell.data('id');
                const field = $cell.data('field');
                const value = $cell.text().trim();

                if (!value || value === originalValue) return;

                $cell.addClass('saving');

                console.log(`Saving: ID=${id}, Field=${field}, Value=${value}, Original=${originalValue}`);

                $j.ajax({
                    url: '{{ route('campaigns.update-note') }}',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $j('meta[name="csrf-token"]').attr('content')
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        id: id,
                        field: field,
                        value: value
                    }),
                    success: function(res) {
                        console.log('✅ Saved:', res);
                        $cell.text(value);
                        // table.ajax.reload(null, false); 
                        $cell.removeClass('saving');
                    },
                    error: function(err) {
                        console.error('❌ Error saving:', err);
                        $cell.text(originalValue);
                        $cell.removeClass('saving');
                    }
                });
            }
        });
    </script>
@endsection
