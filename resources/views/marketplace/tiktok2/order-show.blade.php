@extends('layouts.vertical', ['title' => $title ?? 'TikTok 2 — Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $summary = $detail['summary'] ?? [];
    $shopify = $detail['shopify'] ?? [];
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'tiktok2') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok 2 Orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-1">
            <div>
                @include('marketplace._page-heading', ['slug' => 'tiktok2', 'heading' => 'Order '.($summary['order_id'] ?? $line->order_id), 'mt' => ''])
                <p class="text-muted mb-0">
                    @if(!empty($summary['created']))
                        {{ \Carbon\Carbon::parse($summary['created'])->format('M d, Y H:i') }}
                    @endif
                    <span class="badge bg-secondary ms-1">{{ $summary['status'] ?? ($line->order_status ?? '—') }}</span>
                    @if(!empty($detail['raw_available']))
                        <span class="badge bg-info-subtle text-info ms-1">TikTok 2 data: cached payload</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning ms-1">TikTok 2 data: limited</span>
                    @endif
                </p>
            </div>
            <div class="d-flex gap-2">
                @if($connected && !empty($shopify['shopify_order_id']))
                    <button type="button" class="btn btn-sm btn-warning" id="btn-push-tracking-tiktok-top" data-id="{{ $line->id }}" title="Read Shopify fulfillment tracking and mark shipped on TikTok 2">
                        <i class="ri-truck-line"></i> Push tracking to TikTok 2
                    </button>
                @endif
            </div>
        </div>

        @include('marketplace.tiktok2._nav', ['active' => 'orders'])

        <div class="alert alert-info py-2 small mb-3">
            Order details are synced from TikTok 2 into this app and imported to Shopify.
            After you buy/download a shipping label in Shopify, use <strong>Push tracking to TikTok 2</strong> so TikTok is marked shipped with that tracking number.
        </div>

        @include('marketplace.tiktok._order-detail', ['detail' => $detail, 'marketplaceSlug' => 'tiktok2', 'connected' => $connected ?? false, 'line' => $line])
    </div>
</div>
<script>
function pushTrackingToTikTok(btn) {
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('Read the Shopify tracking number for this order and mark it shipped on TikTok 2?')) return;
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pushing…';
    fetch(@json(url('marketplace/tiktok2/orders')) + '/' + id + '/push-tracking', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
    .then(function (res) {
        alert(res.data.message || (res.data.success ? 'Tracking pushed.' : 'Failed'));
        if (res.data.success || res.data.skipped) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = original;
    });
}
document.getElementById('btn-push-tracking-tiktok-top')?.addEventListener('click', function () { pushTrackingToTikTok(this); });
document.getElementById('btn-push-tracking-tiktok')?.addEventListener('click', function () { pushTrackingToTikTok(this); });
</script>
@endsection
