@extends('layouts.vertical', ['title' => $title ?? 'TikTok Shop — Order', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php
    $summary = $detail['summary'] ?? [];
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.orders', 'tiktok') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> TikTok Shop Orders</a>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-1">
            <div>
                @include('marketplace._page-heading', ['slug' => 'tiktok', 'heading' => 'Order '.($summary['order_id'] ?? $line->order_id), 'mt' => ''])
                <p class="text-muted mb-0">
                    @if(!empty($summary['created']))
                        {{ \Carbon\Carbon::parse($summary['created'])->format('M d, Y H:i') }}
                    @endif
                    <span class="badge bg-secondary ms-1">{{ $summary['status'] ?? ($line->order_status ?? '—') }}</span>
                    @if(!empty($detail['raw_available']))
                        <span class="badge bg-info-subtle text-info ms-1">TikTok data: cached payload</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning ms-1">TikTok data: limited</span>
                    @endif
                </p>
            </div>
        </div>

        @include('marketplace.tiktok._nav', ['active' => 'orders'])

        <div class="alert alert-info py-2 small mb-3">
            Order details below are read from the synced TikTok payload (same data used for Shopify import / address sync).
        </div>

        @include('marketplace.tiktok._order-detail', ['detail' => $detail, 'marketplaceSlug' => 'tiktok'])
    </div>
</div>
@endsection
