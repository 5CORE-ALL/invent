@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Listing Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .ae-gallery img { max-height: 120px; object-fit: contain; cursor: pointer; border: 1px solid #e9ecef; border-radius: 6px; padding: 4px; background: #fff; }
    .ae-gallery img.active { border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,.2); }
    .ae-main-image { max-height: 360px; object-fit: contain; }
    .ae-description { max-height: 480px; overflow: auto; }
    .ae-description img { max-width: 100%; height: auto; }
</style>
@endsection

@section('content')
@php
    $s = $detail['shopify'] ?? [];
    $l = $detail['link'] ?? [];
    $ae = $detail['aliexpress'] ?? [];
    $images = $ae['images'] ?? [];
    $mainImage = $ae['main_image'] ?? ($images[0] ?? $s['image'] ?? null);
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Listings</a>
        <h4 class="mt-2 mb-1">{{ $displayTitle }}</h4>
        <p class="text-muted mb-3">
            <code>{{ $s['sku'] ?? '—' }}</code>
            @if($linked)
                <span class="badge bg-success-subtle text-success ms-1">Linked on AliExpress</span>
            @else
                <span class="badge bg-light text-muted ms-1">Not linked</span>
            @endif
            @if(!empty($ae['product_id']))
                <span class="text-muted ms-2">AE ID: {{ $ae['product_id'] }}</span>
            @endif
        </p>

        @include('marketplace.aliexpress._nav', ['active' => 'products'])

        @if($aeLiveError)
            <div class="alert alert-warning">{{ $aeLiveError }}</div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">Images</div>
                    <div class="card-body text-center">
                        @if($mainImage)
                            <img id="ae-main-image" src="{{ $mainImage }}" alt="" class="img-fluid ae-main-image mb-3 rounded border">
                        @else
                            <p class="text-muted">No image available</p>
                        @endif
                        @if(count($images) > 1)
                            <div class="d-flex flex-wrap gap-2 justify-content-center ae-gallery">
                                @foreach($images as $i => $img)
                                    <img src="{{ $img }}" alt="" class="{{ $i === 0 ? 'active' : '' }}" onclick="document.getElementById('ae-main-image').src='{{ $img }}'; document.querySelectorAll('.ae-gallery img').forEach(function(el){el.classList.remove('active')}); this.classList.add('active');">
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">Pricing & inventory</div>
                    <div class="card-body p-0">
                        @include('marketplace.aliexpress._detail-table', ['rows' => [
                            'Shopify SKU' => '<code>'.e($s['sku'] ?? '—').'</code>',
                            'Shopify B2C price' => isset($s['b2c_price']) ? number_format((float)$s['b2c_price'], 2) : null,
                            'Shopify B2B price' => isset($s['b2b_price']) ? number_format((float)$s['b2b_price'], 2) : null,
                            'Shopify available' => $s['available_to_sell'] ?? null,
                            'Shopify on hand' => $s['on_hand'] ?? null,
                            'AE min price' => isset($ae['min_price']) ? number_format((float)$ae['min_price'], 2) : null,
                            'AE max price' => isset($ae['max_price']) ? number_format((float)$ae['max_price'], 2) : null,
                            'AE cached price' => isset($l['price']) ? number_format((float)$l['price'], 2) : null,
                            'Currency' => $ae['currency'] ?? null,
                            'L30 / L60 sold' => isset($l['l30']) ? ($l['l30'].' / '.($l['l60'] ?? '—')) : null,
                        ]])
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Shopify product details</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['rows' => [
                    'Product title' => $s['product_title'] ?? null,
                    'Variant title' => $s['variant_title'] ?? null,
                    'Variant ID' => $s['variant_id'] ?? null,
                    'Committed' => $s['committed'] ?? null,
                    'Incoming' => $s['incoming'] ?? null,
                    'Unavailable' => $s['unavailable'] ?? null,
                    'Shopify L30' => $s['shopify_l30'] ?? null,
                    'Product link' => !empty($s['product_link']) ? '<a href="'.e($s['product_link']).'" target="_blank" rel="noopener">Open in Shopify</a>' : null,
                ]])
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">AliExpress listing details</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['rows' => [
                    'Product ID' => $ae['product_id'] ?? null,
                    'Title' => $ae['title'] ?? $l['title'] ?? null,
                    'Status' => $ae['status'] ?? null,
                    'Category ID' => $ae['category_id'] ?? null,
                    'Product unit' => $ae['unit'] ?? null,
                    'Package type' => $ae['package_type'] ?? null,
                    'Freight template' => $ae['freight_template_id'] ?? null,
                    'Bulk order' => $ae['bulk_order'] ?? null,
                    'Bulk discount' => $ae['bulk_discount'] ?? null,
                    'Created' => $ae['gmt_create'] ?? null,
                    'Modified' => $ae['gmt_modified'] ?? null,
                    'Last order (metric)' => !empty($l['last_order_date']) ? \Carbon\Carbon::parse($l['last_order_date'])->format('M d, Y H:i') : null,
                ]])
            </div>
        </div>

        @if(!empty($ae['subjects']))
            <div class="card mb-3">
                <div class="card-header">Titles by language</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Language</th><th>Title</th></tr></thead>
                        <tbody>
                            @foreach($ae['subjects'] as $sub)
                                <tr><td class="ps-3">{{ $sub['language'] ?? '—' }}</td><td>{{ $sub['subject'] ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!empty($ae['variants']))
            <div class="card mb-3">
                <div class="card-header">SKU variants ({{ count($ae['variants']) }})</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Image</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>EAN</th>
                                    <th>Properties</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ae['variants'] as $v)
                                    <tr>
                                        <td style="width:72px;">
                                            @if(!empty($v['image']))
                                                <img src="{{ $v['image'] }}" alt="" class="img-thumbnail" style="max-width:56px; max-height:56px; object-fit:contain;">
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td><code>{{ $v['sku'] ?? '—' }}</code></td>
                                        <td>{{ isset($v['price']) ? number_format((float)$v['price'], 2) : '—' }}</td>
                                        <td>{{ $v['stock'] ?? '—' }}</td>
                                        <td>{{ $v['ean'] ?? '—' }}</td>
                                        <td class="small">
                                            @if(!empty($v['properties']))
                                                @foreach($v['properties'] as $p)
                                                    <div>{{ $p['name'] }}: {{ $p['value'] }}</div>
                                                @endforeach
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if(!empty($ae['properties']))
            <div class="card mb-3">
                <div class="card-header">Product properties</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Property</th><th>Value</th></tr></thead>
                        <tbody>
                            @foreach($ae['properties'] as $prop)
                                <tr><td class="ps-3">{{ $prop['name'] }}</td><td>{{ $prop['value'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if(!empty($ae['descriptions']))
            <div class="card mb-3">
                <div class="card-header">Description</div>
                <div class="card-body">
                    @foreach($ae['descriptions'] as $desc)
                        @if(!empty($desc['language']))
                            <div class="text-muted small mb-1">Language: {{ $desc['language'] }}</div>
                        @endif
                        @if(!empty($desc['web']))
                            <div class="ae-description border rounded p-3 mb-3 bg-white">{!! $desc['web'] !!}</div>
                        @endif
                        @if(!empty($desc['mobile']))
                            <div class="mb-2"><span class="badge bg-light text-muted">Mobile</span></div>
                            <div class="ae-description border rounded p-3 mb-3 bg-white">{!! $desc['mobile'] !!}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        @elseif(!empty($l['bullet_points']))
            <div class="card mb-3">
                <div class="card-header">Bullet points (cached)</div>
                <div class="card-body"><pre class="mb-0 small">{{ $l['bullet_points'] }}</pre></div>
            </div>
        @endif
    </div>
</div>
@endsection
