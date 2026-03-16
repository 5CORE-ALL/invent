@extends('layouts.vertical', ['title' => 'Reverb Import Fallback Stats', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    <div class="container-fluid">
        <h4 class="mb-3">Reverb → Shopify import fallback stats</h4>
        <p class="text-muted">
            Every Reverb order is created in Shopify. When variant/SKU or API issues occur, orders are created as custom line items or stored for retry.
            No order fails silently – each has a clear reason in <code>last_error</code>.
        </p>

        @php
            $pendingCount = $pendingCount ?? \App\Models\PendingShopifyOrder::count();
            $pendingOrders = $pendingOrders ?? collect();
        @endphp

        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Pending Shopify orders</h5>
                <p class="mb-2">
                    <strong>{{ $pendingCount }}</strong> order(s) waiting for Shopify (stored in <code>pending_shopify_orders</code>).
                </p>
                <p class="text-muted small mb-0">
                    Retry: <code>php artisan shopify:retry-pending-orders</code> or <code>php artisan shopify:retry-pending-orders --custom-only</code> to force custom line items.
                </p>
            </div>
        </div>

        @if(isset($pendingOrders) && $pendingOrders->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pending orders with reasons</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>SKU</th>
                                <th>Attempts</th>
                                <th>Last attempt</th>
                                <th>Reason (last_error)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingOrders as $p)
                                <tr>
                                    <td>{{ $p->order_data['order_number'] ?? $p->reverb_order_metric_id }}</td>
                                    <td>{{ $p->order_data['sku'] ?? '-' }}</td>
                                    <td>{{ $p->attempts }}</td>
                                    <td>{{ $p->last_attempt_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td class="text-break" title="{{ $p->last_error }}">{{ Str::limit($p->last_error ?? '-', 120) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Log search hints</h5>
                <p class="text-muted small mb-0">
                    Search <code>storage/logs/laravel.log</code> for: <code>ReverbOrderPushService: fallback=</code>, <code>createOrderWithCustomItem</code>, <code>ImportReverbOrderToShopify: stored in pending</code>, <code>RetryPendingShopifyOrders</code>.
                </p>
            </div>
        </div>
    </div>
@endsection
