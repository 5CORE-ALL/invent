@extends('layouts.vertical', ['title' => $title ?? 'TikTok 2 — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'tiktok2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok 2 Manager</a>
        @include('marketplace._page-heading', ['slug' => 'tiktok2', 'heading' => 'TikTok 2 Orders', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">Order lines from <code>tiktok2_orders</code>. Orders are auto-imported to Shopify when enabled in Settings.</p>

        @include('marketplace.tiktok2._nav', ['active' => 'orders'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $orders->total() }} line(s)</span>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="60" selected>Last 60 days</option>
                        <option value="90">Last 90 days</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch from TikTok 2
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="get" class="mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <input type="text" name="q" class="form-control form-control-sm" value="{{ $search }}" placeholder="Order / SKU / product" style="min-width:220px;">
                        </div>
                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Search</button></div>
                    </div>
                </form>
                <div id="fetch-status" class="small text-muted mb-2"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Sale price</th>
                                <th>Shopify</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                @php $url = route('marketplace.orders.show', ['marketplace' => 'tiktok2', 'order' => $o->id]); @endphp
                                <tr style="cursor:pointer" onclick="window.location='{{ $url }}'">
                                    <td><a href="{{ $url }}" onclick="event.stopPropagation()">{{ $o->order_id }}</a></td>
                                    <td class="small">{{ $o->order_created_at ? $o->order_created_at->format('M d, Y H:i') : '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $o->order_status ?? '—' }}</span></td>
                                    <td><code>{{ $o->seller_sku ?? '—' }}</code></td>
                                    <td>{{ \Illuminate\Support\Str::limit($o->product_name ?? '—', 40) }}</td>
                                    <td>{{ $o->quantity ?? 1 }}</td>
                                    <td>{{ is_numeric($o->sale_price) ? number_format((float) $o->sale_price, 2) : '—' }}</td>
                                    <td onclick="event.stopPropagation()">
                                        @if($o->shopify_order_id)
                                            <span class="badge bg-success" title="Imported">✓ #{{ $o->shopify_order_id }}</span>
                                        @elseif(($o->import_status ?? '') === 'queued')
                                            <span class="badge bg-info">Queued</span>
                                        @elseif(($o->import_status ?? '') === 'import_failed')
                                            <button class="btn btn-xs btn-outline-danger btn-push-order" data-id="{{ $o->id }}" title="Retry push">Retry</button>
                                        @else
                                            <button class="btn btn-xs btn-outline-primary btn-push-order" data-id="{{ $o->id }}">Push</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-muted text-center py-4">No orders. Click Fetch from TikTok 2.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $orders->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
document.querySelectorAll('.btn-push-order').forEach(btn => {
    btn.addEventListener('click', async function (e) {
        e.preventDefault();
        const id = this.dataset.id;
        this.disabled = true;
        this.textContent = '…';
        try {
            const res = await fetch('/marketplace-manager/tiktok2/push-order/' + id, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                this.outerHTML = '<span class="badge bg-success">✓ #' + (data.shopify_order_id || '') + '</span>';
            } else {
                this.textContent = 'Failed';
                this.classList.add('btn-danger');
                this.title = data.message || 'Push failed';
            }
        } catch (err) {
            this.textContent = 'Error';
            this.disabled = false;
        }
    });
});

document.getElementById('btn-fetch-orders')?.addEventListener('click', async function () {
    const btn = this;
    const out = document.getElementById('fetch-status');
    const days = parseInt(document.getElementById('fetch-days').value || '60', 10);
    btn.disabled = true;
    out.textContent = 'Fetching orders…';
    out.className = 'small text-muted mb-2';
    try {
        const res = await fetch(@json(route('marketplace.manager.tiktok2.fetch.orders')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ days, import: true }),
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
