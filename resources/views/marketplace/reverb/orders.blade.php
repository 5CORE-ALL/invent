@extends('layouts.vertical', ['title' => $title ?? 'Reverb - Orders', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Reverb Orders</h4>
                    <span class="badge bg-primary">{{ $orders->total() }} orders</span>
                </div>
                <div class="card-body">
                    <p class="text-muted">All Reverb orders. New orders are automatically imported to Shopify when "Import orders to main store" is enabled in Settings. Ensure queue worker is running: <code>php artisan queue:work</code></p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>SKU</th>
                                    <th>Title</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Shopify</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $o)
                                    <tr>
                                        <td>{{ $o->order_number ?? '-' }}</td>
                                        <td>{{ $o->order_date ? \Carbon\Carbon::parse($o->order_date)->format('M d, Y') : '-' }}</td>
                                        <td><span class="badge bg-secondary">{{ $o->status ?? '-' }}</span></td>
                                        <td>{{ $o->sku ?? '-' }}</td>
                                        <td>{{ Str::limit($o->display_sku ?? '-', 40) }}</td>
                                        <td>{{ $o->quantity ?? 1 }}</td>
                                        <td>{{ $o->amount ? number_format($o->amount, 2) : '-' }}</td>
                                        <td>
                                            @if($o->shopify_order_id)
                                                <span class="badge bg-success">Pushed</span>
                                                <small class="d-block">{{ $o->pushed_to_shopify_at?->format('M d, Y') }}</small>
                                            @else
                                                <span class="badge bg-light text-dark">Not pushed</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($o->shopify_order_id)
                                                —
                                            @elseif(($o->import_status ?? '') === 'import_failed')
                                                <button type="button" class="btn btn-sm btn-warning btn-push-order" data-id="{{ $o->id }}">Retry import</button>
                                            @else
                                                <span class="text-muted small">Queued / pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No Reverb orders. Run <code>php artisan reverb:fetch</code> to sync.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($orders->hasPages())
                        <div class="d-flex justify-content-center mt-3">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.btn-push-order').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = this.getAttribute('data-id');
                var row = this.closest('tr');
                var cell = this.closest('td');
                if (!id) return;
                this.disabled = true;
                this.textContent = 'Pushing...';
                fetch('{{ route('marketplace.orders.push', 'reverb') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ id: parseInt(id, 10) })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        cell.innerHTML = '<span class="badge bg-info">Import queued</span>';
                        btn.disabled = true;
                        btn.remove();
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Push to Shopify';
                        alert(data.message || 'Failed to push order.');
                    }
                })
                .catch(function() {
                    btn.disabled = false;
                    btn.textContent = 'Push to Shopify';
                    alert('Request failed.');
                });
            });
        });
    </script>
@endsection
