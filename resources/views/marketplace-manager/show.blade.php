@extends('layouts.vertical', ['title' => $title ?? ($channel['label'] ?? 'Marketplace'), 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
@php $slug = $channel['slug'] ?? 'aliexpress'; @endphp
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <a href="{{ route('marketplace.manager.index') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Marketplace Manager</a>
                <h4 class="mb-1 mt-1">{{ $channel['label'] }}</h4>
                <p class="text-muted mb-0">Source shop: <strong>{{ $channel['source_shop'] }}</strong></p>
            </div>
            <div>
                @if($connected)
                    <span class="badge bg-success-subtle text-success fs-6"><i class="ri-checkbox-circle-line"></i> API Connected</span>
                @else
                    <span class="badge bg-danger-subtle text-danger fs-6"><i class="ri-error-warning-line"></i> Not Connected</span>
                @endif
            </div>
        </div>

        @include('marketplace.'.$slug.'._nav', ['active' => 'overview'])

        <div class="row g-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Listings tracked</p>
                        <h3 class="mb-0">{{ number_format($listings_count) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Inventory sync</p>
                        <h5 class="mb-0">{{ ($settings['inventory']['inventory_sync'] ?? false) ? 'Enabled' : 'Disabled' }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Order import</p>
                        <h5 class="mb-0">{{ ($settings['order']['auto_import_to_shopify'] ?? false) ? 'Auto → Shopify' : 'Manual' }}</h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <p class="text-muted mb-1 small">Price sync</p>
                        <h5 class="mb-0">{{ ($settings['pricing']['price_sync'] ?? false) ? 'Enabled' : 'Disabled' }}</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h5 class="card-title mb-0">Quick actions</h5></div>
            <div class="card-body d-flex flex-wrap gap-2">
                <a href="{{ route('marketplace.manager.'.$slug.'.connect') }}" class="btn btn-outline-secondary"><i class="ri-plug-line me-1"></i> Connection</a>
                <a href="{{ route('marketplace.products', $slug) }}" class="btn btn-outline-primary"><i class="ri-list-check me-1"></i> Listings</a>
                <a href="{{ route('marketplace.orders', $slug) }}" class="btn btn-outline-primary"><i class="ri-shopping-bag-line me-1"></i> Orders</a>
                <a href="{{ route('marketplace.settings', $slug) }}" class="btn btn-outline-primary"><i class="ri-settings-3-line me-1"></i> Sync Settings</a>
                @if($slug === 'aliexpress')
                    <a href="{{ route('listing.aliexpress') }}" class="btn btn-outline-secondary" target="_blank"><i class="ri-external-link-line me-1"></i> Listing Aliexpress (legacy)</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
