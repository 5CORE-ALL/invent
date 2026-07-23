@extends('layouts.vertical', ['title' => 'Variations Verify Masters', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .vvm-channel-logo {
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
        .vvm-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 14px;
            vertical-align: middle;
        }
        .vvm-channel-logo-link {
            display: inline-flex;
            line-height: 1;
        }
        .vvm-channel-name {
            font-weight: 600;
            color: #1e293b;
            text-decoration: none;
        }
        .vvm-channel-name.has-verify {
            color: #0d6efd;
        }
        .vvm-channel-name.has-verify:hover {
            text-decoration: underline;
        }
        .vvm-mismatch-chip {
            display: inline-block;
            min-width: 36px;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            line-height: 1.3;
            text-align: center;
        }
        .vvm-mismatch-chip--ok {
            background: #dcfce7;
            color: #15803d;
        }
        .vvm-mismatch-chip--bad {
            background: #fee2e2;
            color: #b91c1c;
        }
        .vvm-mismatch-chip--na {
            background: #f1f5f9;
            color: #94a3b8;
        }
        .vvm-nr-select {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            padding: 2px 6px;
            height: 30px;
            min-width: 84px;
            text-align: center;
            cursor: pointer;
        }
        .vvm-nr-select.is-req {
            background: #dcfce7;
            color: #15803d;
            border-color: #86efac;
        }
        .vvm-nr-select.is-nreq {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fca5a5;
        }
        #vvm-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        #vvm-filter-bar .vvm-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }
        #variations-verify-masters-table .tabulator-row .tabulator-cell {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Variations Verify Masters',
        'sub_title'  => 'Active Channels Master',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" id="vvm-req-badge" style="font-weight:bold;" title="Channels marked REQ">
                            REQ: <span id="vvm-req-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2" id="vvm-nreq-badge" style="font-weight:bold;" title="Channels marked N-REQ">
                            N-REQ: <span id="vvm-nreq-count">0</span>
                        </span>
                        <span class="badge bg-primary fs-6 p-2" id="vvm-channels-badge" style="font-weight:bold;" title="Channels in current filter">
                            CHANNELS: <span id="vvm-channel-count">0</span>
                        </span>
                        <span class="badge bg-danger fs-6 p-2" id="vvm-mismatch-badge" style="font-weight:bold;" title="Mismatch sum for channels in current filter">
                            MISMATCH: <span id="vvm-mismatch-total">{{ number_format(\App\Http\Controllers\MarketPlace\VariationsVerifyMasterController::totalMismatchCountForSidebar()) }}</span>
                        </span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="vvm-filter-bar" class="mb-2">
                        <div class="d-flex flex-wrap align-items-end gap-3">
                            <div>
                                <label class="vvm-filter-label" for="vvm-nr-filter">REQ / N-REQ</label>
                                <select id="vvm-nr-filter" class="form-select form-select-sm" style="min-width:140px;">
                                    <option value="all">All</option>
                                    <option value="REQ" selected>REQ</option>
                                    <option value="N-REQ">N-REQ</option>
                                </select>
                            </div>
                            <div class="flex-grow-1" style="min-width:200px;">
                                <label class="vvm-filter-label" for="vvm-search">Search</label>
                                <input type="text" id="vvm-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="vvm-filter-apply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="vvm-filter-clear">Clear</button>
                            </div>
                        </div>
                    </div>
                    <div id="variations-verify-masters-table" style="height: calc(100vh - 320px);"></div>
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
    let allRows = [];

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function normalizeNrReq(value) {
        const v = String(value || 'REQ').trim().toUpperCase();
        return v === 'N-REQ' || v === 'NREQ' || v === 'NR' ? 'N-REQ' : 'REQ';
    }

    function updateBadges() {
        const reqCount = allRows.filter(function (r) { return normalizeNrReq(r.nr_req) === 'REQ'; }).length;
        const nreqCount = allRows.filter(function (r) { return normalizeNrReq(r.nr_req) === 'N-REQ'; }).length;

        const visible = table ? table.getData('active') : [];
        const visibleMismatch = visible.reduce(function (sum, row) {
            const n = Number(row && row.mismatch_count);
            return sum + (Number.isFinite(n) ? n : 0);
        }, 0);

        $('#vvm-req-count').text(reqCount.toLocaleString());
        $('#vvm-nreq-count').text(nreqCount.toLocaleString());
        $('#vvm-channel-count').text(visible.length.toLocaleString());
        $('#vvm-mismatch-total').text(visibleMismatch.toLocaleString());
    }

    function applyFilters() {
        if (!table) return;
        const nrFilter = $('#vvm-nr-filter').val() || 'REQ';
        const q = ($('#vvm-search').val() || '').trim().toLowerCase();

        table.setFilter(function (data) {
            const nr = normalizeNrReq(data.nr_req);
            if (nrFilter !== 'all' && nr !== nrFilter) {
                return false;
            }
            if (!q) {
                return true;
            }
            return String(data.channel || '').toLowerCase().includes(q)
                || String(data.alias || '').toLowerCase().includes(q);
        });
        updateBadges();
    }

    table = new Tabulator('#variations-verify-masters-table', {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, true],
        movableColumns: false,
        ajaxURL: '{{ route("variations.verify.masters.data") }}',
        ajaxConfig: 'GET',
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
                    timeout: 0,
                    success: resolve,
                    error: reject,
                });
            });
        },
        ajaxResponse: function (url, params, response) {
            allRows = (response && response.success && Array.isArray(response.data))
                ? response.data
                : [];
            return allRows;
        },
        dataLoaded: function () {
            applyFilters();
        },
        columns: [
            {
                title: 'Channel Image',
                field: 'image',
                width: 130,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const logo = cell.getValue();
                    const channel = escapeHtml(row.channel || '');
                    const sellerLink = (row.seller_link || '').trim();

                    const imgHtml = logo
                        ? `<img src="/storage/${escapeHtml(logo)}" alt="${channel}" class="vvm-channel-logo" onerror="this.style.display='none'"/>`
                        : `<span class="vvm-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>`;

                    if (sellerLink) {
                        return `<a href="${escapeHtml(sellerLink)}" target="_blank" rel="noopener noreferrer" class="vvm-channel-logo-link" title="Open seller page">${imgHtml}</a>`;
                    }
                    return imgHtml;
                },
            },
            {
                title: 'Channels',
                field: 'channel',
                minWidth: 220,
                headerFilter: false,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const channel = escapeHtml(cell.getValue() || '');
                    const verifyUrl = (row.verify_url || '').trim();
                    if (verifyUrl) {
                        return `<a href="${escapeHtml(verifyUrl)}" class="vvm-channel-name has-verify" title="Open Listing Variation Verify">${channel}</a>`;
                    }
                    return `<span class="vvm-channel-name">${channel}</span>`;
                },
            },
            {
                title: 'REQ / N-REQ',
                field: 'nr_req',
                width: 120,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const value = normalizeNrReq(cell.getValue());
                    const cls = value === 'N-REQ' ? 'is-nreq' : 'is-req';
                    const id = Number(row.id) || 0;
                    return `<select class="vvm-nr-select ${cls}" data-id="${id}">
                        <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                        <option value="N-REQ" ${value === 'N-REQ' ? 'selected' : ''}>N-REQ</option>
                    </select>`;
                },
                cellClick: function (e) {
                    e.stopPropagation();
                },
            },
            {
                title: 'Mismatch',
                field: 'mismatch_count',
                width: 120,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const value = cell.getValue();
                    const verifyUrl = (row.verify_url || '').trim();

                    if (value === null || value === undefined || value === '') {
                        return '<span class="vvm-mismatch-chip vvm-mismatch-chip--na" title="No Variations Verify page for this channel">—</span>';
                    }

                    const n = Number(value) || 0;
                    const cls = n > 0 ? 'vvm-mismatch-chip--bad' : 'vvm-mismatch-chip--ok';
                    const chip = `<span class="vvm-mismatch-chip ${cls}">${n}</span>`;

                    if (verifyUrl) {
                        return `<a href="${escapeHtml(verifyUrl)}" title="Open Listing Variation Verify (Mismatch Only)">${chip}</a>`;
                    }
                    return chip;
                },
            },
        ],
    });

    $(document).on('change', '.vvm-nr-select', function () {
        const $select = $(this);
        const id = Number($select.data('id'));
        const nrReq = normalizeNrReq($select.val());
        const prev = nrReq === 'N-REQ' ? 'REQ' : 'N-REQ';

        if (!id) {
            return;
        }

        $select.prop('disabled', true);
        $.ajax({
            url: '{{ route("variations.verify.masters.update.nr.req") }}',
            method: 'POST',
            data: {
                id: id,
                nr_req: nrReq,
                _token: $('meta[name="csrf-token"]').attr('content'),
            },
            success: function (res) {
                if (!res || !res.success) {
                    $select.val(prev);
                    alert((res && res.message) ? res.message : 'Failed to update REQ / N-REQ');
                    return;
                }

                const row = table.getRows().find(function (r) {
                    return Number(r.getData().id) === id;
                });
                if (row) {
                    row.update({ nr_req: nrReq });
                }

                const idx = allRows.findIndex(function (r) { return Number(r.id) === id; });
                if (idx >= 0) {
                    allRows[idx].nr_req = nrReq;
                }

                $select
                    .toggleClass('is-req', nrReq === 'REQ')
                    .toggleClass('is-nreq', nrReq === 'N-REQ');

                applyFilters();
            },
            error: function (xhr) {
                $select.val(prev);
                const msg = (xhr.responseJSON && xhr.responseJSON.message)
                    ? xhr.responseJSON.message
                    : 'Failed to update REQ / N-REQ';
                alert(msg);
            },
            complete: function () {
                $select.prop('disabled', false);
            },
        });
    });

    $('#vvm-filter-apply').on('click', applyFilters);
    $('#vvm-nr-filter').on('change', applyFilters);
    $('#vvm-search').on('keyup', function (e) {
        if (e.key === 'Enter') {
            applyFilters();
            return;
        }
        applyFilters();
    });
    $('#vvm-filter-clear').on('click', function () {
        $('#vvm-nr-filter').val('REQ');
        $('#vvm-search').val('');
        applyFilters();
    });
})();
</script>
@endsection
