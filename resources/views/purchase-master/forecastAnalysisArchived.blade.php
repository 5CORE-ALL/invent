@extends('layouts.vertical', ['title' => 'Forecast Analysis · Restore Archived'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Match the visual style of the main forecast page where it makes sense.
           This page is intentionally narrower — we only need enough columns to
           identify the archived row and decide whether to restore it. */
        .archived-toolbar { gap: 0.5rem; }
        #archived-forecast-table .tabulator-row.tabulator-selected { background-color: #fff7e6; }
        .archived-empty { padding: 2rem; text-align: center; color: #6c757d; }
        .small-meta { font-size: 0.78rem; color: #6c757d; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Restore Archived Forecast Rows',
        'sub_title'  => 'Bring previously archived (sku, parent) pairs back to /forecast.analysis',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center flex-wrap archived-toolbar mb-3">
                    <a href="{{ route('forecast.analysis') }}" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Forecast Analysis</span>
                    </a>

                    <div class="vr mx-2"></div>

                    <input type="text" id="archived-search" class="form-control form-control-sm"
                           placeholder="Search SKU or Parent…" style="max-width: 240px;">

                    <button id="archived-restore-btn" type="button"
                            class="btn btn-sm btn-success fw-semibold d-flex align-items-center gap-1" disabled
                            title="Restore the rows you've ticked. Restored rows reappear on /forecast.analysis.">
                        <i class="fas fa-rotate-left"></i>
                        <span>Restore (<span id="archived-restore-count">0</span>)</span>
                    </button>

                    <button id="archived-refresh-btn" type="button"
                            class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1"
                            title="Reload archived rows from the server">
                        <i class="fas fa-sync-alt"></i>
                        <span>Refresh</span>
                    </button>

                    <span class="ms-auto small-meta" id="archived-meta">—</span>
                </div>

                <div id="archived-forecast-table"></div>
                <div id="archived-empty-msg" class="archived-empty d-none">
                    No archived rows. Archive rows from <a href="{{ route('forecast.analysis') }}">/forecast.analysis</a> to see them here.
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    // Restore page selection is independent from the main page. Keyed by
    // 'sku||parent' (same shape as the archive page), so the two pages stay
    // consistent if we ever decide to share helpers.
    const archivedSelection = new Set();
    function archivedKey(sku, parent) {
        return String(sku || '').trim() + '||' + String(parent || '').trim();
    }
    function updateRestoreBtn() {
        const count = archivedSelection.size;
        $('#archived-restore-count').text(count);
        $('#archived-restore-btn').prop('disabled', count === 0);
    }

    function formatArchivedAt(value) {
        if (!value) return '<span class="text-muted">—</span>';
        // Server returns Y-m-d H:i:s strings; show as-is plus a tooltip with the raw
        const s = String(value);
        return '<span title="' + s.replace(/"/g, '&quot;') + '">' + s + '</span>';
    }

    $(function() {
        const table = new Tabulator('#archived-forecast-table', {
            ajaxURL: "{{ route('forecast.analysis.archived.data') }}",
            ajaxConfig: 'GET',
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            paginationCounter: 'rows',
            initialSort: [{ column: 'archived_at', dir: 'desc' }],
            placeholder: 'No archived rows.',
            ajaxResponse: function(url, params, response) {
                const data = (response && response.data) || [];
                $('#archived-empty-msg').toggleClass('d-none', data.length > 0);
                $('#archived-meta').text(data.length + ' archived row(s)');
                return data;
            },
            columns: [
                {
                    title: '<input type="checkbox" id="archived-select-all" title="Select all on this page">',
                    field: '_sel',
                    headerSort: false,
                    width: 40,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const key = archivedKey(d.sku, d.parent);
                        const checked = archivedSelection.has(key) ? 'checked' : '';
                        return '<input type="checkbox" class="archived-row-cb" data-sku="' +
                            String(d.sku || '').replace(/"/g, '&quot;') + '" data-parent="' +
                            String(d.parent || '').replace(/"/g, '&quot;') + '" ' + checked + '>';
                    },
                    cellClick: function(e) { e.stopPropagation(); }
                },
                {
                    title: 'Img',
                    field: 'image',
                    headerSort: false,
                    width: 60,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return '<span class="text-muted small">—</span>';
                        const safe = String(url).replace(/"/g, '&quot;');
                        return '<img src="' + safe + '" alt="" ' +
                            'style="width:30px;height:30px;object-fit:contain;border-radius:4px;" ' +
                            "onerror=\"this.style.display='none';\">";
                    }
                },
                { title: 'SKU',         field: 'sku',           headerFilter: 'input', minWidth: 160 },
                { title: 'Parent',      field: 'parent',        headerFilter: 'input', minWidth: 110 },
                { title: 'Suppliers',   field: 'suppliers',     headerFilter: 'input', minWidth: 130 },
                { title: 'INV',         field: 'inv',           hozAlign: 'right',     width: 80,  sorter: 'number' },
                {
                    title: 'Stage',
                    field: 'stage',
                    headerFilter: 'input',
                    width: 110,
                    formatter: function(cell) {
                        const v = String(cell.getValue() || '').trim().toLowerCase();
                        const tips = {
                            '': '(no stage)',
                            'appr_req': 'Appr Req',
                            'mip': 'MIP',
                            'r2s': 'R2S',
                            'transit': 'Transit',
                            'to_order_analysis': 'Order',
                            'all_good': 'All Good',
                        };
                        if (!v) return '<span class="text-muted small">(no stage)</span>';
                        return '<span class="badge bg-secondary-subtle text-dark">' + (tips[v] || v) + '</span>';
                    }
                },
                {
                    title: 'NR',
                    field: 'nr',
                    headerFilter: 'input',
                    width: 90,
                    hozAlign: 'center',
                    // The main forecast page treats an empty stored NR as the default
                    // 'REQ' (matches the cell-formatter logic), so we do the same here
                    // so legacy archived rows (which had NR = NULL in the DB before
                    // snapshot-on-archive existed) read consistently with the page they
                    // came from.
                    formatter: function(cell) {
                        const stored = String(cell.getValue() || '').trim().toUpperCase();
                        const effective = (stored === 'REQ' || stored === 'NR' || stored === 'LATER') ? stored : 'REQ';
                        const wasDefaulted = stored === '';
                        const palette = {
                            REQ:   { bg: '#16a34a', text: '#fff', label: 'REQ' },
                            NR:    { bg: '#dc2626', text: '#fff', label: '2BDC' },
                            LATER: { bg: '#f59e0b', text: '#fff', label: 'LATER' },
                        };
                        const p = palette[effective] || palette.REQ;
                        const title = wasDefaulted
                            ? 'Default (no NR value stored)'
                            : 'Stored: ' + stored;
                        return '<span class="badge" title="' + title +
                            '" style="background:' + p.bg + ';color:' + p.text + ';' +
                            (wasDefaulted ? 'opacity:0.7;' : '') + '">' + p.label + '</span>';
                    }
                },
                {
                    title: 'REQ',
                    field: 'req',
                    headerFilter: 'input',
                    width: 80,
                    hozAlign: 'center',
                    // REQ is a free-form string in forecast_analysis (string(20)). No
                    // implicit default exists on the main page, so an empty value here
                    // is shown as a muted dash rather than fabricated.
                    formatter: function(cell) {
                        const v = String(cell.getValue() || '').trim();
                        if (!v) return '<span class="text-muted small">—</span>';
                        return '<span class="badge bg-light text-dark border">' + v + '</span>';
                    }
                },
                { title: 'MOQ',         field: 'moq',           hozAlign: 'right',     width: 90,  sorter: 'number' },
                { title: 's_msl',       field: 's_msl',         hozAlign: 'right',     width: 80,  sorter: 'number' },
                { title: 'CP',          field: 'cp',            hozAlign: 'right',     width: 80,  sorter: 'number' },
                { title: 'CBM',         field: 'cbm',           hozAlign: 'right',     width: 80,  sorter: 'number' },
                { title: 'MIP',         field: 'order_given',   hozAlign: 'right',     width: 80,  sorter: 'number' },
                { title: 'Transit',     field: 'transit',       hozAlign: 'right',     width: 80,  sorter: 'number' },
                {
                    title: 'B/S',
                    field: '_bs_links',
                    headerSort: false,
                    minWidth: 78,
                    width: 80,
                    hozAlign: 'center',
                    // Buyer / Seller buttons mirror the styling used on /forecast.analysis
                    // inside the SKU column (Clink = Buyer, Olink = Seller). Both open in
                    // a new tab; placeholder dashes when a link isn't set so the column
                    // width stays stable.
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const buyerUrl  = String(d.clink || '').trim();
                        const sellerUrl = String(d.olink || '').trim();
                        const esc = function(u) { return u.replace(/"/g, '&quot;'); };

                        const buyerBtn = buyerUrl
                            ? '<a href="' + esc(buyerUrl) + '" target="_blank" rel="noopener noreferrer" ' +
                                'class="btn btn-sm btn-outline-primary" title="Buyer Link (Clink)" ' +
                                'style="padding:1px 6px;font-size:11px;line-height:1.4;">B</a>'
                            : '<span class="text-muted small" title="No Buyer link saved" style="padding:1px 4px;">—</span>';

                        const sellerBtn = sellerUrl
                            ? '<a href="' + esc(sellerUrl) + '" target="_blank" rel="noopener noreferrer" ' +
                                'class="btn btn-sm btn-outline-secondary" title="Seller Link (Olink)" ' +
                                'style="padding:1px 6px;font-size:11px;line-height:1.4;">S</a>'
                            : '<span class="text-muted small" title="No Seller link saved" style="padding:1px 4px;">—</span>';

                        return '<span style="display:inline-flex;gap:4px;align-items:center;">' +
                            buyerBtn + sellerBtn + '</span>';
                    }
                },
                {
                    title: 'RFQ',
                    field: '_rfq_links',
                    headerSort: false,
                    width: 70,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const d = cell.getRow().getData() || {};
                        const dot = function(url, color, label) {
                            const u = String(url || '').trim();
                            if (!u) return '';
                            const safe = u.replace(/"/g, '&quot;');
                            return '<a href="' + safe + '" target="_blank" rel="noopener noreferrer" title="' + label + '" ' +
                                'style="display:inline-block;width:12px;height:12px;margin:0 2px;border-radius:50%;background:' + color + ';"></a>';
                        };
                        const html = dot(d.rfq_form_link, '#fbc02d', 'RFQ Form') +
                                     dot(d.rfq_report,    '#2e7d32', 'RFQ Report');
                        return html || '<span class="text-muted small">—</span>';
                    }
                },
                { title: 'Notes',       field: 'notes',         minWidth: 160 },
                {
                    title: 'Archived',
                    field: 'archived_at',
                    sorter: 'string',
                    minWidth: 170,
                    formatter: function(cell) { return formatArchivedAt(cell.getValue()); }
                },
                { title: 'Archived By', field: 'archived_by',   minWidth: 160 },
            ],
        });

        $(document).on('change', '.archived-row-cb', function() {
            const sku = $(this).attr('data-sku') || '';
            const parent = $(this).attr('data-parent') || '';
            const key = archivedKey(sku, parent);
            if ($(this).is(':checked')) archivedSelection.add(key);
            else {
                archivedSelection.delete(key);
                $('#archived-select-all').prop('checked', false);
            }
            updateRestoreBtn();
        });

        $(document).on('change', '#archived-select-all', function() {
            const turnOn = $(this).is(':checked');
            const rows = table.getRows('active');
            rows.forEach(function(r) {
                const d = r.getData() || {};
                const key = archivedKey(d.sku, d.parent);
                if (turnOn) archivedSelection.add(key);
                else archivedSelection.delete(key);
            });
            try { table.redraw(true); } catch (e) { /* ignore */ }
            updateRestoreBtn();
        });

        $('#archived-search').on('input', function() {
            const q = $(this).val();
            table.setFilter([
                [
                    { field: 'sku', type: 'like', value: q },
                    { field: 'parent', type: 'like', value: q },
                ]
            ]);
        });

        $('#archived-refresh-btn').on('click', function() {
            archivedSelection.clear();
            $('#archived-select-all').prop('checked', false);
            updateRestoreBtn();
            try { table.replaceData(); } catch (e) { /* ignore */ }
        });

        $('#archived-restore-btn').on('click', function() {
            if (archivedSelection.size === 0) return;
            const items = Array.from(archivedSelection).map(function(key) {
                const parts = key.split('||');
                return { sku: parts[0] || '', parent: parts[1] || '' };
            });
            if (!confirm('Restore ' + items.length + ' row(s)? They will reappear on /forecast.analysis.')) {
                return;
            }
            const $btn = $(this);
            const prevHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Restoring…');

            $.ajax({
                url: "{{ route('forecast.analysis.restore') }}",
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    items: items,
                },
                success: function(res) {
                    if (res && res.success) {
                        archivedSelection.clear();
                        $('#archived-select-all').prop('checked', false);
                        updateRestoreBtn();
                        try { table.replaceData(); } catch (e) { /* ignore */ }
                    } else {
                        alert((res && res.message) || 'Failed to restore.');
                    }
                },
                error: function(xhr) {
                    if (xhr && xhr.status === 403) {
                        alert('You are not authorized to restore rows.');
                    } else {
                        alert('Failed to restore (network or server error).');
                    }
                },
                complete: function() {
                    $btn.html(prevHtml);
                    updateRestoreBtn();
                }
            });
        });
    });
</script>
@endsection
