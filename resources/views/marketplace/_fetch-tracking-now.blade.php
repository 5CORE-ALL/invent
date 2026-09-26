@php
    $ftNowSlug = $fetchTrackingMarketplace ?? $slug ?? '';
    $ftNowUrl = $fetchTrackingUrl ?? ($ftNowSlug !== '' ? url('marketplace/'.$ftNowSlug.'/orders/fetch-tracking-now') : '');
@endphp
@if($ftNowUrl !== '')
<form method="post" action="{{ $ftNowUrl }}" class="d-inline"
    onsubmit="return confirm('Queue a fresh tracking catch-up now?\n\nUnfulfilled Shopify copies will be marked fulfilled (no customer email), then tracking is pushed to Amazon, Faire, Shein, Wayfair, Newegg, AliExpress, TikTok, Reverb, and the other marketplaces.\n\nThis returns immediately; refresh in a few minutes.');">
    @csrf
    <button type="submit" class="btn btn-sm btn-outline-warning"
        title="Fulfill unfulfilled Shopify copies from Veeqo/GOFO/marketplace tracking, then push tracking to every channel. The Shopify customer is not emailed.">
        <i class="ri-truck-line"></i> Fetch tracking now
    </button>
</form>
@endif
