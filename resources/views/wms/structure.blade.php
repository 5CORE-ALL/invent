@extends('layouts.vertical', ['title' => 'WMS — Structure', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><strong>Warehouse</strong></div>
                <div class="card-body">
                    <select id="whSelect" class="form-select form-select-lg mb-2">
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" data-code="{{ $w->code }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label small text-muted">Short code (e.g. WH1)</label>
                    <input type="text" id="whCode" class="form-control mb-2" maxlength="32" placeholder="WH1">
                    <button type="button" class="btn btn-primary w-100" id="btnSaveWh">Save warehouse code</button>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Zones</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-5"><input type="text" id="zoneName" class="form-control" placeholder="Zone name"></div>
                        <div class="col-md-4"><input type="text" id="zoneCode" class="form-control" placeholder="Code e.g. Z1"></div>
                        <div class="col-md-3"><button type="button" class="btn btn-success w-100" id="btnAddZone">Add zone</button></div>
                    </div>
                    <ul class="list-group" id="zoneList"></ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Cascade</strong> — Zone → Rack → Shelf → Bin</div>
        <div class="card-body">
            <div class="row g-2 mb-2">
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Zone</label>
                    <select id="cZone" class="form-select"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Rack</label>
                    <select id="cRack" class="form-select"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Shelf</label>
                    <select id="cShelf" class="form-select"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-0">Bin</label>
                    <select id="cBin" class="form-select"></select>
                </div>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0">New rack</label>
                    <input type="text" id="rackName" class="form-control" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <input type="text" id="rackCode" class="form-control" placeholder="R1">
                </div>
                <div class="col-md-2">
                    <input type="number" id="rackPri" class="form-control" placeholder="Priority" value="10" min="0">
                </div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100" id="btnAddRack">+ Rack</button></div>
            </div>
            <hr>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">New shelf</label>
                    <input type="text" id="shelfName" class="form-control" placeholder="Name">
                </div>
                <div class="col-md-3">
                    <input type="text" id="shelfCode" class="form-control" placeholder="S1">
                </div>
                <div class="col-md-3"><button type="button" class="btn btn-outline-primary w-100" id="btnAddShelf">+ Shelf</button></div>
            </div>
            <hr>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-0">New bin</label>
                    <input type="text" id="binName" class="form-control" placeholder="Name">
                </div>
                <div class="col-md-2">
                    <input type="text" id="binCode" class="form-control" placeholder="B1">
                </div>
                <div class="col-md-2">
                    <input type="number" id="binCap" class="form-control" placeholder="Capacity" min="0">
                </div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-success w-100" id="btnAddBin">+ Bin</button></div>
            </div>
            <p class="small text-muted mt-3 mb-0" id="fullPathPreview"></p>
        </div>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script>
        const U = {
            wh: (id) => '/wms/data/warehouse/' + id,
            zones: '/wms/data/zones',
            zone: (id) => '/wms/data/zones/' + id,
            racks: '/wms/data/racks',
            rack: (id) => '/wms/data/racks/' + id,
            shelves: '/wms/data/shelves',
            shelf: (id) => '/wms/data/shelves/' + id,
            bins: '/wms/data/bins',
            bin: (id) => '/wms/data/bins/' + id,
            zbw: (wid) => '/wms/data/zones-by-warehouse/' + wid,
            rbz: (zid) => '/wms/data/racks-by-zone/' + zid,
            sbr: (rid) => '/wms/data/shelves-by-rack/' + rid,
            bbs: (sid) => '/wms/data/bins-by-shelf/' + sid,
        };

        const whSelect = document.getElementById('whSelect');
        const whCode = document.getElementById('whCode');

        function currentWh() { return parseInt(whSelect.value, 10); }

        function fillSelect(el, rows, labelFn, valueKey = 'id') {
            el.innerHTML = '<option value="">—</option>';
            rows.forEach(r => {
                const o = document.createElement('option');
                o.value = r[valueKey];
                o.textContent = labelFn(r);
                el.appendChild(o);
            });
        }

        async function loadZones() {
            const data = await wmsJson(U.zbw(currentWh()));
            const list = document.getElementById('zoneList');
            list.innerHTML = '';
            data.data.forEach(z => {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                li.innerHTML = '<span><strong>' + z.name + '</strong> <code>' + z.code + '</code></span>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" data-del-zone="' + z.id + '">Delete</button>';
                list.appendChild(li);
            });
            fillSelect(document.getElementById('cZone'), data.data, z => z.name + ' (' + z.code + ')');
            document.getElementById('cRack').innerHTML = '';
            document.getElementById('cShelf').innerHTML = '';
            document.getElementById('cBin').innerHTML = '';
            updatePathPreview();
        }

        async function loadRacks(zoneId) {
            if (!zoneId) return;
            const data = await wmsJson(U.rbz(zoneId));
            fillSelect(document.getElementById('cRack'), data.data, r => r.name + ' (' + r.code + ') p' + r.pick_priority);
            document.getElementById('cShelf').innerHTML = '';
            document.getElementById('cBin').innerHTML = '';
        }

        async function loadShelves(rackId) {
            if (!rackId) return;
            const data = await wmsJson(U.sbr(rackId));
            fillSelect(document.getElementById('cShelf'), data.data, s => s.name + ' (' + s.code + ')');
            document.getElementById('cBin').innerHTML = '';
        }

        async function loadBins(shelfId) {
            if (!shelfId) return;
            const data = await wmsJson(U.bbs(shelfId));
            fillSelect(document.getElementById('cBin'), data.data, b => b.name + ' — ' + (b.full_location_code || b.code));
            updatePathPreview();
        }

        function updatePathPreview() {
            const opt = document.getElementById('cBin').selectedOptions[0];
            const p = document.getElementById('fullPathPreview');
            p.textContent = opt && opt.textContent && opt.value ? 'Selected: ' + opt.textContent : 'Select a bin to see full path.';
        }

        whSelect.addEventListener('change', () => {
            const opt = whSelect.selectedOptions[0];
            whCode.value = opt.getAttribute('data-code') || '';
            loadZones().catch(e => alert(e.message));
        });

        document.getElementById('btnSaveWh').addEventListener('click', async () => {
            try {
                await wmsJson(U.wh(currentWh()), { method: 'PUT', body: { code: whCode.value || null } });
                whSelect.selectedOptions[0].setAttribute('data-code', whCode.value);
                alert('Saved');
            } catch (e) { alert(e.message); }
        });

        document.getElementById('btnAddZone').addEventListener('click', async () => {
            try {
                await wmsJson(U.zones, { method: 'POST', body: {
                    warehouse_id: currentWh(),
                    name: document.getElementById('zoneName').value,
                    code: document.getElementById('zoneCode').value,
                }});
                document.getElementById('zoneName').value = '';
                document.getElementById('zoneCode').value = '';
                await loadZones();
            } catch (e) { alert(e.message); }
        });

        document.getElementById('zoneList').addEventListener('click', async (ev) => {
            const id = ev.target.getAttribute('data-del-zone');
            if (!id || !confirm('Delete zone and all children?')) return;
            try {
                await wmsJson(U.zone(id), { method: 'DELETE' });
                await loadZones();
            } catch (e) { alert(e.message); }
        });

        document.getElementById('cZone').addEventListener('change', () => loadRacks(document.getElementById('cZone').value).catch(e => alert(e.message)));
        document.getElementById('cRack').addEventListener('change', () => loadShelves(document.getElementById('cRack').value).catch(e => alert(e.message)));
        document.getElementById('cShelf').addEventListener('change', () => loadBins(document.getElementById('cShelf').value).catch(e => alert(e.message)));
        document.getElementById('cBin').addEventListener('change', updatePathPreview);

        document.getElementById('btnAddRack').addEventListener('click', async () => {
            const zid = document.getElementById('cZone').value;
            if (!zid) return alert('Select a zone');
            try {
                await wmsJson(U.racks, { method: 'POST', body: {
                    zone_id: parseInt(zid, 10),
                    name: document.getElementById('rackName').value,
                    code: document.getElementById('rackCode').value,
                    pick_priority: parseInt(document.getElementById('rackPri').value, 10) || 10,
                }});
                await loadRacks(zid);
            } catch (e) { alert(e.message); }
        });

        document.getElementById('btnAddShelf').addEventListener('click', async () => {
            const rid = document.getElementById('cRack').value;
            if (!rid) return alert('Select a rack');
            try {
                await wmsJson(U.shelves, { method: 'POST', body: {
                    rack_id: parseInt(rid, 10),
                    name: document.getElementById('shelfName').value,
                    code: document.getElementById('shelfCode').value,
                }});
                await loadShelves(rid);
            } catch (e) { alert(e.message); }
        });

        document.getElementById('btnAddBin').addEventListener('click', async () => {
            const sid = document.getElementById('cShelf').value;
            if (!sid) return alert('Select a shelf');
            const cap = document.getElementById('binCap').value;
            try {
                await wmsJson(U.bins, { method: 'POST', body: {
                    shelf_id: parseInt(sid, 10),
                    name: document.getElementById('binName').value,
                    code: document.getElementById('binCode').value,
                    capacity: cap ? parseInt(cap, 10) : null,
                }});
                await loadBins(sid);
            } catch (e) { alert(e.message); }
        });

        whCode.value = whSelect.selectedOptions[0].getAttribute('data-code') || '';
        loadZones().catch(e => alert(e.message));
    </script>
@endsection
