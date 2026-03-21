@extends('layouts.vertical', ['title' => 'WMS — Movement history', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0">SKU</label>
                <input type="text" id="fSku" class="form-control">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-0">Type</label>
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
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">From</label>
                <input type="date" id="fFrom" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-0">To</label>
                <input type="date" id="fTo" class="form-control">
            </div>
            <div class="col-12 col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" id="btnLoad">Load</button>
            </div>
        </div>
    </div>

    <div class="timeline-vstack" id="timeline"></div>
    <nav class="mt-3">
        <ul class="pagination pagination-sm" id="pager"></ul>
    </nav>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <style>
        .wms-tl-item { border-left: 3px solid #6c757d; padding-left: 1rem; margin-bottom: 1rem; }
        .wms-tl-item.type-GRN { border-color: #198754; }
        .wms-tl-item.type-PICK, .wms-tl-item.type-DISPATCH { border-color: #dc3545; }
        .wms-tl-item.type-PUTAWAY, .wms-tl-item.type-PACK { border-color: #0d6efd; }
        .wms-tl-item.type-ADJUSTMENT { border-color: #fd7e14; }
    </style>
    <script>
        let page = 1;
        async function load(p) {
            page = p || 1;
            const params = new URLSearchParams({ page, per_page: 25 });
            if (document.getElementById('fSku').value) params.set('sku', document.getElementById('fSku').value);
            if (document.getElementById('fType').value) params.set('type', document.getElementById('fType').value);
            if (document.getElementById('fFrom').value) params.set('from', document.getElementById('fFrom').value);
            if (document.getElementById('fTo').value) params.set('to', document.getElementById('fTo').value);
            const data = await wmsJson('/wms/data/movements?' + params.toString());
            const el = document.getElementById('timeline');
            el.innerHTML = '';
            data.data.forEach(m => {
                const div = document.createElement('div');
                div.className = 'wms-tl-item type-' + m.type;
                const who = m.user ? m.user.name : '—';
                const when = m.created_at;
                div.innerHTML = '<div class="d-flex justify-content-between"><strong>' + m.type + '</strong><small class="text-muted">' + when + '</small></div>' +
                    '<div><code>' + m.sku + '</code> × ' + m.qty + '</div>' +
                    '<div class="small text-muted">From bin: ' + (m.from_bin_id || '—') + ' → To bin: ' + (m.to_bin_id || '—') + '</div>' +
                    '<div class="small">By ' + who + '</div>';
                el.appendChild(div);
            });
            const pager = document.getElementById('pager');
            pager.innerHTML = '';
            if (data.prev_page_url) {
                const li = document.createElement('li');
                li.className = 'page-item';
                li.innerHTML = '<a class="page-link" href="#">Prev</a>';
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
