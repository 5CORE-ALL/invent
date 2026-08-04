@extends('layouts.vertical', ['title' => $title ?? 'TikTok Shop — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'tiktok') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok Shop Manager</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok', 'heading' => 'TikTok Shop Listings', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">Listings from <code>tiktok_products</code> (Shop API sync).</p>

        @include('marketplace.tiktok._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $products->total() }} SKU(s)</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn-refresh-api">
                    <i class="ri-refresh-line"></i> Sync products from API
                </button>
            </div>
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search SKU</label>
                            <input type="text" name="search_sku" class="form-control form-control-sm" value="{{ $searchSku }}" style="min-width:140px;">
                        </div>
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search</label>
                            <input type="text" name="search_name" class="form-control form-control-sm" value="{{ $searchName }}" placeholder="SKU / product id" style="min-width:160px;">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Search</button>
                        </div>
                    </div>
                </form>
                <div id="sync-status" class="small text-muted mb-2"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product ID</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Sold</th>
                                <th>Views</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                <tr>
                                    <td><code>{{ $p->sku }}</code></td>
                                    <td class="small">{{ $p->product_id ?? '—' }}</td>
                                    <td>{{ is_numeric($p->price) ? number_format((float) $p->price, 2) : '—' }}</td>
                                    <td>{{ $p->stock ?? '—' }}</td>
                                    <td>{{ $p->sold ?? '—' }}</td>
                                    <td>{{ $p->views ?? '—' }}</td>
                                    <td class="small">{{ $p->updated_at ? $p->updated_at->format('M d, Y H:i') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center py-4">No products. Click Sync products from API.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $products->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.getElementById('btn-refresh-api')?.addEventListener('click', async function () {
    const btn = this;
    const out = document.getElementById('sync-status');
    btn.disabled = true;
    out.textContent = 'Syncing products… this can take several minutes.';
    out.className = 'small text-muted mb-2';
    try {
        const res = await fetch(@json(route('marketplace.manager.tiktok.refresh')), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        });
        const data = await res.json();
        out.className = 'small mb-2 ' + (data.success ? 'text-success' : 'text-danger');
        out.textContent = data.message || (data.success ? 'Done' : 'Failed');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (e) {
        out.className = 'small text-danger mb-2';
        out.textContent = e.message || 'Request failed';
    } finally {
        btn.disabled = false;
    }
});
</script>
@endsection
