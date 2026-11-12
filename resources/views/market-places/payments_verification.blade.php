@extends('layouts.vertical', ['title' => 'Payments Verification', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .pv-logo { width: 36px; height: 36px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e9ecef; padding: 2px; vertical-align: middle; }
        .pv-logo-placeholder { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 6px; background: #f8f9fa; border: 1px solid #e9ecef; color: #adb5bd; font-size: 14px; vertical-align: middle; }
        .pv-type { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #e0e7ff; color: #3730a3; }
        .pv-type.t-b2b { background: #fef3c7; color: #92400e; }
        .pv-type.t-ds { background: #dcfce7; color: #166534; }
        .pv-type.t-ws { background: #fce7f3; color: #9d174d; }
        .pv-pen { border: 0; background: transparent; color: #0d6efd; cursor: pointer; padding: 0 4px; }
        .pv-pen:hover { color: #0a58ca; }
        .pv-dot { cursor: pointer; font-size: 9px; margin-left: 4px; }
        .pv-recd { font-weight: 700; }
        .pv-empty { color: #adb5bd; }
        .pv-tri { display: inline-block; width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-bottom: 10px solid #dc3545; margin-right: 5px; vertical-align: middle; }
        .pv-pct { font-weight: 700; }
        .pv-pct.low { color: #dc3545; }
        .pv-pct.ok { color: #198754; }
        #pv-low-badge { cursor: pointer; user-select: none; }
        #pv-low-badge.active { outline: 3px solid #f1aeb5; }
        .pv-link-cell { display: inline-block; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
        #payments-verification-table .tabulator-row .tabulator-cell { vertical-align: middle; }
        #pvHistoryChartWrap { position: relative; height: 260px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Payments Verification',
        'sub_title'  => 'Active Channels Master',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" style="font-weight:bold;">CHANNELS: <span id="pv-count">0</span></span>
                        <span class="badge bg-success fs-6 p-2" style="font-weight:bold;" title="Sum of Sales (L30) for channels shown">SALES: <span id="pv-sales">$0</span></span>
                        <span class="badge bg-info fs-6 p-2" style="font-weight:bold;" title="Sum of PMT RECD for channels shown">PMT RECD: <span id="pv-recd">0</span></span>
                        <span class="badge bg-danger fs-6 p-2" id="pv-low-badge" style="font-weight:bold;" title="Channels whose PMT RECD is under 70% of Sales. Click to show only these.">
                            <span class="pv-tri" style="border-bottom-color:#fff;"></span>BELOW 70%: <span id="pv-low">0</span>
                        </span>
                        <input type="search" id="pv-search" class="form-control form-control-sm ms-auto" style="max-width:260px;" placeholder="Search channel, bank account, link..." autocomplete="off">
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="payments-verification-table" style="height: calc(100vh - 300px);"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    {{-- PMT RECD amount --}}
    <div class="modal fade" id="pvAmountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PMT RECD</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="pvAmountChannel"></div>
                    <label class="form-label" for="pvAmountInput">Amount received</label>
                    <input type="text" id="pvAmountInput" class="form-control" maxlength="6" inputmode="decimal" autocomplete="off" placeholder="e.g. 1250">
                    <div class="form-text">Up to 6 characters.</div>
                    <div class="text-danger small mt-2 d-none" id="pvAmountErr"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pvAmountSave">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bank account + link --}}
    <div class="modal fade" id="pvDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit payment details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="pvDetailsChannel"></div>
                    <div class="mb-3">
                        <label class="form-label" for="pvBankInput">Bank account</label>
                        <input type="text" id="pvBankInput" class="form-control" maxlength="255" autocomplete="off">
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="pvLinkInput">Link</label>
                        <input type="url" id="pvLinkInput" class="form-control" maxlength="1000" placeholder="https://..." autocomplete="off">
                    </div>
                    <div class="text-danger small mt-2 d-none" id="pvDetailsErr"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pvDetailsSave">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- History + graph --}}
    <div class="modal fade" id="pvHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PMT RECD history <span class="text-muted fs-6" id="pvHistoryChannel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="pvHistoryEmpty" class="text-muted d-none">No amounts saved yet.</div>
                    <div id="pvHistoryBody">
                        <div id="pvHistoryChartWrap"><canvas id="pvHistoryChart"></canvas></div>
                        <table class="table table-sm mt-3 mb-0">
                            <thead><tr><th>Date</th><th class="text-end">Amount</th><th>By</th></tr></thead>
                            <tbody id="pvHistoryRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const urls = {
        data: @json(route('payments.verification.data')),
        history: @json(route('payments.verification.history')),
        amount: @json(route('payments.verification.amount')),
        details: @json(route('payments.verification.details')),
    };
    const csrf = $('meta[name="csrf-token"]').attr('content');
    let table = null;
    let historyChart = null;
    let current = null; // row data of the channel being edited

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(v) {
        const n = parseFloat(String(v == null ? '' : v).replace(/[^0-9.\-]/g, ''));
        return isFinite(n) ? n : 0;
    }
    function money(v) { return '$' + Math.round(num(v)).toLocaleString('en-US'); }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function errMsg(xhr, fallback) {
        const j = xhr && xhr.responseJSON;
        if (j && j.errors) return Object.values(j.errors).flat().join(' ');
        return (j && j.message) || fallback;
    }
    function rowFor(key) {
        return table.getRows().find(function (r) { return r.getData().channel_key === key; });
    }

    const LOW_PCT = 70;
    let onlyLow = false;

    // PMT RECD ÷ Sales (L30) as a percentage. Blank when nothing is entered or Sales is 0.
    function pctOf(d) {
        const recd = d.pmt_recd;
        if (recd === null || recd === undefined || recd === '') return null;
        const sales = num(d.sales);
        if (sales <= 0) return null;
        return (num(recd) / sales) * 100;
    }
    function isLow(d) {
        const p = pctOf(d);
        return p !== null && p < LOW_PCT;
    }

    function updateBadges() {
        const rows = table ? table.getData('active') : [];
        $('#pv-low').text((table ? table.getData() : []).filter(isLow).length.toLocaleString());
        $('#pv-count').text(rows.length.toLocaleString());
        $('#pv-sales').text(money(rows.reduce(function (s, r) { return s + num(r.sales); }, 0)));
        $('#pv-recd').text(rows.reduce(function (s, r) { return s + num(r.pmt_recd); }, 0).toLocaleString('en-US', { maximumFractionDigits: 2 }));
    }

    function applySearch() {
        const q = ($('#pv-search').val() || '').trim().toLowerCase();
        if (!q && !onlyLow) { table.clearFilter(); updateBadges(); return; }
        table.setFilter(function (d) {
            if (onlyLow && !isLow(d)) return false;
            if (!q) return true;
            return (String(d.channel) + ' ' + String(d.bank_account || '') + ' ' + String(d.link || '')).toLowerCase().indexOf(q) !== -1;
        });
        updateBadges();
    }

    function typeBadge(type) {
        const t = String(type || '').trim();
        if (!t) return '<span class="pv-empty">-</span>';
        const slug = t.toLowerCase().replace(/[^a-z0-9]+/g, '');
        const map = { b2b: ['t-b2b', 'B2B'], dropship: ['t-ds', 'DS'], ds: ['t-ds', 'DS'], wholesale: ['t-ws', 'WS'] };
        const m = map[slug] || ['', t];
        return '<span class="pv-type ' + m[0] + '" title="' + esc(t) + '">' + esc(m[1]) + '</span>';
    }

    table = new Tabulator('#payments-verification-table', {
        layout: 'fitColumns',
        placeholder: 'Loading channels…',
        pagination: false,
        movableColumns: false,
        initialSort: [{ column: 'sales', dir: 'desc' }],
        ajaxURL: urls.data,
        ajaxConfig: 'GET',
        ajaxRequestFunc: function (url, config, params) {
            return new Promise(function (resolve, reject) {
                $.ajax({ url: url, type: 'GET', data: params, timeout: 0, success: resolve, error: reject });
            });
        },
        ajaxResponse: function (url, params, response) {
            const rows = (response && response.success && Array.isArray(response.data)) ? response.data : [];
            rows.forEach(function (r) { r.pct = pctOf(r); });
            return rows;
        },
        ajaxError: function (xhr) {
            $('#payments-verification-table .tabulator-placeholder-contents').text(errMsg(xhr, 'Could not load channels.'));
        },
        dataLoaded: updateBadges,
        columns: [
            {
                title: 'Img', field: 'logo', width: 70, hozAlign: 'center', headerSort: false, frozen: true,
                formatter: function (cell) {
                    const d = cell.getRow().getData();
                    const img = cell.getValue()
                        ? '<img src="/storage/' + esc(cell.getValue()) + '" alt="' + esc(d.channel) + '" class="pv-logo" onerror="this.style.display=\'none\'">'
                        : '<span class="pv-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                    const link = String(d.seller_link || '').trim();
                    return link ? '<a href="' + esc(link) + '" target="_blank" rel="noopener noreferrer" title="Open seller page">' + img + '</a>' : img;
                },
            },
            { title: 'MP', field: 'channel', minWidth: 140, frozen: true, formatter: function (c) { return '<strong>' + esc(c.getValue()) + '</strong>'; } },
            { title: 'Type', field: 'type', width: 80, hozAlign: 'center', formatter: function (c) { return typeBadge(c.getValue()); } },
            {
                title: 'Sales', field: 'sales', width: 130, hozAlign: 'center', sorter: 'number',
                headerTooltip: 'L30 Sales — same figure as Active Channel Master',
                formatter: function (c) { return '<span style="font-weight:600;">' + money(c.getValue()) + '</span>'; },
                bottomCalc: 'sum',
                bottomCalcFormatter: function (c) { return '<strong>' + money(c.getValue()) + '</strong>'; },
            },
            {
                title: 'PMT RECD', field: 'pmt_recd', width: 160, hozAlign: 'center', sorter: function (a, b) { return num(a) - num(b); },
                formatter: function (cell) {
                    const d = cell.getRow().getData();
                    const v = cell.getValue();
                    const value = (v === null || v === undefined || v === '') ? '<span class="pv-empty">—</span>' : '<span class="pv-recd">' + esc(v) + '</span>';
                    const color = d.history_count > 0 ? '#0d6efd' : '#adb5bd';
                    return value
                        + ' <button type="button" class="pv-pen" data-act="amount" title="Enter PMT RECD"><i class="fas fa-pen"></i></button>'
                        + '<i class="fas fa-circle pv-dot" data-act="history" style="color:' + color + ';" title="View history"></i>';
                },
                cellClick: function (e, cell) {
                    const act = $(e.target).closest('[data-act]').data('act');
                    if (act === 'amount') openAmount(cell.getRow().getData());
                    if (act === 'history') openHistory(cell.getRow().getData());
                },
                bottomCalc: function (values) { return values.reduce(function (s, v) { return s + num(v); }, 0); },
                bottomCalcFormatter: function (c) { return '<strong>' + Number(c.getValue()).toLocaleString('en-US', { maximumFractionDigits: 2 }) + '</strong>'; },
            },
            {
                title: 'Percentage', field: 'pct', width: 120, hozAlign: 'center',
                headerTooltip: 'PMT RECD ÷ Sales (last 30 days). Red triangle when under 70%.',
                sorter: function (a, b) {
                    const x = (a === null || a === undefined) ? -Infinity : a;
                    const y = (b === null || b === undefined) ? -Infinity : b;
                    return x === y ? 0 : (x < y ? -1 : 1);
                },
                formatter: function (cell) {
                    const p = cell.getValue();
                    if (p === null || p === undefined) return '<span class="pv-empty" title="Enter PMT RECD (and Sales must be above 0)">—</span>';
                    const low = p < LOW_PCT;
                    const text = (Math.round(p * 10) / 10).toLocaleString('en-US') + '%';
                    return (low ? '<span class="pv-tri" title="Under ' + LOW_PCT + '% of Sales"></span>' : '')
                        + '<span class="pv-pct ' + (low ? 'low' : 'ok') + '">' + text + '</span>';
                },
            },
            {
                title: 'Bank Account', field: 'bank_account', minWidth: 180,
                formatter: function (c) { const v = c.getValue(); return v ? esc(v) : '<span class="pv-empty">—</span>'; },
            },
            {
                title: 'Link', field: 'link', minWidth: 200,
                formatter: function (c) {
                    const v = String(c.getValue() || '').trim();
                    return v ? '<a class="pv-link-cell" href="' + esc(v) + '" target="_blank" rel="noopener noreferrer" title="' + esc(v) + '">' + esc(v) + '</a>' : '<span class="pv-empty">—</span>';
                },
            },
            {
                title: 'Edit', field: '_edit', width: 80, hozAlign: 'center', headerSort: false,
                formatter: function () { return '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Edit bank account and link"><i class="fas fa-edit"></i></button>'; },
                cellClick: function (e, cell) { openDetails(cell.getRow().getData()); },
            },
        ],
    });

    // ---- PMT RECD amount ----
    function openAmount(d) {
        current = d;
        $('#pvAmountChannel').text(d.channel);
        $('#pvAmountInput').val(d.pmt_recd || '');
        $('#pvAmountErr').addClass('d-none');
        modal('pvAmountModal').show();
    }
    document.getElementById('pvAmountModal').addEventListener('shown.bs.modal', function () { $('#pvAmountInput').trigger('focus').trigger('select'); });
    $('#pvAmountInput').on('input', function () { this.value = this.value.replace(/[^0-9.]/g, '').slice(0, 6); });
    $('#pvAmountInput').on('keydown', function (e) { if (e.key === 'Enter') $('#pvAmountSave').trigger('click'); });

    $('#pvAmountSave').on('click', function () {
        const amount = String($('#pvAmountInput').val() || '').trim();
        const $err = $('#pvAmountErr');
        if (!/^\d+(\.\d+)?$/.test(amount) || amount.length > 6) {
            $err.text('Enter a number of up to 6 characters, for example 1250 or 99.5.').removeClass('d-none');
            return;
        }
        const $btn = $(this).prop('disabled', true);
        $.ajax({ url: urls.amount, method: 'POST', data: { _token: csrf, channel: current.channel, amount: amount } })
            .done(function (res) {
                const row = rowFor(res.data.channel_key);
                if (row) {
                    const next = Object.assign({}, row.getData(), { pmt_recd: res.data.pmt_recd, history_count: res.data.history_count });
                    row.update({ pmt_recd: next.pmt_recd, history_count: next.history_count, pct: pctOf(next) });
                }
                modal('pvAmountModal').hide();
                applySearch();
            })
            .fail(function (xhr) { $err.text(errMsg(xhr, 'Could not save.')).removeClass('d-none'); })
            .always(function () { $btn.prop('disabled', false); });
    });

    // ---- Bank account + link ----
    function openDetails(d) {
        current = d;
        $('#pvDetailsChannel').text(d.channel);
        $('#pvBankInput').val(d.bank_account || '');
        $('#pvLinkInput').val(d.link || '');
        $('#pvDetailsErr').addClass('d-none');
        modal('pvDetailsModal').show();
    }
    $('#pvDetailsSave').on('click', function () {
        const $err = $('#pvDetailsErr');
        const $btn = $(this).prop('disabled', true);
        $.ajax({
            url: urls.details, method: 'POST',
            data: { _token: csrf, channel: current.channel, bank_account: $('#pvBankInput').val(), link: $('#pvLinkInput').val() },
        }).done(function (res) {
            const row = rowFor(res.data.channel_key);
            if (row) row.update({ bank_account: res.data.bank_account, link: res.data.link });
            modal('pvDetailsModal').hide();
        }).fail(function (xhr) {
            $err.text(errMsg(xhr, 'Could not save.')).removeClass('d-none');
        }).always(function () { $btn.prop('disabled', false); });
    });

    // ---- History + graph ----
    function openHistory(d) {
        $('#pvHistoryChannel').text('— ' + d.channel);
        $('#pvHistoryRows').empty();
        $('#pvHistoryEmpty').addClass('d-none');
        $('#pvHistoryBody').removeClass('d-none');
        modal('pvHistoryModal').show();

        $.getJSON(urls.history, { channel: d.channel }).done(function (res) {
            const list = (res && res.history) || [];
            if (!list.length) {
                $('#pvHistoryBody').addClass('d-none');
                $('#pvHistoryEmpty').removeClass('d-none');
                return;
            }
            $('#pvHistoryRows').html(list.slice().reverse().map(function (h) {
                return '<tr><td>' + esc(h.at) + '</td><td class="text-end fw-semibold">' + esc(h.amount) + '</td><td>' + esc(h.by || '') + '</td></tr>';
            }).join(''));
            if (historyChart) historyChart.destroy();
            historyChart = new Chart(document.getElementById('pvHistoryChart'), {
                type: 'line',
                data: {
                    labels: list.map(function (h) { return h.at; }),
                    datasets: [{ label: 'PMT RECD', data: list.map(function (h) { return h.value; }), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', fill: true, tension: 0.25, pointRadius: 4 }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        }).fail(function (xhr) {
            $('#pvHistoryBody').addClass('d-none');
            $('#pvHistoryEmpty').text(errMsg(xhr, 'Could not load history.')).removeClass('d-none');
        });
    }

    $('#pv-search').on('input', applySearch);
    $('#pv-low-badge').on('click', function () {
        onlyLow = !onlyLow;
        $(this).toggleClass('active', onlyLow);
        applySearch();
    });
})();
</script>
@endsection
