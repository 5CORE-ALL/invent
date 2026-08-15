@extends('layouts.vertical', ['title' => 'Missing Mapping ' . $channelName, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .badge-mmc-stat {
            font-size: 1.2rem !important;
            line-height: 1.35;
            padding: 0.65rem 1.1rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Missing Mapping ' . $channelName,
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="{{ route('map.issues') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Missing Mapping
                    </a>
                    <span class="badge bg-danger badge-mmc-stat" style="background-color:#a71d2a !important;">
                        Missing Mapping: <span id="mmc-total-count">0</span>
                    </span>
                    <span class="text-muted small">{{ $channelName }} — SKUs where INV does not match channel stock (N Map). Both sides must have stock.</span>
                    @if (!empty($apiStatus))
                        @if (!empty($apiStatus['connected']))
                            <span class="badge bg-success" title="{{ $apiStatus['message'] ?? '' }}">API connected{{ !empty($apiStatus['shop']) ? ' — '.$apiStatus['shop'] : '' }}</span>
                        @else
                            <span class="badge bg-danger" title="{{ $apiStatus['message'] ?? '' }}">API off</span>
                        @endif
                    @endif
                    @if (!empty($hasSkuDetail))
                        <button type="button" id="mmc-export-btn" class="btn btn-sm btn-success ms-auto" title="Export CSV">
                            <i class="fas fa-file-excel me-1"></i> Export
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="mmc-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                </div>
                <div id="mmc-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let mmcTable = null;
    const hasSkuDetail = @json((bool) $hasSkuDetail);

    $(document).ready(function() {
        if (!hasSkuDetail) {
            $('#mmc-table').html(
                '<div class="p-4 text-center text-muted">SKU-level Missing Mapping is not available for this channel yet.</div>'
            );
            return;
        }

        mmcTable = new Tabulator("#mmc-table", {
            ajaxURL: "{{ route('map.issues.channel.data', ['channel' => $channelSlug]) }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                $('#mmc-total-count').text(Number(response && response.count != null ? response.count : data.length).toLocaleString('en-US'));
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "diff", dir: "desc" }],
            placeholder: "No SKUs with Missing Mapping.",
            columns: [
                {
                    title: "SKU",
                    field: "sku",
                    minWidth: 180,
                    headerFilter: "input",
                },
                {
                    title: "Channel SKU",
                    field: "channel_sku",
                    minWidth: 160,
                },
                {
                    title: "INV",
                    field: "inv",
                    width: 110,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: @json($channelInvLabel ?? 'Channel Inv'),
                    field: "channel_inv",
                    width: 130,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "Diff",
                    field: "diff",
                    width: 110,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#dc3545;font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    },
                },
            ],
        });

        $('#mmc-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                mmcTable.clearFilter(true);
                return;
            }
            mmcTable.setFilter(function(row) {
                return String(row.sku || '').toLowerCase().includes(q)
                    || String(row.channel_sku || '').toLowerCase().includes(q);
            });
        });

        $('#mmc-export-btn').on('click', function() {
            if (!mmcTable) return;
            const slug = @json($channelSlug);
            const stamp = new Date().toISOString().slice(0, 10);
            mmcTable.download("csv", `missing_mapping_${slug}_${stamp}.csv`, {
                bom: true,
            });
        });
    });
</script>
@endsection
