@extends('layouts.vertical', ['title' => 'WMS — Putaway', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Putaway</strong> — move from loose or source bin to target bin</div>
        <div class="card-body row g-2">
            <div class="col-md-3">
                <label class="form-label small mb-0">Product ID</label>
                <input type="number" id="pid" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">Qty</label>
                <input type="number" id="qty" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">From bin ID (empty = loose)</label>
                <input type="number" id="fromBin" class="form-control" placeholder="optional">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-0">To bin ID</label>
                <input type="number" id="toBin" class="form-control" required>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-primary w-100 py-3" id="btnGo">Submit PUTAWAY</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script>
        document.getElementById('btnGo').addEventListener('click', async () => {
            const fromVal = document.getElementById('fromBin').value;
            const body = {
                product_id: parseInt(document.getElementById('pid').value, 10),
                type: 'PUTAWAY',
                qty: parseInt(document.getElementById('qty').value, 10),
                to_bin_id: parseInt(document.getElementById('toBin').value, 10),
            };
            if (fromVal) body.from_bin_id = parseInt(fromVal, 10);
            try {
                await wmsJson('/wms/data/stock/move', { method: 'POST', body });
                alert('Putaway recorded');
            } catch (e) { alert(e.message); }
        });
    </script>
@endsection
