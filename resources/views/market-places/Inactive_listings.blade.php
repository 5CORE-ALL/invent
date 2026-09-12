@extends('layouts.vertical', ['title' => 'Inactive Listings', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .il-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .il-channel-logo-placeholder {
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
        .il-channel-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .il-channel-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        .il-inactive-count {
            font-weight: 700;
            text-decoration: none;
        }
        .il-listings-arrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            text-decoration: none;
            line-height: 1;
        }
        .il-listings-arrow-on { color: #0d6efd; }
        .il-listings-arrow-on:hover { color: #0a58ca; }
        .il-listings-arrow-off { color: #dc3545; cursor: default; }
        .il-api-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.06);
        }
        .il-api-dot-green { background: #198754; }
        .il-api-dot-yellow { background: #ffc107; }
        .il-api-dot-red { background: #dc3545; }
        #stat-cp-inactive-listings.badge,
        .badge-il-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group {
            background: #eef4fb;
            text-align: center;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-col-group .tabulator-col-title {
            font-weight: 700;
            color: #6c2c2c;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Inactive Listings',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-warning text-dark badge-il-stat" id="stat-cp-inactive-listings" title="CP Master SKUs that are also inactive on the marketplace. Zero-inventory SKUs are excluded.">
                        Inactive Child SKUs: <span id="total-cp-inactive-listings">{{ number_format(\App\Support\Marketplace\MappingChannelCounts::cachedCpInactiveTotalOrZero()) }}</span>
                    </span>
                    <span class="text-muted small">Inactive Listing = listed on the marketplace but not live there, and Active with stock in CP Master. Zero does not mean every CP Master SKU is live on every channel — missing / never-listed SKUs are on Missing Listing.</span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="inactive-listings-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="inactive-listings-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    let table = null;

    function updateSidebarInactiveCount(n) {
        const $b = $('.inactive-listings-badge');
        if (!$b.length) return;
        const v = Number(n || 0);
        $b.text(v.toLocaleString('en-US'));
        if (v > 0) {
            $b.css('display', 'inline-block');
        } else {
            $b.hide();
        }
    }

    function updateStats(rows, totals) {
        const data = rows || [];
        let cpTotal = totals && totals.cp != null && !isNaN(Number(totals.cp))
            ? Number(totals.cp)
            : data.reduce((sum, r) => sum + Number(r.cp_inactive_child || 0), 0);
        $('#total-cp-inactive-listings').text(cpTotal.toLocaleString('en-US'));
        updateSidebarInactiveCount(cpTotal);
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

    function skuDetailUrl(row, field) {
        const data = row || {};
        return String(data.cp_detail_url || data.detail_url || '').trim();
    }

    function formatInactiveCount(cell, field) {
        const v = Number(cell.getValue() || 0);
        const row = cell.getRow().getData();
        const url = skuDetailUrl(row, field);
        const color = v === 0 ? '#198754' : '#b45309';
        const label = v.toLocaleString('en-US');
        if (!url) {
            return `<span class="il-inactive-count" style="color:${color};">${label}</span>`;
        }
        const title = field === 'cp_inactive_parent'
            ? 'Open inactive parent listings'
            : 'Open inactive child SKUs';
        return `<a href="${escapeHtml(url)}" class="il-inactive-count" style="color:${color};" title="${title}">${label}</a>`;
    }

    $(document).ready(function() {
        table = new Tabulator("#inactive-listings-table", {
            ajaxURL: "{{ url('/inactive-listings/channels-data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data, {
                    cp: response && (response.total_cp_inactive_child != null ? response.total_cp_inactive_child : response.total_cp_inactive),
                });
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "cp_inactive_child", dir: "desc" }],
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
                            return '<span class="il-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mmLogoSrc(logo);
                        return `<img src="${escapeHtml(src)}" alt="${escapeHtml(channel)}" class="il-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: "Channel",
                    field: "channel",
                    minWidth: 240,
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const url = skuDetailUrl(cell.getRow().getData(), 'cp_inactive_child') || skuDetailUrl(cell.getRow().getData());
                        if (!name) return '';
                        if (!url) return escapeHtml(name);
                        return `<a href="${escapeHtml(url)}" class="il-channel-link" title="Open Inactive Listing SKUs for ${escapeHtml(name)}">${escapeHtml(name)}</a>`;
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
                        const cls = status === 'green' ? 'il-api-dot-green'
                            : (status === 'yellow' ? 'il-api-dot-yellow' : 'il-api-dot-red');
                        const title = escapeHtml(row.api_label || status);
                        return `<span class="il-api-dot ${cls}" title="${title}"></span>`;
                    },
                },
                {
                    title: "Inactive Listing",
                    headerHozAlign: "center",
                    headerTooltip: "Present in CP Master and this marketplace, inactive on the marketplace, and inventory is not zero.",
                    columns: [
                        {
                            title: "Parent",
                            field: "cp_inactive_parent",
                            width: 110,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "CP Master parent listings that are inactive on the marketplace (0 Inv excluded)",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'cp_inactive_parent');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.cp_inactive_parent || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        },
                        {
                            title: "Child",
                            field: "cp_inactive_child",
                            width: 110,
                            hozAlign: "center",
                            sorter: "number",
                            headerTooltip: "CP Master child SKUs that are inactive on the marketplace (0 Inv excluded)",
                            formatter: function(cell) {
                                return formatInactiveCount(cell, 'cp_inactive_child');
                            },
                            bottomCalc: function(values, data) {
                                return (data || []).reduce((sum, row) => sum + Number(row.cp_inactive_child || 0), 0);
                            },
                            bottomCalcFormatter: function(cell) {
                                return Number(cell.getValue() || 0).toLocaleString('en-US');
                            },
                        },
                    ],
                },
                {
                    title: "Listings",
                    field: "cp_detail_url",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    headerHozAlign: "center",
                    headerTooltip: "Open inactive CP Master SKUs for this marketplace",
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const url = String(row.cp_detail_url || row.detail_url || row.listings_url || '').trim();
                        const name = (row.channel || 'channel').trim();
                        if (!url) {
                            return '<span class="il-listings-arrow il-listings-arrow-off" title="Listings link not available"><i class="fas fa-arrow-up-right-from-square"></i></span>';
                        }
                        return `<a href="${escapeHtml(url)}" class="il-listings-arrow il-listings-arrow-on" title="Open ${escapeHtml(name)} inactive listings" target="_self"><i class="fas fa-arrow-up-right-from-square"></i></a>`;
                    },
                },
            ],
        });

        $('#inactive-listings-search').on('input', function() {
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
