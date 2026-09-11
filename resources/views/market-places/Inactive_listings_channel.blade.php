@extends('layouts.vertical', ['title' => (!empty($cpOnly) ? 'Inactive Listing ' : 'Marketplace Inactive Listings ') . $channelName, 'sidenav' => 'condensed'])

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
        .sku-cell {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .copy-sku-btn {
            border: 0;
            background: transparent;
            color: #6c757d;
            padding: 0;
            cursor: pointer;
        }
        .copy-sku-btn:hover { color: #0d6efd; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => (!empty($cpOnly) ? 'Inactive Listing ' : 'Marketplace Inactive Listings ') . $channelName,
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <a href="{{ url('/inactive-listings') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Inactive Listings
                    </a>
                    <span class="badge bg-warning text-dark badge-mmc-stat" title="{{ !empty($cpOnly) ? 'CP Master child SKUs inactive on this marketplace (0 Inv excluded)' : 'Inactive child SKUs only' }}">
                        Inactive Child SKUs: <span id="ilc-child-count">0</span>
                    </span>
                    <span class="badge bg-secondary badge-mmc-stat" title="Inactive parent listings">
                        Parent: <span id="ilc-parent-count">0</span>
                    </span>
                    <span class="badge bg-dark badge-mmc-stat" title="All inactive rows on this page">
                        Rows: <span id="ilc-row-count">0</span>
                    </span>
                    <span class="text-muted small">{{ $channelName }} — {{ !empty($cpOnly) ? 'in CP Master and this marketplace, inactive, inventory not zero.' : 'all marketplace inactive listings and status.' }}</span>
                    @if (!empty($listingsUrl))
                        <a href="{{ $listingsUrl }}" class="btn btn-sm btn-outline-primary">Open marketplace listings</a>
                    @endif
                    @if (!empty($plsApi))
                        @if (!empty($plsApi['connected']))
                            <span class="badge bg-success" title="{{ $plsApi['message'] ?? '' }}">API connected{{ !empty($plsApi['shop']) ? ' — '.$plsApi['shop'] : '' }}</span>
                        @else
                            <span class="badge bg-danger" title="{{ $plsApi['message'] ?? '' }}">API off</span>
                        @endif
                    @endif
                    <button type="button" id="ilc-export-btn" class="btn btn-sm btn-success ms-auto" title="Export CSV">
                        <i class="fas fa-file-excel me-1"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="ilc-search" class="form-control form-control-sm" placeholder="Search parent, SKU, or status...">
                </div>
                <div id="ilc-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let ilcTable = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function copySku(btn) {
        const sku = btn.getAttribute('data-sku') || '';
        if (!sku || !navigator.clipboard) return;
        navigator.clipboard.writeText(sku).then(function () {
            btn.classList.add('is-copied');
            setTimeout(function () { btn.classList.remove('is-copied'); }, 800);
        });
    }

    $(document).ready(function() {
        ilcTable = new Tabulator("#ilc-table", {
            ajaxURL: "{{ url('/inactive-listings/channel/' . $channelSlug . '/data') }}{{ !empty($cpOnly) ? '?source=cp' : '' }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                const child = Number(response && response.child_count != null ? response.child_count : data.filter(function (r) { return String(r.kind || '') !== 'parent'; }).length);
                const parent = Number(response && response.parent_count != null ? response.parent_count : data.filter(function (r) { return String(r.kind || '') === 'parent'; }).length);
                $('#ilc-child-count').text(child.toLocaleString('en-US'));
                $('#ilc-parent-count').text(parent.toLocaleString('en-US'));
                $('#ilc-row-count').text(data.length.toLocaleString('en-US'));
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "parent", dir: "asc" }, { column: "sku", dir: "asc" }],
            placeholder: "No inactive listings for this marketplace.",
            columns: [
                {
                    title: "Parent",
                    field: "parent",
                    minWidth: 180,
                    headerFilter: "input",
                    headerTooltip: "Parent SKU from Product Master",
                },
                {
                    title: "Sku",
                    field: "sku",
                    minWidth: 200,
                    headerFilter: "input",
                    headerTooltip: "Child / variation SKU (or parent placeholder)",
                    formatter: function(cell) {
                        const sku = String(cell.getValue() || '').trim();
                        if (!sku) return '';
                        const safe = escapeHtml(sku);
                        return `<span class="sku-cell"><span>${safe}</span><button type="button" class="copy-sku-btn" data-sku="${safe}" title="Copy SKU" onclick="copySku(this)"><i class="fas fa-copy"></i></button></span>`;
                    },
                },
                {
                    title: "INV",
                    field: "inv",
                    width: 110,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Shopify inventory",
                    formatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "Inactive",
                    field: "status",
                    width: 160,
                    hozAlign: "center",
                    headerFilter: "list",
                    headerFilterParams: { values: true, clearable: true },
                    headerTooltip: "Seller-platform listing status (inactive)",
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const v = String(cell.getValue() || row.state || 'Inactive');
                        const lower = v.toLowerCase();
                        let cls = 'bg-dark';
                        if (lower === 'pending') cls = 'bg-warning text-dark';
                        else if (lower.indexOf('mismatch') !== -1) cls = 'bg-danger';
                        else if (lower === 'inactive' || lower === '') cls = 'bg-dark';
                        return `<span class="badge ${cls}">${escapeHtml(v || 'Inactive')}</span>`;
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
                    title: "Type",
                    field: "kind",
                    width: 110,
                    hozAlign: "center",
                    headerFilter: "list",
                    headerFilterParams: { values: { parent: "Parent", child: "Child" }, clearable: true },
                    formatter: function(cell) {
                        const v = String(cell.getValue() || 'child');
                        if (v === 'parent') {
                            return '<span class="badge bg-dark">Parent</span>';
                        }
                        return '<span class="badge bg-primary">Child</span>';
                    },
                },
            ],
        });

        $('#ilc-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                ilcTable.clearFilter(true);
                return;
            }
            ilcTable.setFilter(function(row) {
                return String(row.parent || '').toLowerCase().includes(q)
                    || String(row.sku || '').toLowerCase().includes(q)
                    || String(row.channel_sku || '').toLowerCase().includes(q)
                    || String(row.status || '').toLowerCase().includes(q)
                    || String(row.state || '').toLowerCase().includes(q)
                    || String(row.kind || '').toLowerCase().includes(q);
            });
        });

        $('#ilc-export-btn').on('click', function() {
            if (!ilcTable) return;
            const slug = @json($channelSlug);
            const stamp = new Date().toISOString().slice(0, 10);
            ilcTable.download("csv", `inactive_listings_${slug}_${stamp}.csv`, {
                bom: true,
            });
        });
    });
</script>
@endsection
