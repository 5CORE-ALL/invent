@extends('layouts.vertical', ['title' => $title ?? 'Shopify PLS — Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.manager.show', 'pls') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Shopify PLS Manager</a>
        @include('marketplace._page-heading', ['slug' => 'pls', 'heading' => 'Shopify PLS Orders / Sales', 'mb' => 'mb-3'])
        <p class="text-muted mb-3">Sales lines from <code>pls_sales</code> (PLS Shopify Admin API).</p>

        @include('marketplace.pls._nav', ['active' => 'orders'])

        @if($apiError)
            <div class="alert alert-warning">{{ $apiError }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="badge bg-primary">{{ $orders->total() }} line(s)</span>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <select id="fetch-days" class="form-select form-select-sm" style="width:auto;">
                        <option value="30">Last 30 days</option>
                        <option value="90" selected>Last 90 days</option>
                        <option value="180">Last 180 days</option>
                        <option value="365">Last 365 days</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-fetch-orders">
                        <i class="ri-download-cloud-line"></i> Fetch sales
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
                                <th>Order</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $o)
                                @php $url = route('marketplace.orders.show', ['marketplace' => 'pls', 'order' => $o->id]); @endphp
                                <tr style="cursor:pointer" onclick="window.location='{{ $url }}'">
                                    <td><a href="{{ $url }}" onclick="event.stopPropagation()">{{ $o->order_name ?: $o->order_number }}</a></td>
                                    <td class="small">{{ $o->order_date ? \Illuminate\Support\Carbon::parse($o->order_date)->format('M d, Y') : '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $o->financial_status ?? '—' }}</span></td>
                                    <td><code>{{ $o->sku ?? '—' }}</code></td>
                                    <td>{{ \Illuminate\Support\Str::limit($o->product_title ?? '—', 40) }}</td>
                                    <td>{{ $o->quantity ?? 1 }}</td>
                                    <td>{{ is_numeric($o->total_amount) ? number_format((float) $o->total_amount, 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-muted text-center py-4">No sales. Click Fetch sales.</td></tr>
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
document.getElementById('btn-fetch-orders')?.addEventListener('click', async function () {
    const btn = this;
    const out = document.getElementById('fetch-status');
    const days = parseInt(document.getElementById('fetch-days').value || '90', 10);
    btn.disabled = true;
    out.textContent = 'Fetching sales…';
    out.className = 'small text-muted mb-2';
    try {
        const res = await fetch(@json(route('marketplace.manager.pls.fetch.orders')), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ days }),
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
