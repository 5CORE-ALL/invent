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

    <style>
        #camRegion { max-width: 100%; width: 100%; }
        #camRegion video,
        #camRegion canvas { max-width: 100% !important; height: auto !important; border-radius: 0.375rem; }
    </style>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">Scan barcode or SKU</label>
            <input type="text" id="scanInput" class="form-control form-control-lg mb-2" autocomplete="off" placeholder="{{ !empty($demoScanCode) ? 'e.g. '.$demoScanCode : 'Focus here for USB scanner' }}" autofocus value="{{ $demoScanCode ?? '' }}">
            <button type="button" class="btn btn-outline-secondary w-100 py-3 mb-2" id="btnCam">Use camera (mobile)</button>
            <div id="camRegion" class="mb-2" style="display:none;"></div>
            <p id="camScanStatus" class="small text-muted mb-2" role="status" aria-live="polite"></p>
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
                <h6 class="small text-muted mb-2">Stock (Shopify)</h6>
                <p class="small text-muted mb-2"><code>shopify_skus</code> by SKU: <strong>inv</strong> = stock · <strong>quantity</strong> = sold (not stock). Not from WMS <code>inventory</code>.</p>
                <ul class="list-group list-group-flush" id="lines"></ul>
                <h6 class="small text-muted mt-3 mb-2">WMS warehouse (<code>inventories</code>)</h6>
                <p class="small text-muted mb-2"><strong>Dispatch / Putaway</strong> use this table. Shelved stock: enter <strong>From bin ID</strong>. <span class="text-danger">No bin</span> stock: leave From bin empty. If you see <em>multiple no-bin rows</em> in the same warehouse, tap <strong>Use this row</strong> on the correct line (or enter <strong>Source inv row #</strong> below).</p>
                <ul class="list-group list-group-flush mb-0" id="warehouseLines"></ul>
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
                        <input type="number" id="actFromBin" class="form-control" min="1" placeholder="From bin ID (optional if unassigned / no-bin stock)" value="">
                    </div>
                    <div class="col-12">
                        <input type="number" id="actFromWarehouse" class="form-control" min="1" placeholder="From warehouse ID (optional; must match row when using no-bin)">
                    </div>
                    <div class="col-12">
                        <input type="number" id="actSourceInventory" class="form-control" min="1" placeholder="Source inv row # (required when multiple no-bin lines — from list above)">
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
        const scanLookupUrl = @json(route('wms.data.scan.lookup'));
        let html5Qr = null;
        /** Prevents duplicate decode callbacks before stop() finishes */
        let camDecodeHandled = false;
        let camLastBarcodeAt = 0;

        function wmsBarcodeFormatsToSupport() {
            if (typeof Html5QrcodeSupportedFormats === 'undefined') return null;
            return [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.CODE_128,
            ];
        }

        async function stopCamScanner() {
            if (!html5Qr) return;
            try {
                await html5Qr.stop();
            } catch (e) { /* not running */ }
            try {
                html5Qr.clear();
            } catch (e) { /* ignore */ }
        }

        /**
         * Camera path: uses Html5Qrcode decodedText only (encoded barcode payload), then WMS scan API.
         */
        async function sendScannedBarcodeToBackend(decodedText) {
            const barcodeValue = String(decodedText || '').trim();
            if (!barcodeValue) return;
            console.info('[WMS scan] barcode (decoded):', barcodeValue);
            scanInput.value = barcodeValue;
            await lookup();
        }

        async function lookup() {
            const code = scanInput.value.trim();
            if (!code) return;
            try {
                const data = await wmsJson(scanLookupUrl + '?code=' + encodeURIComponent(code));
                document.getElementById('result').classList.remove('d-none');
                document.getElementById('pTitle').textContent = data.product.title;
                document.getElementById('pSku').textContent = data.product.sku;
                document.getElementById('pBc').textContent = data.product.barcode || '—';
                document.getElementById('pId').value = data.product.id;
                const srcInvEl = document.getElementById('actSourceInventory');
                if (srcInvEl) srcInvEl.value = '';
                const ul = document.getElementById('lines');
                ul.innerHTML = '';
                (data.lines || []).forEach(l => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item border-start border-4 border-info bg-light';
                    const bid = l.bin_id ? ' · bin #' + l.bin_id : '';
                    const wh = (l.warehouse || '').trim();
                    const soldVal = (l.sold !== null && l.sold !== undefined) ? l.sold : (data.shopify && data.shopify.sold != null ? data.shopify.sold : null);
                    const soldStr = soldVal !== null && soldVal !== undefined ? String(soldVal) : '—';
                    const invNote = data.shopify ? 'inv = stock, quantity col = sold' : 'sync shopify_skus for this SKU';
                    li.innerHTML = '<div><strong>' + (l.full_path || 'Shopify') + '</strong>' + bid + '</div><div class="small">' +
                        (wh ? wh + ' · ' : '') + 'inv (stock) <strong>' + l.on_hand + '</strong> · sold <strong>' + soldStr + '</strong>' +
                        ' <span class="text-muted">(' + invNote + ')</span></div>';
                    ul.appendChild(li);
                });
                const whUl = document.getElementById('warehouseLines');
                whUl.innerHTML = '';
                const whRows = data.warehouse_lines || [];
                if (whRows.length === 0) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item small text-muted';
                    li.textContent = 'No rows in inventories for this SKU. GRN into a bin to create warehouse stock.';
                    whUl.appendChild(li);
                } else {
                    whRows.forEach(function (w) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        const bid = w.bin_id ? 'bin #' + w.bin_id : null;
                        const path = w.full_path || (bid ? '' : 'No bin');
                        const head = bid ? ('<strong>' + path + '</strong> · ' + bid) : '<strong>No bin</strong> <span class="text-muted">(leave From bin empty)</span>';
                        const whn = (w.warehouse || '').trim();
                        const whId = (w.warehouse_id != null && w.warehouse_id !== '') ? ' · warehouse ID <code>' + w.warehouse_id + '</code>' : '';
                        li.innerHTML = '<div>' + head + '</div><div class="small">' + (whn ? whn + ' · ' : '') +
                            'on hand ' + w.on_hand + ' · avail ' + w.available + whId + (w.inventory_id ? ' · inv row #' + w.inventory_id : '') + '</div>';
                        if (!w.bin_id && w.inventory_id) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-sm btn-outline-secondary mt-1';
                            btn.textContent = 'Use this row (no-bin)';
                            btn.addEventListener('click', function () {
                                if (srcInvEl) srcInvEl.value = String(w.inventory_id);
                                const whEl = document.getElementById('actFromWarehouse');
                                if (whEl && w.warehouse_id != null && w.warehouse_id !== '') whEl.value = String(w.warehouse_id);
                            });
                            li.appendChild(btn);
                        }
                        whUl.appendChild(li);
                    });
                }
            } catch (e) {
                const msg = (e.payload && e.payload.message) ? e.payload.message : (e.message || 'Lookup failed');
                alert(msg);
                document.getElementById('result').classList.add('d-none');
            }
        }

        document.getElementById('btnLookup').addEventListener('click', lookup);
        scanInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); lookup(); } });

        document.getElementById('btnCam').addEventListener('click', async () => {
            const region = document.getElementById('camRegion');
            const statusEl = document.getElementById('camScanStatus');
            const btnCam = document.getElementById('btnCam');

            if (region.dataset.scanning === '1') {
                region.dataset.scanning = '0';
                camDecodeHandled = false;
                await stopCamScanner();
                region.style.display = 'none';
                if (statusEl) statusEl.textContent = '';
                btnCam.textContent = 'Use camera (mobile)';
                return;
            }

            if (typeof Html5Qrcode === 'undefined') {
                alert('Barcode scanner library failed to load. Refresh the page.');
                return;
            }

            region.style.display = 'block';
            region.dataset.scanning = '1';
            camDecodeHandled = false;
            btnCam.textContent = 'Stop camera';
            if (statusEl) statusEl.textContent = 'Starting camera…';

            if (!html5Qr) html5Qr = new Html5Qrcode('camRegion');

            const formats = wmsBarcodeFormatsToSupport();
            const config = {
                fps: 10,
                qrbox: function (viewfinderWidth, viewfinderHeight) {
                    const w = Math.min(320, Math.floor(viewfinderWidth * 0.92));
                    const h = Math.min(200, Math.floor(viewfinderHeight * 0.38));
                    return { width: w, height: h };
                },
            };
            if (formats) config.formatsToSupport = formats;

            try {
                await html5Qr.start(
                    { facingMode: 'environment' },
                    config,
                    async function (decodedText /* , decodedResult */) {
                        const barcodeValue = String(decodedText || '').trim();
                        if (!barcodeValue || camDecodeHandled) return;
                        const now = Date.now();
                        if (now - camLastBarcodeAt < 400) return;
                        camLastBarcodeAt = now;
                        camDecodeHandled = true;

                        try {
                            await stopCamScanner();
                            region.dataset.scanning = '0';
                            region.style.display = 'none';
                            btnCam.textContent = 'Use camera (mobile)';
                            if (statusEl) statusEl.textContent = '';
                            await sendScannedBarcodeToBackend(barcodeValue);
                        } catch (e) {
                            const msg = (e.payload && e.payload.message) ? e.payload.message : (e.message || 'Lookup failed');
                            alert(msg);
                            document.getElementById('result').classList.add('d-none');
                        } finally {
                            camDecodeHandled = false;
                        }
                    },
                    function () { /* per-frame; no OCR */ }
                );
                if (statusEl) {
                    statusEl.textContent = 'Point the camera at the bars (EAN-13, EAN-8, or Code 128). Human-readable text under the barcode is ignored.';
                }
            } catch (err) {
                region.dataset.scanning = '0';
                region.style.display = 'none';
                btnCam.textContent = 'Use camera (mobile)';
                if (statusEl) statusEl.textContent = '';
                await stopCamScanner();
                alert('Camera error: ' + (err && err.message ? err.message : err));
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
            const fromWh = parseBinField(document.getElementById('actFromWarehouse'));
            const sourceInv = parseBinField(document.getElementById('actSourceInventory'));
            const body = { product_id: parseInt(pid, 10), type, qty };
            if (type === 'GRN') {
                if (!toBin) { alert('GRN needs a valid to-bin ID (number ≥ 1).'); return; }
                body.to_bin_id = toBin;
            }
            if (type === 'DISPATCH') {
                if (fromBin) body.from_bin_id = fromBin;
                else {
                    if (fromWh) body.from_warehouse_id = fromWh;
                    if (sourceInv) body.source_inventory_id = sourceInv;
                }
            }
            if (type === 'PUTAWAY') {
                if (!toBin) { alert('Putaway needs a valid to-bin ID.'); return; }
                if (fromBin) body.from_bin_id = fromBin;
                else {
                    if (fromWh) body.from_warehouse_id = fromWh;
                    if (sourceInv) body.source_inventory_id = sourceInv;
                }
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
