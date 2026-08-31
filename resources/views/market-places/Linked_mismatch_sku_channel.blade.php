@extends('layouts.vertical', ['title' => 'Linked mismatch SKU ' . $channelName, 'sidenav' => 'condensed'])

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
        'page_title' => 'Linked mismatch SKU ' . $channelName,
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="{{ url('/linked-mismatch-sku') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Linked mismatch SKU
                    </a>
                    <span class="badge bg-danger badge-mmc-stat">
                        Linked mismatch SKU: <span id="lmc-total-count">0</span>
                    </span>
                    <span class="text-muted small">{{ $channelName }} — same as Marketplace Manager Linked mismatch SKU.</span>
                    @if (!empty($listingsUrl))
                        <a href="{{ $listingsUrl }}" class="btn btn-sm btn-outline-primary">Open listings</a>
                    @endif
                    @if (!empty($plsApi))
                        @if (!empty($plsApi['connected']))
                            <span class="badge bg-success" title="{{ $plsApi['message'] ?? '' }}">API connected{{ !empty($plsApi['shop']) ? ' — '.$plsApi['shop'] : '' }}</span>
                        @else
                            <span class="badge bg-danger" title="{{ $plsApi['message'] ?? '' }}">API off</span>
                        @endif
                    @endif
                    @if (!empty($hasSkuDetail))
                        <button type="button" id="lmc-export-btn" class="btn btn-sm btn-success ms-auto" title="Export CSV">
                            <i class="fas fa-file-excel me-1"></i> Export
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="lmc-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                </div>
                <div id="lmc-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let lmcTable = null;
    const hasSkuDetail = @json((bool) $hasSkuDetail);

    $(document).ready(function() {
        if (!hasSkuDetail) {
            $('#lmc-table').html(
                '<div class="p-4 text-center text-muted">SKU-level Linked mismatch SKU is not available for this channel yet.</div>'
            );
            return;
        }

        lmcTable = new Tabulator("#lmc-table", {
            ajaxURL: "{{ url('/linked-mismatch-sku/channel/' . $channelSlug . '/data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                $('#lmc-total-count').text(Number(response && response.count != null ? response.count : data.length).toLocaleString('en-US'));
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "diff", dir: "desc" }],
            placeholder: "No linked mismatch SKUs.",
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
                    headerTooltip: "Shopify quantity",
                    formatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: @json($channelInvLabel ?? 'Channel Inv'),
                    field: "channel_inv",
                    width: 150,
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
                        const color = v === 0 ? '#198754' : '#dc3545';
                        return `<span style="color:${color};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    },
                },
            ],
        });

        $('#lmc-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                lmcTable.clearFilter(true);
                return;
            }
            lmcTable.setFilter(function(row) {
                return String(row.sku || '').toLowerCase().includes(q)
                    || String(row.channel_sku || '').toLowerCase().includes(q);
            });
        });

        $('#lmc-export-btn').on('click', function() {
            if (!lmcTable) return;
            const slug = @json($channelSlug);
            const stamp = new Date().toISOString().slice(0, 10);
            lmcTable.download("csv", `linked_mismatch_sku_${slug}_${stamp}.csv`, {
                bom: true,
            });
        });
    });
</script>
@endsection
