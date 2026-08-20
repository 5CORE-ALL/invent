@php
    $ftMarketplace = $fetchTrackingMarketplace ?? '';
    $ftOrderId = $fetchTrackingOrderId ?? ($line->id ?? ($order->id ?? null));
    $ftShopify = $fetchTrackingShopifyId ?? ($shopify['shopify_order_id'] ?? ($order->shopify_order_id ?? null));
@endphp
@if($ftMarketplace !== '' && !empty($ftOrderId) && !empty($ftShopify))
<button type="button" class="btn btn-sm btn-outline-warning btn-mm-fetch-tracking"
    data-url="{{ url('marketplace/'.$ftMarketplace.'/orders/'.$ftOrderId.'/fetch-tracking') }}"
    title="Fetch tracking from Veeqo, GOFO (4Seller labels), or the marketplace. Write it to Shopify and mark fulfilled (no customer email).">
    <i class="ri-truck-line"></i> Fetch tracking
</button>
@once
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-mm-fetch-tracking');
    if (!btn || btn.disabled) return;
    var url = btn.getAttribute('data-url');
    if (!url) return;
    e.preventDefault();
    if (!confirm('Fetch tracking from Veeqo and from GOFO (4Seller labels), add it to the Shopify order, and mark that Shopify order fulfilled?\n\nThe Shopify customer will not be emailed.')) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Fetching…';
    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
        alert((res.data && res.data.message) || (res.ok ? 'Done' : 'Failed'));
        if (res.data && res.data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
@endonce
@endif
