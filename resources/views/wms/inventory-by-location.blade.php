@extends('layouts.vertical', ['title' => 'WMS — Inventory by location', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-0">Warehouse</label>
                <select id="fWh" class="form-select">
                    <option value="">All</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-0">SKU contains</label>
                <input type="text" id="fSku" class="form-control" placeholder="SKU…">
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <button type="button" class="btn btn-primary w-100" id="btnLoad">Load</button>
            </div>
        </div>
    </div>

    <div class="table-responsive card border-0 shadow-sm">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>SKU</th>
                    <th>Title</th>
                    <th>Warehouse</th>
                    <th>Location</th>
                    <th class="text-end">On hand</th>
                    <th class="text-end">Locked</th>
                    <th class="text-end">Avail</th>
                    <th>Flag</th>
                </tr>
            </thead>
            <tbody id="tbody"></tbody>
        </table>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script>
        const tbody = document.getElementById('tbody');
        function badge(flag) {
            if (flag === 'low') return '<span class="badge text-bg-warning">Low</span>';
            if (flag === 'empty') return '<span class="badge text-bg-secondary">Empty</span>';
            if (flag === 'locked') return '<span class="badge text-bg-info">Locked</span>';
            return '<span class="badge text-bg-success">OK</span>';
        }
        async function load() {
            tbody.innerHTML = '<tr><td colspan="8">Loading…</td></tr>';
            const p = new URLSearchParams();
            if (document.getElementById('fWh').value) p.set('warehouse_id', document.getElementById('fWh').value);
            if (document.getElementById('fSku').value) p.set('sku', document.getElementById('fSku').value);
            try {
                const data = await wmsJson('/wms/data/inventory-rows?' + p.toString());
                tbody.innerHTML = '';
                data.data.forEach(r => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td><code>' + r.sku + '</code></td><td>' + (r.title || '') + '</td><td>' + (r.warehouse || '') + '</td><td><small>' + (r.full_path || '—') + '</small></td>' +
                        '<td class="text-end">' + r.on_hand + '</td><td class="text-end">' + r.pick_locked_qty + '</td><td class="text-end">' + r.available + '</td><td>' + badge(r.stock_flag) + '</td>';
                    tbody.appendChild(tr);
                });
                if (!data.data.length) tbody.innerHTML = '<tr><td colspan="8" class="text-muted">No rows</td></tr>';
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-danger">' + e.message + '</td></tr>';
            }
        }
        document.getElementById('btnLoad').addEventListener('click', load);
        load();
    </script>
@endsection
