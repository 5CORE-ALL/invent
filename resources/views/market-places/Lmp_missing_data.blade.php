@extends('layouts.vertical', ['title' => 'LMP Missing data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .tabulator-col .tabulator-col-sorter,
        .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        .lmp-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .lmp-channel-logo-placeholder {
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
        .lmp-channel-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .lmp-channel-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        #stat-lmp-missing.badge,
        .badge-lmp-stat {
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
        'page_title' => 'LMP Missing data',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge badge-lmp-stat" id="stat-lmp-missing"
                        title="Sum of LMP M. from each analytics page. Green = 0 missing."
                        style="background-color:#28a745;color:#fff;">
                        LMP M. <span id="total-lmp-missing">{{ number_format(\App\Support\Marketplace\LmpMissingChannelCounts::cachedTotalOrZero()) }}</span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="lmp-missing-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="lmp-missing-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function lmpLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function paintTotal(total) {
        const n = Number(total || 0);
        $('#total-lmp-missing').text(n.toLocaleString('en-US'));
        $('#stat-lmp-missing').css('background-color', n === 0 ? '#28a745' : '#dc3545');
    }

    $(document).ready(function() {
        const table = new Tabulator('#lmp-missing-table', {
            ajaxURL: "{{ route('lmp.missing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                paintTotal(response && response.total_lmp_missing);
                return data;
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            initialSort: [{ column: 'lmp_missing', dir: 'desc' }],
            placeholder: 'No analytics channels found.',
            columns: [
                {
                    title: 'Image',
                    field: 'image',
                    headerSort: false,
                    width: 90,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="lmp-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        return `<img src="${escapeHtml(lmpLogoSrc(logo))}" alt="${escapeHtml(channel)}" class="lmp-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: 'Data',
                    field: 'channel',
                    minWidth: 240,
                    headerSort: true,
                    headerTooltip: 'Analytics page',
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const url = (cell.getRow().getData().analytics_url || '').trim();
                        if (!name) return '';
                        const safeName = escapeHtml(name);
                        if (!url) return safeName;
                        return `<a href="${escapeHtml(url)}" class="lmp-channel-link" title="Open analytics page">${safeName}</a>`;
                    }
                },
                {
                    title: 'LMP M.',
                    field: 'lmp_missing',
                    width: 160,
                    hozAlign: 'center',
                    headerSort: true,
                    sorter: 'number',
                    headerTooltip: 'SKUs with no LMP data on that analytics page. Green = 0, red otherwise.',
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const bg = v === 0 ? '#28a745' : '#dc3545';
                        return `<span class="badge" style="background:${bg};color:#fff;font-weight:700;padding:6px 10px;">LMP M. ${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => sum + Number(row.lmp_missing || 0), 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const bg = v === 0 ? '#28a745' : '#dc3545';
                        return `<span style="color:${bg};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    }
                },
            ],
        });

        $('#lmp-missing-search').on('input', function() {
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
