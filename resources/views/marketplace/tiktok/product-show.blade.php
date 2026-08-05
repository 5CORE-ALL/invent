@extends('layouts.vertical', ['title' => $title ?? 'TikTok Shop — Product', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'tiktok') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok Shop Listings</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok', 'heading' => $sku, 'mb' => 'mb-3'])
        @include('marketplace.tiktok._nav', ['active' => 'products'])

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    @if($linked)
                        <span class="badge bg-success-subtle text-success">Linked</span>
                    @else
                        <span class="badge bg-light text-muted">Not linked</span>
                    @endif
                </span>
                <div class="d-flex gap-2">
                    @if($linked)
                        <button type="button" class="btn btn-sm btn-warning" id="btn-sync-inventory" data-id="{{ $shopifySkuId }}">
                            <i class="ri-upload-2-line"></i> Push inventory now
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6>Shopify</h6>
                        <table class="table table-sm table-bordered">
                            <tr><th>SKU</th><td><code>{{ $sku }}</code></td></tr>
                            <tr><th>Title</th><td>{{ $shopify->product_title ?? '—' }} {{ $shopify->variant_title ? '— '.$shopify->variant_title : '' }}</td></tr>
                            <tr><th>Qty</th><td>{{ $shopifyQty !== null ? $shopifyQty : '—' }}</td></tr>
                            <tr><th>Price</th><td>{{ isset($shopify->b2c_price) || isset($shopify->price) ? number_format((float)($shopify->b2c_price ?? $shopify->price), 2) : '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>TikTok Shop</h6>
                        <table class="table table-sm table-bordered">
                            <tr><th>Product ID</th><td class="small">{{ $metric->product_id ?? '—' }}</td></tr>
                            <tr><th>SKU ID</th><td class="small">{{ $metric->sku_id ?? '—' }}</td></tr>
                            <tr><th>Qty</th><td>{{ $tiktokQty !== null ? $tiktokQty : '—' }}</td></tr>
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
document.getElementById('btn-sync-inventory')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    var out = document.getElementById('push-status');
    btn.disabled = true;
    out.textContent = 'Pushing inventory…';
    fetch('{{ url('marketplace/tiktok/products') }}/' + id + '/sync-inventory', {
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
