@extends('layouts.vertical', ['title' => $title ?? 'AliExpress — Listing Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .ae-gallery img { max-height: 96px; object-fit: contain; cursor: pointer; border: 1px solid #e9ecef; border-radius: 6px; padding: 4px; background: #fff; }
    .ae-gallery img.active { border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,.2); }
    .ae-main-image { max-height: 320px; object-fit: contain; }
    .ae-description { max-height: none; overflow: visible; }
    .ae-description img { max-width: 100%; height: auto; }
    .ae-desc-title { font-weight: 600; margin-top: 1rem; margin-bottom: .35rem; }
    .ae-desc-body { margin-bottom: .75rem; color: #444; }
    .ae-desc-image img { display: block; margin: 0 auto; }
    .sku-hero { font-size: 1.05rem; word-break: break-word; }
    .source-pill { font-size: .75rem; }
</style>
@endsection

@section('content')
@php
    $s = $detail['shopify'] ?? [];
    $l = $detail['link'] ?? [];
    $ae = $detail['aliexpress'] ?? [];
    $images = $ae['images'] ?? [];
    if (!empty($s['image']) && !in_array($s['image'], $images, true)) {
        array_unshift($images, $s['image']);
    }
    $mainImage = $s['image'] ?? $ae['main_image'] ?? ($images[0] ?? null);
    $aeSource = $aeDataSource ?? 'none';
@endphp
<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'aliexpress') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Listings</a>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
            <div>
                <h4 class="mb-1">{{ $displayTitle }}</h4>
                <div class="sku-hero"><code>{{ $s['sku'] ?? '—' }}</code></div>
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    @if($linked)
                        <span class="badge bg-success-subtle text-success">Linked on AliExpress</span>
                    @else
                        <span class="badge bg-light text-muted">Not linked on AliExpress</span>
                    @endif
                    @if($aeSource === 'api')
                        <span class="badge bg-info-subtle text-info source-pill">AE data: live API</span>
                    @elseif($aeSource === 'cached')
                        <span class="badge bg-warning-subtle text-warning source-pill">AE data: cached map</span>
                    @else
                        <span class="badge bg-light text-muted source-pill">AE data: not loaded</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2">
                @if($connected)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-pull-ae" data-id="{{ $shopifySkuId }}">
                        <i class="ri-download-cloud-line"></i> Pull from AliExpress
                    </button>
                @endif
            </div>
        </div>

        @include('marketplace.aliexpress._nav', ['active' => 'products'])

        <div class="alert alert-info py-2 small mb-3">
            <strong>Read-only view.</strong> This page only displays data from Shopify and AliExpress. Nothing is pushed to Shopify or AliExpress from here.
        </div>

        @if($aeLiveError)
            <div class="alert alert-warning">{{ $aeLiveError }}</div>
        @endif

        @if(!$linked)
            <div class="alert alert-secondary small">
                This Shopify SKU is not linked in <code>aliexpress_metric</code> yet.
                Go to <a href="{{ route('marketplace.products', 'aliexpress') }}">Listings</a> and click <strong>Sync AE link map</strong> to match SKUs.
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">Images</div>
                    <div class="card-body text-center">
                        @if($mainImage)
                            <img id="ae-main-image" src="{{ $mainImage }}" alt="" class="img-fluid ae-main-image mb-3 rounded border">
                        @else
                            <p class="text-muted mb-0">No image available</p>
                        @endif
                        @if(count($images) > 1)
                            <div class="d-flex flex-wrap gap-2 justify-content-center ae-gallery">
                                @foreach($images as $i => $img)
                                    <img src="{{ $img }}" alt="" class="{{ $img === $mainImage ? 'active' : '' }}" onclick="document.getElementById('ae-main-image').src='{{ $img }}'; document.querySelectorAll('.ae-gallery img').forEach(function(el){el.classList.remove('active')}); this.classList.add('active');">
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 border-primary-subtle">
                            <div class="card-header bg-primary-subtle">Shopify (source)</div>
                            <div class="card-body p-0">
                                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                                    'SKU' => '<code>'.e($s['sku'] ?? '—').'</code>',
                                    'Title' => $s['product_title'] ?? null,
                                    'Variant' => $s['variant_title'] ?? null,
                                    'Variant ID' => $s['variant_id'] ?? null,
                                    'B2C price' => isset($s['b2c_price']) ? '$'.number_format((float)$s['b2c_price'], 2) : null,
                                    'Base price' => isset($s['price']) ? '$'.number_format((float)$s['price'], 2) : null,
                                    'Available' => $s['available_to_sell'] ?? null,
                                    'On hand' => $s['on_hand'] ?? null,
                                ]])
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border-warning-subtle">
                            <div class="card-header bg-warning-subtle">AliExpress</div>
                            <div class="card-body p-0">
                                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                                    'Product ID' => $ae['product_id'] ?? $l['product_id'] ?? null,
                                    'Title' => $ae['title'] ?? $l['title'] ?? null,
                                    'Status' => $ae['status'] ?? null,
                                    'Price (live)' => isset($ae['min_price']) ? '$'.number_format((float)$ae['min_price'], 2) : null,
                                    'Price (cached)' => isset($ae['cached_price']) ? '$'.number_format((float)$ae['cached_price'], 2) : null,
                                    'L30 / L60' => isset($l['l30']) ? ($l['l30'].' / '.($l['l60'] ?? '—')) : null,
                                ]])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Full Shopify inventory & pricing</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                    'SKU' => '<code>'.e($s['sku'] ?? '—').'</code>',
                    'Product title' => $s['product_title'] ?? null,
                    'Variant title' => $s['variant_title'] ?? null,
                    'Variant ID' => $s['variant_id'] ?? null,
                    'Available to sell' => $s['available_to_sell'] ?? null,
                    'On hand' => $s['on_hand'] ?? null,
                    'Committed' => $s['committed'] ?? null,
                    'Incoming' => $s['incoming'] ?? null,
                    'Unavailable' => $s['unavailable'] ?? null,
                    'B2C price' => isset($s['b2c_price']) ? '$'.number_format((float)$s['b2c_price'], 2) : null,
                    'B2B price' => isset($s['b2b_price']) ? '$'.number_format((float)$s['b2b_price'], 2) : null,
                    'Shopify L30' => $s['shopify_l30'] ?? null,
                    'Product link' => !empty($s['product_link']) ? '<a href="'.e($s['product_link']).'" target="_blank" rel="noopener">Open in Shopify</a>' : null,
                ]])
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">AliExpress listing details</div>
            <div class="card-body p-0">
                @include('marketplace.aliexpress._detail-table', ['showEmpty' => true, 'rows' => [
                    'Product ID' => $ae['product_id'] ?? $l['product_id'] ?? null,
                    'Title' => $ae['title'] ?? $l['title'] ?? null,
                    'Status' => $ae['status'] ?? null,
                    'Category ID' => $ae['category_id'] ?? null,
                    'Currency' => $ae['currency'] ?? null,
                    'Product unit' => $ae['unit'] ?? null,
                    'Package type' => $ae['package_type'] ?? null,
                    'Freight template' => $ae['freight_template_id'] ?? null,
                    'Bulk order' => $ae['bulk_order'] ?? null,
                    'Bulk discount' => $ae['bulk_discount'] ?? null,
                    'Min price' => isset($ae['min_price']) ? '$'.number_format((float)$ae['min_price'], 2) : null,
                    'Max price' => isset($ae['max_price']) ? '$'.number_format((float)$ae['max_price'], 2) : null,
                    'Created' => $ae['gmt_create'] ?? null,
                    'Modified' => $ae['gmt_modified'] ?? null,
                    'Last order' => !empty($l['last_order_date']) ? \Carbon\Carbon::parse($l['last_order_date'])->format('M d, Y H:i') : null,
                ]])
            </div>
        </div>

        @if(!empty($ae['variants']))
            <div class="card mb-3">
                <div class="card-header">SKU / variants ({{ count($ae['variants']) }})</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Source</th>
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
                                    <tr class="{{ ($v['sku'] ?? '') === ($s['sku'] ?? '') ? 'table-primary' : '' }}">
                                        <td class="small text-muted">{{ $v['source'] ?? ($aeSource === 'api' ? 'aliexpress' : 'shopify') }}</td>
                                        <td style="width:72px;">
                                            @if(!empty($v['image']))
                                                <img src="{{ $v['image'] }}" alt="" class="img-thumbnail" style="max-width:56px; max-height:56px; object-fit:contain;">
                                            @else — @endif
                                        </td>
                                        <td><code>{{ $v['sku'] ?? '—' }}</code></td>
                                        <td>{{ isset($v['price']) ? '$'.number_format((float)$v['price'], 2) : '—' }}</td>
                                        <td>{{ $v['stock'] ?? '—' }}</td>
                                        <td>{{ $v['ean'] ?? '—' }}</td>
                                        <td class="small">
                                            @if(!empty($v['properties']))
                                                @foreach($v['properties'] as $p)
                                                    <div>{{ $p['name'] }}: {{ $p['value'] }}</div>
                                                @endforeach
                                            @else — @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

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
                            <div class="text-muted small mb-2">Language: {{ $desc['language'] }}</div>
                        @endif
                        <div class="ae-description border rounded p-3 bg-white">{!! $desc['html'] ?? '' !!}</div>
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

<script>
document.getElementById('btn-pull-ae')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pulling…';
    fetch('{{ url('marketplace/aliexpress/products') }}/' + id + '/pull', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        alert(data.message || (data.success ? 'Done' : 'Failed'));
        if (data.success) location.reload();
    })
    .catch(function () { alert('Request failed.'); })
    .finally(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="ri-download-cloud-line"></i> Pull from AliExpress';
    });
});
</script>
@endsection
