@extends('layouts.vertical', ['title' => 'price < 80% of LMP', 'sidenav' => 'condensed'])

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
        #stat-price-lt80-lmp.badge,
        .badge-plt-stat {
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
        'page_title' => 'price < 80% of LMP',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge badge-plt-stat" id="stat-price-lt80-lmp"
                        title="Sum of purple-triangle (Price &lt; 80% of LMP) counts from each analytics page. Green = 0."
                        style="background-color:#28a745;color:#fff;">
                        price &lt; 80% of LMP <span id="total-price-lt80-lmp">{{ number_format(\App\Support\Marketplace\PriceLt80LmpChannelCounts::cachedTotalOrZero()) }}</span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="price-lt80-lmp-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="price-lt80-lmp-table" style="height: calc(100vh - 280px);"></div>
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
        $('#total-price-lt80-lmp').text(n.toLocaleString('en-US'));
        $('#stat-price-lt80-lmp').css('background-color', n === 0 ? '#28a745' : '#6f42c1');
    }

    $(document).ready(function() {
        const table = new Tabulator('#price-lt80-lmp-table', {
            ajaxURL: "{{ route('price.lt80.lmp.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                paintTotal(response && response.total_price_lt80_lmp);
                return data;
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            initialSort: [{ column: 'price_lt80_lmp', dir: 'desc' }],
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
                    title: 'price < 80 % of lmp',
                    field: 'price_lt80_lmp',
                    width: 220,
                    hozAlign: 'center',
                    headerSort: true,
                    sorter: 'number',
                    headerTooltip: 'SKUs where Price &lt; 80% of LMP (purple triangle). Green = 0, purple otherwise.',
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const bg = v === 0 ? '#28a745' : '#6f42c1';
                        return `<span class="badge" style="background:${bg};color:#fff;font-weight:700;padding:6px 10px;"><i class="fas fa-exclamation-triangle"></i> ${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => sum + Number(row.price_lt80_lmp || 0), 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        const bg = v === 0 ? '#28a745' : '#6f42c1';
                        return `<span style="color:${bg};font-weight:700;">${v.toLocaleString('en-US')}</span>`;
                    }
                },
            ],
        });

        $('#price-lt80-lmp-search').on('input', function() {
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
