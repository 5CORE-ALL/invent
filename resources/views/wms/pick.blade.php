@extends('layouts.vertical', ['title' => 'WMS — Pick', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body row g-2">
            <div class="col-12 col-md-3">
                <label class="form-label small mb-0">Warehouse</label>
                <select id="wh" class="form-select">
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-0">SKU</label>
                <input type="text" id="sku" class="form-control" placeholder="SKU">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-0">Bin ID (optional)</label>
                <input type="number" id="binId" class="form-control" placeholder="null = loose">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-0">Qty</label>
                <input type="number" id="qty" class="form-control" value="1" min="1">
            </div>
            <div class="col-6">
                <button type="button" class="btn btn-warning w-100 py-3" id="btnLock">Lock for pick</button>
            </div>
            <div class="col-6">
                <button type="button" class="btn btn-outline-secondary w-100 py-3" id="btnUnlock">Release lock</button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Confirm pick</strong> (reduces on-hand; staff needs prior lock unless manager)</div>
        <div class="card-body row g-2">
            <div class="col-md-4">
                <input type="number" id="pid" class="form-control" placeholder="Product ID">
            </div>
            <div class="col-md-4">
                <input type="number" id="fromBin" class="form-control" placeholder="From bin ID">
            </div>
            <div class="col-md-4">
                <input type="number" id="pickQty" class="form-control" placeholder="Qty" min="1" value="1">
            </div>
            <div class="col-12">
                <label class="form-check-label"><input type="checkbox" id="forceMgr" class="form-check-input"> Manager: pick without lock</label>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-danger w-100 py-3" id="btnPick">Record PICK</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script>
        function wh() { return parseInt(document.getElementById('wh').value, 10); }
        function binOptional() {
            const v = document.getElementById('binId').value;
            return v ? parseInt(v, 10) : null;
        }
        document.getElementById('btnLock').addEventListener('click', async () => {
            try {
                await wmsJson('/wms/data/stock/lock', { method: 'POST', body: {
                    sku: document.getElementById('sku').value.trim(),
                    warehouse_id: wh(),
                    bin_id: binOptional(),
                    qty: parseInt(document.getElementById('qty').value, 10),
                }});
                alert('Locked');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('btnUnlock').addEventListener('click', async () => {
            try {
                await wmsJson('/wms/data/stock/unlock', { method: 'POST', body: {
                    sku: document.getElementById('sku').value.trim(),
                    warehouse_id: wh(),
                    bin_id: binOptional(),
                    qty: parseInt(document.getElementById('qty').value, 10),
                }});
                alert('Released');
            } catch (e) { alert(e.message); }
        });
        document.getElementById('btnPick').addEventListener('click', async () => {
            try {
                await wmsJson('/wms/data/stock/move', { method: 'POST', body: {
                    product_id: parseInt(document.getElementById('pid').value, 10),
                    type: 'PICK',
                    qty: parseInt(document.getElementById('pickQty').value, 10),
                    from_bin_id: parseInt(document.getElementById('fromBin').value, 10),
                    force_pick_without_lock: document.getElementById('forceMgr').checked,
                }});
                alert('Pick recorded');
            } catch (e) { alert(e.message); }
        });
    </script>
@endsection
