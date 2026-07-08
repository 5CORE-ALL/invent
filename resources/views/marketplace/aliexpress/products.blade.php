@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> AliExpress Manager</a>
        <h4 class="mt-2 mb-1">AliExpress Listings</h4>
        <p class="text-muted mb-3">Shopify (source) ↔ AliExpress listing map. Compare price, quantity, and link status.</p>

        @include('marketplace.aliexpress._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $products->total() }} listings</span>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
                        <i class="ri-refresh-line"></i> Sync from AliExpress API
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end flex-wrap">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search name</label>
                            <input type="text" name="search_name" class="form-control form-control-sm" value="{{ $searchName }}" placeholder="Title or SKU" style="min-width: 160px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search SKU</label>
                            <input type="text" name="search_sku" class="form-control form-control-sm" value="{{ $searchSku }}" placeholder="SKU" style="min-width: 120px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Source</label>
                            <select name="source" class="form-select form-select-sm">
                                <option value="db" {{ ($source ?? 'db') === 'db' ? 'selected' : '' }}>Local DB</option>
                                <option value="api" {{ ($source ?? '') === 'api' ? 'selected' : '' }}>Live API</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Search</button>
                            <a href="{{ request()->url() }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 64px;">Image</th>
                                <th>SKU</th>
                                <th>Title (Shopify)</th>
                                <th>AliExpress ID</th>
                                <th>Shopify Qty</th>
                                <th>AE Qty</th>
                                <th>Shopify Price</th>
                                <th>AE Price</th>
                                <th>Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                <tr>
                                    <td>
                                        @if(!empty($p->image_src))
                                            <img src="{{ $p->image_src }}" alt="" class="img-thumbnail" style="max-width: 48px; max-height: 48px; object-fit: contain;">
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $p->sku }}</code></td>
                                    <td>{{ Str::limit($p->title ?? '—', 50) }}</td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td>{{ $p->shopify_quantity ?? '—' }}</td>
                                    <td>{{ $p->quantity ?? '—' }}</td>
                                    <td>{{ isset($p->shopify_price) ? number_format((float)$p->shopify_price, 2) : '—' }}</td>
                                    <td>{{ isset($p->price) ? number_format((float)$p->price, 2) : '—' }}</td>
                                    <td>
                                        @if($p->linked)
                                            <span class="badge bg-success-subtle text-success">Linked</span>
                                        @else
                                            <span class="badge bg-light text-muted">Unlinked</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No listings yet.
                                        @if($connected)
                                            Click <strong>Sync from AliExpress API</strong> or switch source to <strong>Live API</strong>.
                                        @else
                                            <a href="{{ route('marketplace.manager.aliexpress.connect') }}">Connect AliExpress</a> first.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($products->hasPages())
                    <div class="d-flex justify-content-center mt-3">{{ $products->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-refresh-api')?.addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    fetch('{{ route('marketplace.manager.aliexpress.refresh') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ page: {{ (int) request('page', 1) }} }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Done' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});
</script>
@endsection
