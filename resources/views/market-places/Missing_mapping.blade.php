@extends('layouts.vertical', ['title' => 'Missing Mapping', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .mm-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .mm-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 12px;
        }
        .mm-channel-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .mm-channel-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        #stat-missing-mapping.badge,
        .badge-mm-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Missing Mapping',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-danger badge-mm-stat" id="stat-missing-mapping" title="N Map total from each channel pricing / tabulator page" style="background-color:#a71d2a !important;">
                        Missing Mapping: <span id="total-missing-mapping">{{ number_format(\App\Support\Marketplace\MappingChannelCounts::totalNmap(true)) }}</span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="missing-mapping-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="missing-mapping-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table = null;

    function updateStats(rows, totalNmap) {
        if (totalNmap !== undefined && totalNmap !== null && !isNaN(Number(totalNmap))) {
            $('#total-missing-mapping').text(Number(totalNmap).toLocaleString('en-US'));
            return;
        }
        const total = (rows || []).reduce((sum, r) => sum + Number(r.missing_mapping || 0), 0);
        $('#total-missing-mapping').text(total.toLocaleString('en-US'));
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function mmLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    $(document).ready(function() {
        table = new Tabulator("#missing-mapping-table", {
            ajaxURL: "{{ route('map.issues.channels') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data, response && response.total_nmap);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "channel", dir: "asc" }],
            placeholder: "No channels found.",
            columns: [
                {
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="mm-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mmLogoSrc(logo);
                        return `<img src="${escapeHtml(src)}" alt="${escapeHtml(channel)}" class="mm-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: "Channel",
                    field: "channel",
                    minWidth: 260,
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const row = cell.getRow().getData() || {};
                        const url = (row.detail_url || '').trim();
                        if (!name) return '';
                        const safeName = escapeHtml(name);
                        let apiBadge = '';
                        if (String(row.channel_slug || '') === 'pls') {
                            const on = row.api_connected === true || row.api_connected === 1 || row.api_connected === '1';
                            const title = escapeHtml(row.api_label || (on ? 'PLS API connected' : 'PLS API not connected'));
                            apiBadge = on
                                ? ` <span class="badge bg-success" title="${title}" style="font-size:0.7rem;font-weight:600;">API connected</span>`
                                : ` <span class="badge bg-danger" title="${title}" style="font-size:0.7rem;font-weight:600;">API off</span>`;
                        }
                        if (!url) return safeName + apiBadge;
                        return `<a href="${escapeHtml(url)}" class="mm-channel-link" title="Open Missing Mapping ${safeName}">${safeName}</a>${apiBadge}`;
                    },
                },
                {
                    title: "Missing Mapping",
                    field: "missing_mapping",
                    width: 200,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "N Map count from Active Channel (listed + INV mismatch)",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const color = v === 0 ? '#198754' : '#dc3545';
                        return `<span style="color:${color};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: "sum",
                },
            ],
        });

        $('#missing-mapping-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return String(row.channel || '').toLowerCase().includes(q);
            });
        });
    });
</script>
@endsection
