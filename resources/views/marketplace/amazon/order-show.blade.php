@extends('layouts.vertical', ['title' => $title ?? 'Amz Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $raw = is_array($raw ?? null) ? $raw : \App\Models\AmazonOrder::decodeRawPayload($order->raw_data ?? null);
    $addr = is_array($raw['ShippingAddress'] ?? null) ? $raw['ShippingAddress'] : (is_array($raw['shippingAddress'] ?? null) ? $raw['shippingAddress'] : []);
    $buyerRaw = is_array($raw['BuyerInfo'] ?? null) ? $raw['BuyerInfo'] : (is_array($raw['buyerInfo'] ?? null) ? $raw['buyerInfo'] : []);
    $orderTotal = is_array($raw['OrderTotal'] ?? null) ? $raw['OrderTotal'] : (is_array($raw['orderTotal'] ?? null) ? $raw['orderTotal'] : []);
    $fmtDt = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->timezone('America/Los_Angeles')->format('M d, Y H:i').' PT';
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $fmtMoney = function ($amount, $currency = null) {
        if ($amount === null || $amount === '') {
            return null;
        }
        $cur = $currency ?: 'USD';

        return $cur.' '.number_format((float) $amount, 2);
    };
    $boolLabel = function ($value) {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    };
    $flattenRaw = function (array $data, string $prefix = '') use (&$flattenRaw): array {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            if (is_array($v)) {
                if ($v === []) {
                    $out[$key] = '[]';
                } elseif (array_is_list($v) && collect($v)->every(fn ($i) => ! is_array($i))) {
                    $out[$key] = implode(', ', array_map('strval', $v));
                } else {
                    $out = array_merge($out, $flattenRaw($v, $key));
                }
            } elseif (is_bool($v)) {
                $out[$key] = $v ? 'true' : 'false';
            } elseif ($v === null) {
                $out[$key] = null;
            } else {
                $out[$key] = is_scalar($v) ? (string) $v : json_encode($v);
            }
        }

        return $out;
    };
    $rawOrderRows = $flattenRaw($raw);
    $prettyJson = static function ($data): string {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    };
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'amazon') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Amz Orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-1">
            <div>
                @include('marketplace._page-heading', ['slug' => 'amazon', 'heading' => 'Order '.$order->amazon_order_id, 'mb' => 'mb-0', 'mt' => ''])
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($connected ?? true)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-pull-amazon-order" data-id="{{ $order->id }}">
                        <i class="ri-download-cloud-line"></i> Pull address from Amazon
                    </button>
                    @if($order->shopify_order_id)
                        @include('marketplace._fetch-tracking-button', ['fetchTrackingMarketplace' => 'amazon', 'fetchTrackingOrderId' => $order->id, 'fetchTrackingShopifyId' => $order->shopify_order_id])
                        <button type="button" class="btn btn-sm btn-warning" id="btn-push-tracking-amazon" data-id="{{ $order->id }}" title="If Shopify has no tracking, fetch it from Veeqo or GOFO (4Seller) first, then confirm shipment on Amazon">
                            <i class="ri-truck-line"></i> Push tracking to Amazon
                        </button>
                    @endif
                @endif
            </div>
        </div>

        @include('marketplace.amazon._nav', ['active' => 'orders'])

        <div class="row g-3 mb-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">Order summary</div>
                    <div class="card-body p-0">
                        @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'rows' => [
                            'Amazon order ID' => $raw['AmazonOrderId'] ?? $order->amazon_order_id,
                            'Seller order ID' => $raw['SellerOrderId'] ?? $raw['sellerOrderId'] ?? null,
                            'Status' => $raw['OrderStatus'] ?? $order->status,
                            'Fulfillment channel' => $raw['FulfillmentChannel'] ?? $order->fulfillmentChannel(),
                            'Sales channel' => $raw['SalesChannel'] ?? $raw['salesChannel'] ?? null,
                            'Order channel' => $raw['OrderChannel'] ?? $raw['orderChannel'] ?? null,
                            'Order type' => $raw['OrderType'] ?? $raw['orderType'] ?? null,
                            'Marketplace ID' => $raw['MarketplaceId'] ?? $raw['marketplaceId'] ?? null,
                            'Purchase date' => $fmtDt($raw['PurchaseDate'] ?? $order->order_date),
                            'Last update' => $fmtDt($raw['LastUpdateDate'] ?? $raw['lastUpdateDate'] ?? null),
                            'Earliest ship date' => $fmtDt($raw['EarliestShipDate'] ?? $raw['earliestShipDate'] ?? null),
                            'Latest ship date' => $fmtDt($raw['LatestShipDate'] ?? $raw['latestShipDate'] ?? null),
                            'Earliest delivery' => $fmtDt($raw['EarliestDeliveryDate'] ?? $raw['earliestDeliveryDate'] ?? null),
                            'Latest delivery' => $fmtDt($raw['LatestDeliveryDate'] ?? $raw['latestDeliveryDate'] ?? null),
                            'Ship service level' => $raw['ShipServiceLevel'] ?? $raw['shipServiceLevel'] ?? null,
                            'Shipment service category' => $raw['ShipmentServiceLevelCategory'] ?? $raw['shipmentServiceLevelCategory'] ?? null,
                            'Items shipped' => $raw['NumberOfItemsShipped'] ?? $raw['numberOfItemsShipped'] ?? null,
                            'Items unshipped' => $raw['NumberOfItemsUnshipped'] ?? $raw['numberOfItemsUnshipped'] ?? null,
                            'Prime' => $boolLabel($raw['IsPrime'] ?? $raw['isPrime'] ?? null),
                            'Premium' => $boolLabel($raw['IsPremiumOrder'] ?? $raw['isPremiumOrder'] ?? null),
                            'Business order' => $boolLabel($raw['IsBusinessOrder'] ?? $raw['isBusinessOrder'] ?? null),
                            'Replacement order' => $boolLabel($raw['IsReplacementOrder'] ?? $raw['isReplacementOrder'] ?? null),
                            'Sold by Amazon' => $boolLabel($raw['IsSoldByAB'] ?? $raw['isSoldByAB'] ?? null),
                            'Easy Ship status' => $raw['EasyShipShipmentStatus'] ?? $raw['easyShipShipmentStatus'] ?? null,
                            'Seller display name' => $raw['SellerDisplayName'] ?? $raw['sellerDisplayName'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">Payment details</div>
                    <div class="card-body p-0">
                        @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'rows' => [
                            'Order total' => $fmtMoney($orderTotal['Amount'] ?? $order->total_amount, $orderTotal['CurrencyCode'] ?? $order->currency),
                            'Currency' => $orderTotal['CurrencyCode'] ?? $order->currency,
                            'Payment method' => $raw['PaymentMethod'] ?? $raw['paymentMethod'] ?? null,
                            'Payment method details' => $raw['PaymentMethodDetails'] ?? $raw['paymentMethodDetails'] ?? null,
                            'Shopify order ID' => $order->shopify_order_id,
                            'Import status' => $order->import_status,
                            'Pushed at' => !empty($order->pushed_to_shopify_at) ? $fmtDt($order->pushed_to_shopify_at) : null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Buyer</div>
                    <div class="card-body p-0">
                        @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'rows' => [
                            'Name' => $buyerRaw['BuyerName'] ?? $buyerRaw['buyerName'] ?? ($raw['BuyerName'] ?? ($buyerInfo['name'] ?? null)),
                            'Email' => $buyerRaw['BuyerEmail'] ?? $buyerRaw['buyerEmail'] ?? ($buyerInfo['email'] ?? null),
                            'County' => $buyerRaw['BuyerCounty'] ?? $buyerRaw['buyerCounty'] ?? null,
                            'Purchase order number' => $buyerRaw['PurchaseOrderNumber'] ?? $buyerRaw['purchaseOrderNumber'] ?? null,
                            'Buyer tax info' => $raw['BuyerTaxInfo'] ?? $raw['buyerTaxInfo'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">Complete shipping address</div>
                    <div class="card-body p-0">
                        @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'rows' => [
                            'Receiver name' => $addr['Name'] ?? $addr['name'] ?? ($shippingAddress['name'] ?? null),
                            'Address line 1' => $addr['AddressLine1'] ?? $addr['addressLine1'] ?? ($shippingAddress['line1'] ?? null),
                            'Address line 2' => $addr['AddressLine2'] ?? $addr['addressLine2'] ?? ($shippingAddress['line2'] ?? null),
                            'Address line 3' => $addr['AddressLine3'] ?? $addr['addressLine3'] ?? null,
                            'City' => $addr['City'] ?? $addr['city'] ?? ($shippingAddress['city'] ?? null),
                            'County' => $addr['County'] ?? $addr['county'] ?? null,
                            'District' => $addr['District'] ?? $addr['district'] ?? null,
                            'State / Region' => $addr['StateOrRegion'] ?? $addr['stateOrRegion'] ?? ($shippingAddress['state'] ?? null),
                            'Municipality' => $addr['Municipality'] ?? $addr['municipality'] ?? null,
                            'Postal code' => $addr['PostalCode'] ?? $addr['postalCode'] ?? ($shippingAddress['postal'] ?? null),
                            'Country' => $addr['CountryCode'] ?? $addr['countryCode'] ?? ($shippingAddress['country'] ?? null),
                            'Phone' => $addr['Phone'] ?? $addr['phone'] ?? ($shippingAddress['phone'] ?? null),
                            'Address type' => $addr['AddressType'] ?? $addr['addressType'] ?? null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Shopify import</h5>
                <div class="d-flex flex-wrap gap-2">
                    @if($order->shopify_order_id)
                        <span class="text-muted small align-self-center">Already imported</span>
                    @elseif($order->isFba())
                        <span class="text-muted small align-self-center">FBA orders are not created on Shopify.</span>
                    @else
                        @php
                            $pushBlocked = ($importPaidOrdersOnly ?? false) && ! ($orderIsPaid ?? true);
                            $canCreate = $order->canCreateShopifyOrder();
                        @endphp
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-dry-run-shopify" data-id="{{ $order->id }}">
                            Dry run (preview)
                        </button>
                        @if($pushBlocked)
                            <button type="button" class="btn btn-sm btn-secondary" disabled>Push to Shopify</button>
                        @elseif($canCreate)
                            <button type="button" class="btn btn-sm btn-warning" id="btn-push-order" data-id="{{ $order->id }}">
                                Push to Shopify
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-success" id="btn-mark-imported" data-id="{{ $order->id }}" data-order-id="{{ $order->amazon_order_id }}">
                            Already imported
                        </button>
                    @endif
                </div>
            </div>
            <div class="card-body small text-muted">
                FBM orders on/after {{ \App\Models\AmazonOrder::SHOPIFY_IMPORT_CUTOFF_DATE }} PT auto-push to Shopify when fetched.
                Existing Shopify orders (previous sync app) are linked, never duplicated. FBA is never created.
                After a shipping label is bought in Veeqo, 4Seller (GOFO), Shopify, or ShipStation, tracking is written to Shopify and confirmed on Amazon.
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Products in this order ({{ $items->count() }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>ASIN</th>
                                <th>Order item ID</th>
                                <th>Title</th>
                                <th>Qty ordered</th>
                                <th>Qty shipped</th>
                                <th>Item price</th>
                                <th>Shipping</th>
                                <th>Promotion</th>
                                <th>Condition</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                @php
                                    $itemRaw = \App\Models\AmazonOrder::decodeRawPayload($item->raw_data ?? null);
                                    $itemPriceAmt = data_get($itemRaw, 'ItemPrice.Amount');
                                    $shipAmt = data_get($itemRaw, 'ShippingPrice.Amount');
                                    $promoAmt = data_get($itemRaw, 'PromotionDiscount.Amount');
                                @endphp
                                <tr>
                                    <td><code>{{ $item->sku ?: ($itemRaw['SellerSKU'] ?? '—') }}</code></td>
                                    <td>{{ $item->asin ?: ($itemRaw['ASIN'] ?? '—') }}</td>
                                    <td class="small"><code>{{ $itemRaw['OrderItemId'] ?? $itemRaw['orderItemId'] ?? '—' }}</code></td>
                                    <td>{{ $item->title ?: ($itemRaw['Title'] ?? '—') }}</td>
                                    <td>{{ $item->quantity ?? ($itemRaw['QuantityOrdered'] ?? 0) }}</td>
                                    <td>{{ $itemRaw['QuantityShipped'] ?? $itemRaw['quantityShipped'] ?? '—' }}</td>
                                    <td>
                                        {{ $itemPriceAmt !== null ? number_format((float) $itemPriceAmt, 2) : (is_numeric($item->price) ? number_format((float) $item->price, 2) : '—') }}
                                        {{ data_get($itemRaw, 'ItemPrice.CurrencyCode') ?: ($item->currency ?: ($order->currency ?: 'USD')) }}
                                    </td>
                                    <td>{{ $shipAmt !== null ? number_format((float) $shipAmt, 2) : '—' }}</td>
                                    <td>{{ $promoAmt !== null ? number_format((float) $promoAmt, 2) : '—' }}</td>
                                    <td>{{ $itemRaw['ConditionId'] ?? $itemRaw['conditionId'] ?? $itemRaw['ConditionSubtypeId'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-3">No line items stored for this order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Raw API response — order</div>
            <div class="card-body p-0">
                @if($rawOrderRows !== [])
                    @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'width' => '280px', 'rows' => $rawOrderRows])
                @else
                    <p class="text-muted small p-3 mb-0">No stored Amazon order payload.</p>
                @endif
                <div class="border-top p-3">
                    <div class="small text-muted mb-2">Full JSON</div>
                    <pre class="small bg-light border rounded p-3 mb-0" style="max-height: 480px; overflow: auto;">{{ $prettyJson($raw) }}</pre>
                </div>
            </div>
        </div>

        @foreach($items as $item)
            @php $itemRaw = \App\Models\AmazonOrder::decodeRawPayload($item->raw_data ?? null); @endphp
            <div class="card mt-3">
                <div class="card-header">Raw API response — item {{ $item->sku ?: ($item->asin ?: '#'.$item->id) }}</div>
                <div class="card-body p-0">
                    @if($itemRaw !== [])
                        @include('marketplace.amazon._detail-table', ['showEmpty' => true, 'width' => '280px', 'rows' => $flattenRaw($itemRaw)])
                    @else
                        <p class="text-muted small p-3 mb-0">No stored item payload.</p>
                    @endif
                    <div class="border-top p-3">
                        <div class="small text-muted mb-2">Full JSON</div>
                        <pre class="small bg-light border rounded p-3 mb-0" style="max-height: 420px; overflow: auto;">{{ $prettyJson($itemRaw) }}</pre>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="modal fade" id="shopifyDryRunModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Shopify push preview (dry run)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="shopify-dry-run-summary" class="mb-3"></div>
                <pre id="shopify-dry-run-json" class="small bg-light border rounded p-3 mb-0" style="max-height: 420px; overflow: auto;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('btn-dry-run-shopify')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.push', 'amazon') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id, dry_run: true }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        var summary = document.getElementById('shopify-dry-run-summary');
        var jsonEl = document.getElementById('shopify-dry-run-json');
        summary.innerHTML = '<p class="mb-1">' + (data.message || '') + '</p>';
        jsonEl.textContent = JSON.stringify(data.payload || data, null, 2);
        new bootstrap.Modal(document.getElementById('shopifyDryRunModal')).show();
    })
    .catch(function () { alert('Dry run request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-push-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('Push this Amazon FBM order to Shopify now?\n\nThis creates a real Shopify order (same as other marketplaces). FBA is never created. If it already exists on Shopify, it will be linked instead of duplicated.')) return;
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.push', 'amazon') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Pushed to Shopify.' : 'Push failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-mark-imported')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    var orderId = btn.getAttribute('data-order-id') || id;
    if (!id) return;
    if (!confirm('Mark Amazon order ' + orderId + ' as already imported?\n\nNo new Shopify order will be created.')) return;
    var shopifyOrderId = prompt('Optional Shopify order ID (leave blank if entered manually):', '') || '';
    btn.disabled = true;
    fetch('{{ route('marketplace.orders.mark-imported', 'amazon') }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: id, shopify_order_id: shopifyOrderId }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Marked imported.' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () { btn.disabled = false; });
});

document.getElementById('btn-pull-amazon-order')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pulling…';
    fetch('{{ url('marketplace/amazon/orders') }}/' + id + '/pull', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
        alert((res.data && res.data.message) || (res.ok ? 'Done' : 'Failed'));
        if (res.ok && res.data && res.data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});

document.getElementById('btn-push-tracking-amazon')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('If Shopify has no tracking yet, fetch it from Veeqo or GOFO (4Seller) and fulfill Shopify, then confirm shipment on Amazon?')) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pushing…';
    fetch('{{ url('marketplace/amazon/orders') }}/' + id + '/push-tracking', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
        alert((res.data && res.data.message) || (res.ok ? 'Done' : 'Failed'));
        if (res.ok && res.data && res.data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
@endsection
