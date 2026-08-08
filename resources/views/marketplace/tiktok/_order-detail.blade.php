@php
    $summary = $detail['summary'] ?? [];
    $amounts = $detail['funds'] ?? [];
    $buyer = $detail['buyer'] ?? [];
    $shipping = $detail['shipping'] ?? [];
    $shipment = $detail['shipment'] ?? [];
    $logistics = $detail['logistics'] ?? [];
    $lineItems = $detail['line_items'] ?? [];
    $shopify = $detail['shopify'] ?? [];
    $payment = $detail['payment'] ?? [];
    $currency = $amounts['currency'] ?? $payment['currency'] ?? '';
    $fmt = function ($value) use ($currency) {
        if ($value === null || $value === '') {
            return '—';
        }

        return ($currency ? $currency.' ' : '').number_format((float) $value, 2);
    };
    $fmtDt = function ($value) {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('M d, Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $slug = $marketplaceSlug ?? 'tiktok';
    $detailTable = 'marketplace.tiktok._detail-table';
@endphp

@if(empty($detail['raw_available']))
    <div class="alert alert-warning py-2 small">
        Limited order payload in cache. Run order sync / address sync so TikTok detail fields (address, payment) can populate.
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">Order summary</div>
            <div class="card-body p-0">
                @include($detailTable, ['showEmpty' => true, 'rows' => [
                    'Order ID' => $summary['order_id'] ?? null,
                    'Status' => $summary['status'] ?? null,
                    'Buyer user ID' => $summary['buyer_user_id'] ?? null,
                    'Created' => $fmtDt($summary['created'] ?? null),
                    'Paid' => $fmtDt($summary['paid'] ?? null),
                    'Updated' => $fmtDt($summary['updated'] ?? null),
                    'RTS time' => $fmtDt($summary['rts_time'] ?? null),
                    'Delivery time' => $fmtDt($summary['delivery_time'] ?? null),
                    'Fulfillment type' => $summary['fulfillment_type'] ?? null,
                    'Delivery type' => $summary['delivery_type'] ?? null,
                    'Shipping type' => $summary['shipping_type'] ?? null,
                    'Delivery option' => $summary['delivery_option'] ?? null,
                    'On hold' => !empty($summary['is_on_hold']) ? 'Yes' : 'No',
                    'Buyer message' => $summary['buyer_message'] ?? null,
                ]])
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">Payment details</div>
            <div class="card-body p-0">
                @include($detailTable, ['showEmpty' => true, 'rows' => [
                    'Total amount paid' => isset($payment['total_paid']) ? $fmt($payment['total_paid']) : null,
                    'Payment time' => $fmtDt($payment['paid_at'] ?? null),
                    'Payment method' => $payment['method'] ?? ($summary['payment_method'] ?? null),
                    'Currency' => $currency ?: null,
                ]])
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Fund details</div>
    <div class="card-body p-0">
        @include($detailTable, ['showEmpty' => true, 'rows' => [
            'Product total' => isset($amounts['product_total']) ? $fmt($amounts['product_total']) : null,
            'Sub total' => isset($amounts['sub_total']) ? $fmt($amounts['sub_total']) : null,
            'Shipping fee' => isset($amounts['shipping_fee']) ? $fmt($amounts['shipping_fee']) : null,
            'Tax' => isset($amounts['tax']) ? $fmt($amounts['tax']) : null,
            'Platform discount' => isset($amounts['platform_discount']) ? $fmt($amounts['platform_discount']) : null,
            'Seller discount' => isset($amounts['seller_discount']) ? $fmt($amounts['seller_discount']) : null,
            'Shipping fee platform discount' => isset($amounts['shipping_fee_platform_discount']) ? $fmt($amounts['shipping_fee_platform_discount']) : null,
            'Order amount (buyer paid)' => isset($amounts['order_amount']) ? $fmt($amounts['order_amount']) : null,
        ]])
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Shipment information</div>
    <div class="card-body p-0">
        @include($detailTable, ['showEmpty' => true, 'rows' => [
            'Shipping time' => $fmtDt($shipment['shipped_at'] ?? null),
            'Shipping method' => $shipment['service'] ?? null,
            'Tracking number' => !empty($shipment['tracking']) ? '<code>'.e($shipment['tracking']).'</code>' : null,
            'Status' => $shipment['status'] ?? null,
        ]])
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Buyer</div>
            <div class="card-body p-0">
                @include($detailTable, ['showEmpty' => true, 'rows' => [
                    'Name' => $buyer['name'] ?? null,
                    'Nickname' => $buyer['nickname'] ?? null,
                    'User ID' => $buyer['user_id'] ?? null,
                    'Email' => $buyer['email'] ?? null,
                    'Phone' => $buyer['phone'] ?? null,
                    'Country' => $buyer['country'] ?? null,
                ]])
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Complete shipping address</div>
            <div class="card-body p-0">
                @include($detailTable, ['showEmpty' => true, 'rows' => [
                    'Receiver name' => $shipping['recipient'] ?? null,
                    'Detailed address' => $shipping['detail_address'] ?? null,
                    'Address line 1' => $shipping['address_line_1'] ?? null,
                    'Address line 2' => $shipping['address_line_2'] ?? null,
                    'City' => $shipping['city'] ?? null,
                    'State / Province' => $shipping['province'] ?? null,
                    'Zip code' => $shipping['zip'] ?? null,
                    'Country' => $shipping['country'] ?? null,
                    'Email' => $shipping['email'] ?? null,
                    'Phone' => $shipping['phone'] ?? null,
                ]])
            </div>
        </div>
    </div>
</div>

@if(!empty($logistics))
    <div class="card mb-3">
        <div class="card-header">Logistics & tracking</div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Service</th>
                        <th>Tracking</th>
                        <th>Shipped</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logistics as $log)
                        <tr>
                            <td class="ps-3">{{ $log['service'] ?? '—' }}</td>
                            <td><code>{{ $log['tracking'] ?? '—' }}</code></td>
                            <td class="small">{{ $fmtDt($log['shipped_at'] ?? null) ?: '—' }}</td>
                            <td>{{ $log['status'] ?? '—' }}</td>
                            <td class="small">{{ $log['status_message'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-header">Products in this order ({{ count($lineItems) }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:72px;">Image</th>
                        <th>SKU</th>
                        <th>Product ID</th>
                        <th>SKU ID</th>
                        <th>Title</th>
                        <th>Qty</th>
                        <th>Unit price</th>
                        <th>Line total</th>
                        <th>Status</th>
                        <th>Shopify</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lineItems as $item)
                        <tr>
                            <td>
                                @if(!empty($item['image']))
                                    <img src="{{ $item['image'] }}" alt="" class="img-thumbnail" style="max-width:56px; max-height:56px; object-fit:contain;" referrerpolicy="no-referrer" loading="lazy">
                                @else — @endif
                            </td>
                            <td><code>{{ $item['sku'] ?? '—' }}</code></td>
                            <td>{{ $item['product_id'] ?? '—' }}</td>
                            <td>{{ $item['sku_id'] ?? '—' }}</td>
                            <td>{{ $item['title'] ?? '—' }}</td>
                            <td>{{ $item['quantity'] ?? 1 }}</td>
                            <td>{{ isset($item['unit_price']) ? number_format((float) $item['unit_price'], 2) : '—' }}</td>
                            <td>{{ isset($item['line_total']) ? number_format((float) $item['line_total'], 2) : '—' }}</td>
                            <td>{{ $item['status'] ?? '—' }}</td>
                            <td>
                                @if(!empty($item['shopify_order_id']))
                                    <span class="badge bg-success">Imported</span>
                                @else
                                    {{ $item['import_status'] ?? 'pending' }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No line items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Shopify import</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th class="ps-3" style="width:200px;">Shopify order ID</th><td>{{ $shopify['shopify_order_id'] ?? '—' }}</td></tr>
                <tr><th class="ps-3">Import status</th><td>{{ $shopify['import_status'] ?? 'pending' }}</td></tr>
                <tr><th class="ps-3">Pushed at</th><td>{{ $fmtDt($shopify['pushed_to_shopify_at'] ?? null) ?: '—' }}</td></tr>
                <tr><th class="ps-3">Tracking pushed at</th><td>{{ $fmtDt($shopify['tracking_pushed_at'] ?? null) ?: '—' }}</td></tr>
                <tr><th class="ps-3">Sent to Shopify</th><td class="small text-muted">
                    Shipping address, buyer details, payment, and line items are sent when the order is imported / address sync runs.
                </td></tr>
            </tbody>
        </table>
    </div>
</div>
