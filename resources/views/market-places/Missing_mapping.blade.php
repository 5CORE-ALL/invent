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
        .mm-listings-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            text-decoration: none;
            line-height: 1;
        }
        .mm-listings-arrow-on {
            color: #0d6efd;
        }
        .mm-listings-arrow-on:hover {
            color: #0a58ca;
        }
        .mm-listings-arrow-off {
            color: #dc3545;
            cursor: default;
        }
        .mm-api-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.06);
        }
        .mm-api-dot-green { background: #198754; }
        .mm-api-dot-yellow { background: #ffc107; }
        .mm-api-dot-red { background: #dc3545; }
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
                    <span class="badge bg-danger badge-mm-stat" id="stat-missing-mapping" title="Sum of Missing Mapping Titas (Active SKU Mismatch from listings)" style="background-color:#a71d2a !important;">
                        Missing Mapping: <span id="total-missing-mapping">{{ number_format(\App\Support\Marketplace\MappingChannelCounts::totalTitas(true)) }}</span>
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

    function updateStats(rows, totalTitas) {
        if (totalTitas !== undefined && totalTitas !== null && !isNaN(Number(totalTitas))) {
            $('#total-missing-mapping').text(Number(totalTitas).toLocaleString('en-US'));
            return;
        }
        const total = (rows || []).reduce((sum, r) => sum + Number(r.missing_mapping_titas || 0), 0);
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
                updateStats(data, response && response.total_titas);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "missing_mapping_titas", dir: "desc" }],
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
                        if (!name) return '';
                        return escapeHtml(name);
                    },
                },
                {
                    title: "API",
                    field: "api_status",
                    width: 80,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerTooltip: "Green: API linked and inventory synced in the last 24h. Yellow: linked but last sync is older than 24h. Red: not linked.",
                    sorter: function(a, b) {
                        const rank = { green: 0, yellow: 1, red: 2 };
                        return (rank[a] ?? 9) - (rank[b] ?? 9);
                    },
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const status = String(row.api_status || 'red').toLowerCase();
                        const cls = status === 'green' ? 'mm-api-dot-green'
                            : (status === 'yellow' ? 'mm-api-dot-yellow' : 'mm-api-dot-red');
                        const title = escapeHtml(row.api_label || status);
                        return `<span class="mm-api-dot ${cls}" title="${title}"></span>`;
                    },
                },
                {
                    title: "Link",
                    field: "listings_url",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    formatter: function(cell) {
                        const url = (cell.getValue() || '').trim();
                        const name = (cell.getRow().getData().channel || 'channel').trim();
                        if (!url) {
                            return '<span class="mm-listings-arrow mm-listings-arrow-off" title="Listings link not available"><i class="fas fa-arrow-up-right-from-square"></i></span>';
                        }
                        return `<a href="${escapeHtml(url)}" class="mm-listings-arrow mm-listings-arrow-on" title="Open ${escapeHtml(name)} listings" target="_self"><i class="fas fa-arrow-up-right-from-square"></i></a>`;
                    },
                },
                {
                    title: "Missing Mapping Titas",
                    field: "missing_mapping_titas",
                    width: 230,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Active SKU Mismatch from Marketplace Manager listings (Link column)",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const url = (cell.getRow().getData().listings_url || '').trim();
                        const color = v === 0 ? '#198754' : '#dc3545';
                        const html = `<span style="color:${color};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                        if (!url) return html;
                        return `<a href="${escapeHtml(url)}" style="text-decoration:none;">${html}</a>`;
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
