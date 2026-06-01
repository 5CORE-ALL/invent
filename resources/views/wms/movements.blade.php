@extends('layouts.vertical', ['title' => 'WMS — Movement history', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm mb-3 mx-auto wms-movements-wrap">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label small fw-semibold mb-1" for="fSku">SKU</label>
                    <input type="text" id="fSku" class="form-control" autocomplete="off" placeholder="Filter by SKU">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label small fw-semibold mb-1" for="fType">Type</label>
                    <select id="fType" class="form-select">
                        <option value="">All</option>
                        <option>GRN</option>
                        <option>PUTAWAY</option>
                        <option>PICK</option>
                        <option>PACK</option>
                        <option>DISPATCH</option>
                        <option>ADJUSTMENT</option>
                    </select>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold mb-1" for="fFrom">From</label>
                    <input type="date" id="fFrom" class="form-control">
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <label class="form-label small fw-semibold mb-1" for="fTo">To</label>
                    <input type="date" id="fTo" class="form-control">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <button type="button" class="btn btn-primary w-100" id="btnLoad">Load</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 mx-auto wms-movements-wrap">
        <div id="timeline" class="table-responsive"></div>
    </div>
    <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination pagination-sm mb-0" id="pager"></ul>
    </nav>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <style>
        .wms-movements-wrap { max-width: 72rem; }
        .wms-move-table {
            font-size: 0.875rem;
            width: 100%;
            table-layout: fixed;
        }
        .wms-move-table th,
        .wms-move-table td {
            text-align: center;
            vertical-align: middle;
        }
        .wms-move-table th { white-space: nowrap; }
        .wms-move-table td.wms-move-from,
        .wms-move-table td.wms-move-to {
            max-width: 14rem;
            word-wrap: break-word;
        }
    </style>
    <script>
        let page = 1;

        function esc(s) {
            if (s == null) return '';
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/"/g, '&quot;');
        }

        function wmsFormatMovementTime(iso) {
            if (!iso) return '—';
            const d = new Date(iso);
            if (Number.isNaN(d.getTime())) return iso;
            try {
                return new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(d);
            } catch (e) {
                return d.toLocaleString();
            }
        }

        function wmsMovementFrom(m) {
            const inv = m.inventory;
            if (m.from_bin_id) {
                return 'Bin #' + m.from_bin_id;
            }
            if (m.type === 'PUTAWAY' && m.to_bin_id) {
                return 'No bin (loose stock)';
            }
            if (m.type === 'PICK' && m.to_bin_id) {
                return 'No bin (loose stock)';
            }
            if ((m.type === 'DISPATCH' || m.type === 'PICK') && !m.to_bin_id) {
                if (inv && (inv.bin_id === null || inv.bin_id === undefined)) {
                    const wh = (inv.warehouse && inv.warehouse.name) ? inv.warehouse.name + ' · ' : '';
                    return 'No bin · ' + wh + 'inv #' + inv.id;
                }
                if (m.inventory_id) {
                    return 'No bin · inv #' + m.inventory_id;
                }
            }
            if (m.type === 'GRN') {
                return 'Receipt (external)';
            }
            return '—';
        }

        function wmsMovementTo(m) {
            if (m.to_bin_id) {
                return 'Bin #' + m.to_bin_id;
            }
            if (m.type === 'DISPATCH') {
                return 'Shipped out (no shelf bin)';
            }
            return '—';
        }

        function wmsTypeBadgeClass(type) {
            switch (type) {
                case 'GRN':
                    return 'bg-success';
                case 'DISPATCH':
                case 'PICK':
                    return 'bg-danger';
                case 'PUTAWAY':
                case 'PACK':
                    return 'bg-primary';
                case 'ADJUSTMENT':
                    return 'bg-warning text-dark';
                default:
                    return 'bg-secondary';
            }
        }

        async function load(p) {
            page = p || 1;
            const params = new URLSearchParams({ page, per_page: 25 });
            if (document.getElementById('fSku').value) params.set('sku', document.getElementById('fSku').value);
            if (document.getElementById('fType').value) params.set('type', document.getElementById('fType').value);
            if (document.getElementById('fFrom').value) params.set('from', document.getElementById('fFrom').value);
            if (document.getElementById('fTo').value) params.set('to', document.getElementById('fTo').value);
            const data = await wmsJson('/wms/data/movements?' + params.toString());
            const el = document.getElementById('timeline');

            if (!data.data || data.data.length === 0) {
                el.innerHTML = '<div class="p-4 text-center text-muted mb-0">No movements match these filters.</div>';
            } else {
                const rows = data.data.map(m => {
                    const who = m.user ? m.user.name : '—';
                    const when = wmsFormatMovementTime(m.created_at);
                    const qty = parseInt(m.qty, 10);
                    const qtyStr = Number.isFinite(qty) ? String(qty) : esc(m.qty);
                    const badge = wmsTypeBadgeClass(m.type);
                    return '<tr>' +
                        '<td><span class="badge ' + badge + '">' + esc(m.type) + '</span></td>' +
                        '<td><time datetime="' + esc(m.created_at) + '">' + esc(when) + '</time></td>' +
                        '<td><code class="text-primary small">' + esc(m.sku) + '</code></td>' +
                        '<td><strong>' + qtyStr + '</strong></td>' +
                        '<td class="wms-move-from small text-muted">' + esc(wmsMovementFrom(m)) + '</td>' +
                        '<td class="wms-move-to small text-muted">' + esc(wmsMovementTo(m)) + '</td>' +
                        '<td class="small text-muted">' + esc(who) + '</td>' +
                        '</tr>';
                }).join('');
                el.innerHTML =
                    '<table class="table table-sm table-hover align-middle mb-0 wms-move-table">' +
                    '<thead class="table-light">' +
                    '<tr>' +
                    '<th scope="col">Type</th>' +
                    '<th scope="col">When</th>' +
                    '<th scope="col">SKU</th>' +
                    '<th scope="col">Qty</th>' +
                    '<th scope="col">From</th>' +
                    '<th scope="col">To</th>' +
                    '<th scope="col">User</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>' + rows + '</tbody>' +
                    '</table>';
            }

            const pager = document.getElementById('pager');
            pager.innerHTML = '';
            if (data.prev_page_url) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = '<a class="page-link" href="#">Previous</a>';
                li.querySelector('a').onclick = (e) => { e.preventDefault(); load(page - 1); };
                pager.appendChild(li);
            }
            if (data.next_page_url) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = '<a class="page-link" href="#">Next</a>';
                li.querySelector('a').onclick = (e) => { e.preventDefault(); load(page + 1); };
                pager.appendChild(li);
            }
        }
        document.getElementById('btnLoad').addEventListener('click', () => load(1));
        load(1).catch(e => alert(e.message));
    </script>
@endsection
