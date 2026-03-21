@extends('layouts.vertical', ['title' => 'WMS — Smart locate', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('wms._subnav')

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-column flex-md-row gap-2">
            <input type="text" id="q" class="form-control form-control-lg flex-grow-1" placeholder="SKU or barcode">
            <button type="button" class="btn btn-primary btn-lg px-4" id="btnSearch">Search</button>
        </div>
    </div>

    <div id="out" class="d-none">
        <div class="alert alert-info py-2" id="fastFlag" style="display:none;">Fast-moving SKU (high movement last 30 days)</div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 id="title"></h5>
                <p class="mb-0"><code id="sku"></code> · <span id="bc"></span></p>
            </div>
        </div>
        <div class="mt-3" id="locs"></div>
    </div>

    <script src="{{ asset('js/wms-core.js') }}"></script>
    <script>
        async function run() {
            const q = document.getElementById('q').value.trim();
            if (!q) return;
            const data = await wmsJson('/wms/data/locate?q=' + encodeURIComponent(q));
            if (!data.product) {
                alert('No product');
                document.getElementById('out').classList.add('d-none');
                return;
            }
            document.getElementById('out').classList.remove('d-none');
            document.getElementById('title').textContent = data.product.title;
            document.getElementById('sku').textContent = data.product.sku;
            document.getElementById('bc').textContent = data.product.barcode || '—';
            const ff = document.getElementById('fastFlag');
            ff.style.display = data.is_fast_moving ? 'block' : 'none';
            const locs = document.getElementById('locs');
            locs.innerHTML = '';
            if (!data.locations.length) {
                locs.innerHTML = '<p class="text-muted">No bin assignments yet.</p>';
                return;
            }
            data.locations.forEach(l => {
                const card = document.createElement('div');
                card.className = 'card border-0 shadow-sm mb-2';
                const h = l.hierarchy || {};
                const crumb = [h.warehouse, h.zone, h.rack, h.shelf, h.bin].filter(Boolean).join(' › ');
                card.innerHTML = '<div class="card-body"><div class="fw-semibold">' + (l.full_path || '') + '</div>' +
                    '<div class="small text-muted">' + crumb + '</div>' +
                    '<div class="mt-1">On hand: ' + l.on_hand + ' · Available: ' + l.available + '</div></div>';
                locs.appendChild(card);
            });
        }
        document.getElementById('btnSearch').addEventListener('click', () => run().catch(e => alert(e.message)));
        document.getElementById('q').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); run().catch(err => alert(err.message)); }});
    </script>
@endsection
