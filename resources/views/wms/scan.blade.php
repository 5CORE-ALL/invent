@extends('layouts.vertical', ['title' => 'WMS — Scan', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    @if (!empty($demoScanCode))
        <div class="alert alert-info small mb-3">
            <strong>Demo:</strong> scan or type <code>{{ $demoScanCode }}</code>
            @if (!empty($demoSku))
                (SKU <code>{{ $demoSku }}</code>)
            @endif
            @if (!empty($demoBinId))
                — stock is in <strong>bin #{{ $demoBinId }}</strong> (pre-filled below for dispatch / GRN).
            @endif
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">Scan barcode or SKU</label>
            <input type="text" id="scanInput" class="form-control form-control-lg mb-2" autocomplete="off" placeholder="{{ !empty($demoScanCode) ? 'e.g. '.$demoScanCode : 'Focus here for USB scanner' }}" autofocus value="{{ $demoScanCode ?? '' }}">
            <button type="button" class="btn btn-outline-secondary w-100 py-3 mb-2" id="btnCam">Use camera (mobile)</button>
            <div id="camRegion" class="mb-2" style="max-width:400px;display:none;"></div>
            <button type="button" class="btn btn-primary w-100 py-3" id="btnLookup">Lookup</button>
        </div>
    </div>

    <div id="result" class="d-none">
        <div class="card border-0 shadow-sm border-start border-4 border-primary">
            <div class="card-body">
                <h5 id="pTitle" class="card-title"></h5>
                <p class="mb-1"><code id="pSku"></code> · <span id="pBc"></span></p>
                <input type="hidden" id="pId">
                <hr>
                <h6 class="small text-muted">Stock lines</h6>
                <ul class="list-group list-group-flush" id="lines"></ul>
                <hr>
                <div class="row g-2">
                    <div class="col-6">
                        <select id="actType" class="form-select">
                            <option value="DISPATCH">Dispatch (remove from bin)</option>
                            <option value="GRN">GRN (receive to bin)</option>
                            <option value="PUTAWAY">Putaway (move between bins)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <input type="number" id="actQty" class="form-control" min="1" value="1" placeholder="Qty">
                    </div>
                    <div class="col-12">
                        <input type="number" id="actBin" class="form-control" min="1" placeholder="To bin ID (GRN / putaway target)" value="{{ $demoBinId ?? '' }}">
                    </div>
                    <div class="col-12">
                        <input type="number" id="actFromBin" class="form-control" min="1" placeholder="From bin ID (dispatch / putaway source)" value="{{ $demoBinId ?? '' }}">
                    </div>
                    <div class="col-12">
                        <button type="button" class="btn btn-success w-100 py-3" id="btnDoMove">Submit movement</button>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Managers can adjust via API or Movements page. Staff: lock picks first on the Pick screen.</p>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const scanInput = document.getElementById('scanInput');
        let html5Qr;

        async function lookup() {
            const code = scanInput.value.trim();
            if (!code) return;
            try {
                const data = await wmsJson('/wms/data/scan-lookup?code=' + encodeURIComponent(code));
                document.getElementById('result').classList.remove('d-none');
                document.getElementById('pTitle').textContent = data.product.title;
                document.getElementById('pSku').textContent = data.product.sku;
                document.getElementById('pBc').textContent = data.product.barcode || '—';
                document.getElementById('pId').value = data.product.id;
                const ul = document.getElementById('lines');
                ul.innerHTML = '';
                data.lines.forEach(l => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    const bid = l.bin_id ? ' · bin #' + l.bin_id : '';
                    li.innerHTML = '<div><strong>' + (l.full_path || 'No bin') + '</strong>' + bid + '</div><div class="small">' + (l.warehouse || '') + ' · on hand ' + l.on_hand + ' · avail ' + l.available + '</div>';
                    ul.appendChild(li);
                });
            } catch (e) {
                alert(e.message || 'Not found');
                document.getElementById('result').classList.add('d-none');
            }
        }

        document.getElementById('btnLookup').addEventListener('click', lookup);
        scanInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); lookup(); } });

        document.getElementById('btnCam').addEventListener('click', async () => {
            const region = document.getElementById('camRegion');
            region.style.display = 'block';
            if (!html5Qr) html5Qr = new Html5Qrcode('camRegion');
            try {
                await html5Qr.start(
                    { facingMode: 'environment' },
                    { fps: 8, qrbox: { width: 240, height: 240 } },
                    (decoded) => { scanInput.value = decoded; lookup(); html5Qr.stop(); region.style.display = 'none'; },
                    () => {}
                );
            } catch (err) {
                alert('Camera error: ' + err);
                region.style.display = 'none';
            }
        });

        function parseBinField(el) {
            const raw = (el.value || '').trim();
            if (!raw) return null;
            const n = parseInt(raw, 10);
            return (Number.isFinite(n) && n > 0) ? n : null;
        }

        document.getElementById('btnDoMove').addEventListener('click', async () => {
            const pid = document.getElementById('pId').value;
            const type = document.getElementById('actType').value;
            const qty = parseInt(document.getElementById('actQty').value, 10);
            const toBin = parseBinField(document.getElementById('actBin'));
            const fromBin = parseBinField(document.getElementById('actFromBin'));
            const body = { product_id: parseInt(pid, 10), type, qty };
            if (type === 'GRN') {
                if (!toBin) { alert('GRN needs a valid to-bin ID (number ≥ 1).'); return; }
                body.to_bin_id = toBin;
            }
            if (type === 'DISPATCH') {
                if (!fromBin) { alert('Dispatch needs a valid from-bin ID (number ≥ 1).'); return; }
                body.from_bin_id = fromBin;
            }
            if (type === 'PUTAWAY') {
                if (!toBin) { alert('Putaway needs a valid to-bin ID.'); return; }
                if (fromBin) body.from_bin_id = fromBin;
                body.to_bin_id = toBin;
            }
            try {
                await wmsJson('/wms/data/stock/move', { method: 'POST', body });
                alert('Movement recorded');
                lookup();
            } catch (e) { alert(e.message); }
        });
    </script>
@endsection
