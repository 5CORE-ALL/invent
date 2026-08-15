@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Product', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Listings</a>
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => $sku, 'mb' => 'mb-3'])
        @include('marketplace.pls._nav', ['active' => 'products'])

        @php
            $inventoryMismatch = $linked
                && $shopifyQty !== null
                && $plsQty !== null
                && (int) $shopifyQty !== (int) $plsQty;
        @endphp

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    @if($linked)
                        <span class="badge bg-success-subtle text-success">Linked on PLS</span>
                    @else
                        <span class="badge bg-light text-muted">Not linked</span>
                    @endif
                    @if(!empty($plsState))
                        <span class="badge bg-secondary-subtle text-secondary">{{ $plsState }}</span>
                    @endif
                </span>
                <div class="d-flex gap-2">
                    @if($connected)
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-pull-pls" data-id="{{ $shopifySkuId }}">
                            <i class="ri-download-cloud-line"></i> Pull from PLS
                        </button>
                    @endif
                    @if($linked)
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-inventory" data-id="{{ $shopifySkuId }}">
                            <i class="ri-upload-2-line"></i> Push inventory now
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if(!empty($inventoryMismatch))
                    <div class="alert alert-warning py-2 small mb-3">
                        Inventory mismatch: Shopify <strong>{{ (int) $shopifyQty }}</strong> vs PLS <strong>{{ (int) $plsQty }}</strong>.
                        Click <strong>Push inventory now</strong> to set PLS qty from B2C Shopify.
                    </div>
                @endif

                <div class="row g-3">
                    <div class="col-md-6">
                        <h6>Shopify (B2C)</h6>
                        <table class="table table-sm table-bordered">
                            <tr><th>SKU</th><td><code>{{ $sku }}</code></td></tr>
                            <tr><th>Title</th><td>{{ $shopify->product_title ?? '—' }} {{ $shopify->variant_title ? '— '.$shopify->variant_title : '' }}</td></tr>
                            <tr><th>Qty</th><td>{{ $shopifyQty !== null ? $shopifyQty : '—' }}</td></tr>
                            <tr><th>Price</th><td>{{ isset($shopify->b2c_price) || isset($shopify->price) ? number_format((float)($shopify->b2c_price ?? $shopify->price), 2) : '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Shopify PLS</h6>
                        <table class="table table-sm table-bordered">
                            <tr><th>Product ID</th><td class="small">{{ $metric->product_id ?? '—' }}</td></tr>
                            <tr><th>Variant ID</th><td class="small">{{ $metric->sku_id ?? '—' }}</td></tr>
                            <tr><th>Title</th><td>{{ $metric->title ?? '—' }}</td></tr>
                            <tr><th>Qty</th><td>{{ $plsQty !== null ? $plsQty : '—' }}</td></tr>
                            <tr><th>Price</th><td>{{ isset($metric->price) ? number_format((float)$metric->price, 2) : '—' }}</td></tr>
                        </table>
                    </div>
                </div>
                <div id="push-status" class="small text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('btn-pull-pls')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    var out = document.getElementById('push-status');
    btn.disabled = true;
    out.textContent = 'Pulling from PLS…';
    fetch('{{ url('marketplace/pls/products') }}/' + id + '/pull', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    }).then(function (r) { return r.json(); }).then(function (data) {
        out.className = 'small mt-2 ' + (data.success ? 'text-success' : 'text-danger');
        out.textContent = data.message || (data.success ? 'Done' : 'Failed');
        if (data.success) setTimeout(function () { location.reload(); }, 800);
        else btn.disabled = false;
    }).catch(function (e) {
        out.className = 'small mt-2 text-danger';
        out.textContent = e.message || 'Request failed';
        btn.disabled = false;
    });
});

document.getElementById('btn-sync-inventory')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    var out = document.getElementById('push-status');
    if (!confirm('Push live Shopify quantity to PLS for this SKU now (no queue)?')) return;
    btn.disabled = true;
    out.textContent = 'Pushing inventory…';
    fetch('{{ url('marketplace/pls/products') }}/' + id + '/sync-inventory', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    }).then(function (r) { return r.json(); }).then(function (data) {
        out.className = 'small mt-2 ' + (data.success ? 'text-success' : 'text-danger');
        out.textContent = data.message || (data.success ? 'Done' : 'Failed');
        if (data.success) setTimeout(function () { location.reload(); }, 800);
        else btn.disabled = false;
    }).catch(function (e) {
        out.className = 'small mt-2 text-danger';
        out.textContent = e.message || 'Request failed';
        btn.disabled = false;
    });
});
</script>
@endsection
