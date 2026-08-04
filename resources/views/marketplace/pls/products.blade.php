@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Manager</a>
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Shopify PLS Listings', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">
            Pricing rows from <code>pls_products</code>.
            Catalog variants (store=pls): <strong>{{ number_format($catalogCount ?? 0) }}</strong>.
        </p>

        @include('marketplace.pls._nav', ['active' => 'products'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $products->total() }} SKU(s)</span>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-sync-catalog">
                        <i class="ri-refresh-line"></i> Sync catalog
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btn-refresh-pricing">
                        <i class="ri-database-2-line"></i> Refresh pricing
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label small mb-0">Search SKU</label>
                            <input type="text" name="search_sku" class="form-control form-control-sm" value="{{ $searchSku }}" style="min-width:160px;">
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Search</button></div>
                    </div>
                </form>
                <div id="sync-status" class="small text-muted mb-2"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Price</th>
                                <th>L30</th>
                                <th>L60</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $p)
                                <tr>
                                    <td><code>{{ $p->sku }}</code></td>
                                    <td>{{ is_numeric($p->price) ? number_format((float) $p->price, 2) : '—' }}</td>
                                    <td>{{ $p->p_l30 ?? '—' }}</td>
                                    <td>{{ $p->p_l60 ?? '—' }}</td>
                                    <td class="small">{{ $p->updated_at ? $p->updated_at->format('M d, Y H:i') : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-4">No rows. Sync catalog, then Refresh pricing.</td></tr>
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
(function () {
    const out = document.getElementById('sync-status');
    const csrf = '{{ csrf_token() }}';
    async function run(btn, url, label) {
        btn.disabled = true;
        out.className = 'small text-muted mb-2';
        out.textContent = label;
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
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
    }
    document.getElementById('btn-sync-catalog')?.addEventListener('click', function () {
        run(this, @json(route('marketplace.manager.pls.refresh.products')), 'Syncing catalog…');
    });
    document.getElementById('btn-refresh-pricing')?.addEventListener('click', function () {
        run(this, @json(route('marketplace.manager.pls.refresh.pricing')), 'Refreshing pricing…');
    });
})();
</script>
@endsection
