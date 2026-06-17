@extends('layouts.vertical', ['title' => 'CC Return Audit', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .audit-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 12px;
        }

        .audit-toolbar .form-control,
        .audit-toolbar .form-select {
            max-width: 240px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 40px !important;
        }

        .tabulator-cell {
            padding: 5px 8px !important;
            white-space: normal !important;
        }

        .channel-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #fff3cd;
            color: #997404;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-arrow-go-back-line me-2 text-primary"></i>CC Return Audit
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Audit Master</a></li>
                        <li class="breadcrumb-item active">CC Return Audit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="audit-toolbar">
                        <input type="text" id="searchReturns" class="form-control form-control-sm"
                            placeholder="Search returns...">
                        <select id="channelFilter" class="form-select form-select-sm">
                            <option value="">All Channels</option>
                            @foreach ($channels as $channel)
                                <option value="{{ $channel }}">{{ $channel }}</option>
                            @endforeach
                        </select>
                        <select id="reasonFilter" class="form-select form-select-sm">
                            <option value="">All Reasons</option>
                            <option value="defective">Defective</option>
                            <option value="not_as_described">Not As Described</option>
                            <option value="changed_mind">Changed Mind</option>
                            <option value="damaged">Damaged in Transit</option>
                        </select>
                        <input type="date" id="startDate" class="form-control form-control-sm">
                        <input type="date" id="endDate" class="form-control form-control-sm">
                        <button type="button" id="refreshBtn" class="btn btn-sm btn-primary">
                            <i class="ri-refresh-line me-1"></i> Refresh
                        </button>
                        <button type="button" id="exportBtn" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-download-2-line me-1"></i> Export
                        </button>
                    </div>

                    <div id="ccReturnAuditTable"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            const channels = @json($channels);

            const tableData = channels.map((channel, index) => ({
                id: index + 1,
                channel: channel,
                returns: 0,
                last_audited: '-',
            }));

            const table = new Tabulator('#ccReturnAuditTable', {
                data: tableData,
                layout: 'fitColumns',
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No channels found in Channel Master.',
                columns: [
                    { title: '#', field: 'id', width: 60, hozAlign: 'center' },
                    {
                        title: 'Channels',
                        field: 'channel',
                        headerFilter: 'input',
                        formatter: cell => `<span class="channel-pill">${cell.getValue() ?? ''}</span>`,
                    },
                    { title: 'Returns', field: 'returns', width: 120, hozAlign: 'center' },
                    { title: 'Last Audited', field: 'last_audited', width: 160 },
                ],
            });

            $('#channelFilter').on('change', function () {
                const value = $(this).val();
                if (value) {
                    table.setFilter('channel', '=', value);
                } else {
                    table.clearFilter();
                }
            });

            $('#searchReturns').on('input', function () {
                const value = $(this).val();
                if (value) {
                    table.setFilter('channel', 'like', value);
                } else {
                    table.clearFilter();
                }
            });

            $('#refreshBtn').on('click', function () {
                window.location.reload();
            });

            $('#exportBtn').on('click', function () {
                table.download('csv', 'cc-return-audit.csv');
            });
        });
    </script>
@endsection
