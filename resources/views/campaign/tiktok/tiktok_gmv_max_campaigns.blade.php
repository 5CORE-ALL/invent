@extends('layouts.vertical', ['title' => 'TikTok GMV Max Campaigns', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .gmvmax-badge {
            font-size: 1.15rem !important;
            padding: 0.6rem 1rem !important;
            border-radius: 0.35rem !important;
            font-weight: 700;
            color: #000 !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TikTok GMV Max Campaigns',
        'sub_title'  => 'Campaign totals from the TikTok Marketing API — same columns as the Ads Manager export',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="btn-group me-2" role="group" id="gmvmax-range">
                        <button type="button" class="btn btn-sm btn-primary" data-range="L30">L30</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-range="L7">L7</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-range="L1">L1</button>
                    </div>
                    <span class="badge gmvmax-badge" style="background:#cfe2ff" id="b-campaigns">Campaigns 0</span>
                    <span class="badge gmvmax-badge" style="background:#a5d6e8" id="b-cost">Spend $0.00</span>
                    <span class="badge gmvmax-badge" style="background:#d7e3fc" id="b-orders">Sold 0</span>
                    <span class="badge gmvmax-badge" style="background:#9ec5fe" id="b-rev">Sales $0.00</span>
                    <span class="badge gmvmax-badge" style="background:#f8d7da" id="b-acos">ACOS -</span>
                    <span class="badge gmvmax-badge" style="background:#ffe69c" id="b-roi">ROI -</span>
                    <span class="text-muted small ms-2" id="gmvmax-period"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="gmvmax-push-toggle">
                        <i class="fas fa-upload me-1"></i>Push log
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gmvmax-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <input type="text" id="gmvmax-f-name" class="form-control form-control-sm"
                                   placeholder="Filter by campaign name…" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="gmvmax-f-id" class="form-control form-control-sm"
                                   placeholder="Filter by campaign ID…" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <input type="text" id="gmvmax-f-sku" class="form-control form-control-sm"
                                   placeholder="Filter by SKU (campaigns selling it)…" autocomplete="off">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="gmvmax-f-clear">Clear filters</button>
                        </div>
                    </div>
                </div>
                <div id="gmvmax-push-panel" class="border-bottom bg-white px-3 py-2" style="display:none;">
                    <div class="d-flex align-items-center mb-1">
                        <strong class="small">Budget push log</strong>
                        <span class="text-muted small ms-2">Changes are applied to TikTok by the relay job (about every 5 minutes).</span>
                        <button type="button" class="btn btn-sm btn-link ms-auto" id="gmvmax-push-refresh">Refresh</button>
                    </div>
                    <div style="max-height:200px;overflow:auto;">
                        <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                            <thead class="table-light"><tr><th>Time (UTC)</th><th>Campaign</th><th>Budget</th><th>Status</th><th>By</th><th>Message</th></tr></thead>
                            <tbody id="gmvmax-push-body"><tr><td colspan="6" class="text-muted">No pushes yet.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div id="gmvmax-table" style="height: calc(100vh - 300px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function money(n) { return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function moneyCell(cell) {
        const v = cell.getValue();
        return (v === null || v === undefined || v === '') ? '' : money(v);
    }
    function numCol(title, field, width, fmt) {
        return { title: title, field: field, width: width, hozAlign: 'right', sorter: 'number',
                 headerSortStartingDir: 'desc', formatter: fmt };
    }

    const PUSH_URL = "{{ route('tiktok.gmv.max.campaigns.push.budget') }}";
    const PUSH_LOG_URL = "{{ route('tiktok.gmv.max.campaigns.push.log') }}";
    const CSRF = "{{ csrf_token() }}";
    let pushPollTimer = null;

    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }

    function pushBudget(row, input, btn, cell) {
        const newV = input.value.trim();
        if (newV === '' || isNaN(Number(newV))) { alert('Type the new daily budget first.'); input.focus(); return; }
        const oldV = row.budget;
        const label = (row.campaign_name || row.campaign_id);
        if (!confirm('Push daily budget to TikTok?\n\n' + label + '\n' + (oldV == null ? '-' : money(oldV)) + '  ->  ' + money(newV))) { return; }
        btn.disabled = true;
        $.ajax({ url: PUSH_URL, method: 'POST', data: { _token: CSRF, campaign_id: row.campaign_id, budget: newV } })
            .done(function() {
                input.value = '';
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Queued';
                btn.className = 'btn btn-sm btn-success';
                setTimeout(function() { btn.innerHTML = '<i class="fas fa-upload me-1"></i>Push'; btn.className = 'btn btn-sm btn-primary'; btn.disabled = false; }, 4000);
                $('#gmvmax-push-panel').show();
                loadPushLog();
                startPushPoll();
            })
            .fail(function(xhr) {
                btn.disabled = false;
                const m = (xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Push failed';
                alert(m);
            });
    }

    function loadPushLog() {
        return $.getJSON(PUSH_LOG_URL).done(function(resp) {
            const rows = (resp && resp.data) || [];
            const body = $('#gmvmax-push-body').empty();
            if (!rows.length) { body.append('<tr><td colspan="6" class="text-muted">No pushes yet.</td></tr>'); return; }
            const color = { success: 'success', failed: 'danger', pending: 'warning', processing: 'info', cancelled: 'secondary' };
            let open = false;
            rows.forEach(function(r) {
                if (r.status === 'pending' || r.status === 'processing') { open = true; }
                body.append('<tr><td>' + esc(r.created_at) + '</td><td>' + esc(r.campaign_name || '') + '<div class="text-muted">' + esc(r.campaign_id) + '</div></td><td>' +
                    (r.old_value == null ? '-' : money(r.old_value)) + ' &rarr; <strong>' + money(r.new_value) + '</strong></td><td><span class="badge bg-' + (color[r.status] || 'secondary') + '">' + esc(r.status) + '</span></td><td>' +
                    esc(r.requested_by || r.source) + '</td><td>' + esc(r.message || '') + '</td></tr>');
            });
            if (!open) { const wasPolling = !!pushPollTimer; stopPushPoll(); if (wasPolling && window.gmvTable) { window.gmvTable.replaceData(); } }
        });
    }
    function startPushPoll() { if (!pushPollTimer) { pushPollTimer = setInterval(loadPushLog, 15000); } }
    function stopPushPoll() { if (pushPollTimer) { clearInterval(pushPollTimer); pushPollTimer = null; } }

    $(document).ready(function() {
        let range = 'L30';
        const table = window.gmvTable = new Tabulator('#gmvmax-table', {
            ajaxURL: "{{ route('tiktok.gmv.max.campaigns.data') }}",
            ajaxParams: function() { return { range: range }; },
            ajaxResponse: function(_u, _p, resp) {
                const s = (resp && resp.sums) || {};
                $('#b-campaigns').text('Campaigns ' + Number(s.campaigns || 0).toLocaleString('en-US'));
                $('#b-cost').text('Spend ' + money(s.cost));
                $('#b-orders').text('Sold ' + Number(s.sku_orders || 0).toLocaleString('en-US'));
                $('#b-rev').text('Sales ' + money(s.gross_revenue));
                $('#b-acos').text('ACOS ' + (Number(s.gross_revenue) > 0 ? (Number(s.cost) / Number(s.gross_revenue) * 100).toFixed(2) + '%' : '-'));
                $('#b-roi').text('ROI ' + (s.roi == null ? '-' : Number(s.roi).toFixed(2)));
                const p = (resp && resp.period_start) ? (resp.period_start + ' → ' + resp.period_end) : '';
                const f = (resp && resp.fetched_at) ? ('  ·  fetched ' + resp.fetched_at + ' UTC') : '';
                $('#gmvmax-period').text(p ? ('Period ' + p + f) : 'No data uploaded yet for ' + range);
                return (resp && resp.data) ? resp.data : [];
            },
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 250],
            initialSort: [{ column: 'cost', dir: 'desc' }],
            placeholder: 'No GMV Max campaign data yet — it is uploaded by the daily GitHub job.',
            columns: [
                { title: 'Campaign ID', field: 'campaign_id', width: 170, sorter: 'string' },
                { title: 'Campaign name', field: 'campaign_name', minWidth: 240, sorter: 'string' },
                numCol('Current budget', 'budget', 130, moneyCell),
                { title: 'Push budget', field: 'campaign_id', width: 230, headerSort: false, download: false, hozAlign: 'center',
                  headerTooltip: 'Type a new daily budget and press Push to send it to TikTok',
                  formatter: function(cell) {
                      const d = cell.getRow().getData();
                      const wrap = document.createElement('div');
                      wrap.className = 'd-flex align-items-center justify-content-center gap-1';
                      const input = document.createElement('input');
                      input.type = 'number'; input.min = '0'; input.step = '0.01';
                      input.className = 'form-control form-control-sm'; input.style.width = '90px';
                      input.placeholder = d.budget == null ? 'Budget' : Number(d.budget).toFixed(2);
                      const btn = document.createElement('button');
                      btn.type = 'button'; btn.className = 'btn btn-sm btn-primary';
                      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Push';
                      btn.addEventListener('click', function() { pushBudget(d, input, btn, cell); });
                      input.addEventListener('keydown', function(e) { if (e.key === 'Enter') { btn.click(); } });
                      wrap.appendChild(input); wrap.appendChild(btn);
                      return wrap;
                  } },
                numCol('Spend', 'cost', 110, moneyCell),
                numCol('Sold', 'sku_orders', 110, function(c) { return Number(c.getValue() || 0).toLocaleString('en-US'); }),
                numCol('CPS', 'cost_per_order', 100, moneyCell),
                numCol('Sales', 'gross_revenue', 120, moneyCell),
                { title: 'ACOS', field: 'acos', width: 100, hozAlign: 'right', sorter: 'number', headerSortStartingDir: 'asc',
                  formatter: function(c) { const v = c.getValue(); return (v == null) ? '' : Number(v).toFixed(2) + '%'; } },
                numCol('ROI', 'roi', 90, function(c) { const v = c.getValue(); return (v == null || v === '') ? '' : Number(v).toFixed(2); }),
                { title: 'SKUs', field: 'skus', minWidth: 220, sorter: 'string', tooltip: true,
                  formatter: function(c) { const v = c.getValue() || ''; return v.length > 60 ? v.substring(0, 57) + '… (' + (c.getRow().getData().products || 0) + ')' : v; } },
                { title: 'Status', field: 'status', width: 110, hozAlign: 'center', sorter: 'string' },
                { title: 'Type', field: 'promotion_type', width: 110, hozAlign: 'center', sorter: 'string' },
                { title: 'Currency', field: 'currency', width: 90, hozAlign: 'center' },
            ],
        });

        $('#gmvmax-range button').on('click', function() {
            range = $(this).data('range');
            $('#gmvmax-range button').removeClass('btn-primary').addClass('btn-outline-primary');
            $(this).removeClass('btn-outline-primary').addClass('btn-primary');
            table.setData("{{ route('tiktok.gmv.max.campaigns.data') }}", { range: range });
        });

        function applyFilters() {
            const name = $('#gmvmax-f-name').val().trim().toLowerCase();
            const id = $('#gmvmax-f-id').val().trim();
            const sku = $('#gmvmax-f-sku').val().trim().toLowerCase();
            if (!name && !id && !sku) { table.clearFilter(true); return; }
            table.setFilter(function(row) {
                if (name && !String(row.campaign_name || '').toLowerCase().includes(name)) return false;
                if (id && !String(row.campaign_id || '').includes(id)) return false;
                if (sku && !String(row.skus || '').toLowerCase().includes(sku)) return false;
                return true;
            });
        }
        $('#gmvmax-f-name, #gmvmax-f-id, #gmvmax-f-sku').on('input', applyFilters);
        $('#gmvmax-f-clear').on('click', function() {
            $('#gmvmax-f-name, #gmvmax-f-id, #gmvmax-f-sku').val('');
            table.clearFilter(true);
        });

        $('#gmvmax-push-toggle').on('click', function() {
            const p = $('#gmvmax-push-panel');
            p.toggle();
            if (p.is(':visible')) { loadPushLog().done(function() { startPushPoll(); }); } else { stopPushPoll(); }
        });
        $('#gmvmax-push-refresh').on('click', loadPushLog);

        $('#gmvmax-csv').on('click', function() { table.download('csv', 'tiktok_gmv_max_campaigns_' + range + '.csv'); });
    });
</script>
@endsection
