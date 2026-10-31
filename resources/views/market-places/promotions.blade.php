@extends('layouts.vertical', ['title' => 'Promotions', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .promo-channel-logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 2px;
            display: inline-block;
            vertical-align: middle;
        }
        .promo-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 14px;
            vertical-align: middle;
        }
        .promo-channel-logo-link {
            display: inline-flex;
            line-height: 1;
        }
        .promo-channel-name {
            font-weight: 600;
            color: #1e293b;
        }
        .promo-status-chip {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            background: #dcfce7;
            color: #15803d;
        }
        #promo-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        #promo-filter-bar .promo-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        #promotions-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Promotions',
        'sub_title'  => 'Active Channels',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" style="font-weight:bold;" title="Channels in current filter">
                            CHANNELS: <span id="promo-channel-count">0</span>
                        </span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="promo-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div class="flex-grow-1" style="min-width:200px;">
                                <label class="promo-filter-label" for="promo-search">Search</label>
                                <input type="text" id="promo-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="promo-filter-clear">Clear</button>
                            </div>
                        </div>
                    </div>
                    <div id="promotions-table" style="height: calc(100vh - 320px);"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    let table = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateBadges() {
        const visible = table ? table.getData('active') : [];
        $('#promo-channel-count').text(visible.length.toLocaleString());
    }

    function applyFilters() {
        if (!table) return;
        const q = ($('#promo-search').val() || '').trim().toLowerCase();

        if (!q) {
            table.clearFilter();
        } else {
            table.setFilter(function (data) {
                return String(data.channel || '').toLowerCase().includes(q)
                    || String(data.alias || '').toLowerCase().includes(q);
            });
        }
        updateBadges();
    }

    table = new Tabulator('#promotions-table', {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, true],
        movableColumns: false,
        ajaxURL: '{{ route("promotions.data") }}',
        ajaxConfig: 'GET',
        ajaxResponse: function (url, params, response) {
            return (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];
        },
        dataLoaded: function () {
            applyFilters();
        },
        columns: [
            {
                title: 'Img',
                field: 'image',
                width: 90,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const logo = cell.getValue();
                    const channel = escapeHtml(row.channel || '');
                    const sellerLink = (row.seller_link || '').trim();

                    const imgHtml = logo
                        ? `<img src="/storage/${escapeHtml(logo)}" alt="${channel}" class="promo-channel-logo" onerror="this.style.display='none'"/>`
                        : `<span class="promo-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>`;

                    if (sellerLink) {
                        return `<a href="${escapeHtml(sellerLink)}" target="_blank" rel="noopener noreferrer" class="promo-channel-logo-link" title="Open seller page">${imgHtml}</a>`;
                    }
                    return imgHtml;
                },
            },
            {
                title: 'Channels',
                field: 'channel',
                minWidth: 220,
                formatter: function (cell) {
                    return `<span class="promo-channel-name">${escapeHtml(cell.getValue() || '')}</span>`;
                },
            },
            {
                title: 'Status',
                field: 'status',
                width: 130,
                hozAlign: 'center',
                headerHozAlign: 'center',
                formatter: function (cell) {
                    return `<span class="promo-status-chip">${escapeHtml(cell.getValue() || 'Active')}</span>`;
                },
            },
        ],
    });

    $('#promo-search').on('keyup', applyFilters);
    $('#promo-filter-clear').on('click', function () {
        $('#promo-search').val('');
        applyFilters();
    });
})();
</script>
@endsection
