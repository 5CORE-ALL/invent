<div class="crm-tab-partial" data-tab-partial="orders">
    <p class="text-muted small">Orders from Shopify for linked Shopify customer IDs.</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Shopify customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Order date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="font-monospace small">{{ $order->shopify_order_id }}</td>
                        <td class="font-monospace small">{{ $order->shopify_customer_id }}</td>
                        <td class="text-nowrap">{{ $order->total_price }} {{ $order->currency }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $order->order_status ?? '—' }}</span></td>
                        <td class="small text-nowrap">{{ $order->order_date?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">No orders found. Sync Shopify orders or link a Shopify customer.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        <div class="crm-pagination mt-3 d-flex justify-content-center">
            {{ $orders->onEachSide(1)->links() }}
        </div>
    @endif
</div>
