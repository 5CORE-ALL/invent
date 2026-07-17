@extends('layouts.vertical', ['title' => $title ?? 'Alibaba — Listing Detail', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .ae-gallery img { max-height: 96px; object-fit: contain; cursor: pointer; border: 1px solid #e9ecef; border-radius: 6px; padding: 4px; background: #fff; }
    .ae-gallery img.active { border-color: #0d6efd; box-shadow: 0 0 0 2px rgba(13,110,253,.2); }
    .ae-main-image { max-height: 320px; object-fit: contain; }
    .ae-description { max-height: 480px; overflow: auto; }
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
    $ae = $detail['alibaba'] ?? [];
    $shopifyImages = $s['images'] ?? [];
    $aeImages = $ae['images'] ?? [];
    $aeSource = $aeDataSource ?? 'none';
    $shopifyDescription = $s['description_html'] ?? null;
    $aeDescriptionBlocks = $ae['descriptions'] ?? [];
    $shopifyDescSource = $s['description_source'] ?? null;
    $shopifyQtyShow = $s['available_to_sell'] ?? $s['on_hand'] ?? null;
    $mpQtyShow = $linked ? ($ae['stock'] ?? $l['ab_stock'] ?? null) : null;
    $inventoryMismatch = $linked
        && $shopifyQtyShow !== null
        && $mpQtyShow !== null
        && (int) $shopifyQtyShow !== (int) $mpQtyShow;

    $shopifyListingRows = [
        'SKU' => '<code>'.e($s['sku'] ?? '—').'</code>',
        'Title' => $s['product_title'] ?? null,
        'Variant' => $s['variant_title'] ?? null,
        'Variant ID' => $s['variant_id'] ?? null,
        'B2C price' => isset($s['b2c_price']) ? '$'.number_format((float)$s['b2c_price'], 2) : null,
        'B2B price' => isset($s['b2b_price']) ? '$'.number_format((float)$s['b2b_price'], 2) : null,
        'Base price' => isset($s['price']) ? '$'.number_format((float)$s['price'], 2) : null,
        'Available' => $s['available_to_sell'] ?? null,
        'On hand' => $s['on_hand'] ?? null,
        'Committed' => $s['committed'] ?? null,
        'Incoming' => $s['incoming'] ?? null,
        'Unavailable' => $s['unavailable'] ?? null,
        'Shopify L30' => $s['shopify_l30'] ?? null,
        'Vendor' => $s['vendor'] ?? null,
        'Product type' => $s['product_type'] ?? null,
        'Catalog status' => $s['catalog_status'] ?? null,
        'Shopify product ID' => $s['shopify_product_id'] ?? null,
        'Product link' => !empty($s['product_link']) ? '<a href="'.e($s['product_link']).'" target="_blank" rel="noopener">Open in Shopify</a>' : null,
    ];

    $aeListingRows = [
        'Product ID' => $ae['product_id'] ?? $l['product_id'] ?? null,
        'Title' => $ae['title'] ?? $l['title'] ?? null,
        'Status' => $ae['status'] ?? null,
        'Alibaba Qty' => $ae['stock'] ?? $l['ab_stock'] ?? null,
        'Category ID' => $ae['category_id'] ?? null,
        'Currency' => $ae['currency'] ?? null,
        'Product unit' => $ae['unit'] ?? null,
        'Package type' => $ae['package_type'] ?? null,
        'Freight template' => $ae['freight_template_id'] ?? null,
        'Bulk order' => $ae['bulk_order'] ?? null,
        'Bulk discount' => $ae['bulk_discount'] ?? null,
        'Min price' => isset($ae['min_price']) ? '$'.number_format((float)$ae['min_price'], 2) : null,
        'Max price' => isset($ae['max_price']) ? '$'.number_format((float)$ae['max_price'], 2) : null,
        'Price (cached)' => isset($ae['cached_price']) ? '$'.number_format((float)$ae['cached_price'], 2) : null,
        'L30 / L60' => isset($l['l30']) ? ($l['l30'].' / '.($l['l60'] ?? '—')) : null,
        'Created' => $ae['gmt_create'] ?? null,
        'Modified' => $ae['gmt_modified'] ?? null,
        'Last order' => !empty($l['last_order_date']) ? \Carbon\Carbon::parse($l['last_order_date'])->format('M d, Y H:i') : null,
        'Last synced' => !empty($l['last_synced_at']) ? \Carbon\Carbon::parse($l['last_synced_at'])->timezone(config('app.timezone'))->format('M d, Y H:i') : null,
        'Link map synced' => !empty($l['link_synced_at']) ? \Carbon\Carbon::parse($l['link_synced_at'])->timezone(config('app.timezone'))->format('M d, Y H:i') : null,
        'Inventory synced' => !empty($l['inventory_synced_at']) ? \Carbon\Carbon::parse($l['inventory_synced_at'])->timezone(config('app.timezone'))->format('M d, Y H:i') : null,
    ];

    ob_start();
@endphp
@include('marketplace.alibaba._detail-table', ['showEmpty' => true, 'rows' => $shopifyListingRows])
@php $shopifyListingHtml = ob_get_clean(); ob_start(); @endphp
@include('marketplace.alibaba._detail-table', ['showEmpty' => true, 'rows' => $aeListingRows])
@php $aeListingHtml = ob_get_clean(); @endphp

<div class="row">
    <div class="col-12">
        <a href="{{ route('marketplace.products', 'alibaba') }}" class="text-muted small"><i class="ri-arrow-left-line"></i> Back to Listings</a>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mt-2 mb-3">
            <div>
                @include('marketplace._page-heading', ['slug' => 'alibaba', 'heading' => $displayTitle, 'mt' => ''])
                <div class="sku-hero"><code>{{ $s['sku'] ?? '—' }}</code></div>
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    @if($linked)
                        <span class="badge bg-success-subtle text-success">Linked on Alibaba</span>
                    @else
                        <span class="badge bg-light text-muted">Not linked on Alibaba</span>
                    @endif
                    @if($aeSource === 'api')
                        <span class="badge bg-info-subtle text-info source-pill">Alibaba data: live API</span>
                    @elseif($aeSource === 'cached')
                        <span class="badge bg-warning-subtle text-warning source-pill">Alibaba data: cached map</span>
                    @else
                        <span class="badge bg-light text-muted source-pill">Alibaba data: not loaded</span>
                    @endif
                    @if(!empty($l['last_synced_at']))
                        <span class="badge bg-secondary-subtle text-secondary source-pill" title="Latest of link-map or inventory/price sync">
                            Last synced: {{ \Carbon\Carbon::parse($l['last_synced_at'])->timezone(config('app.timezone'))->format('M d, Y H:i') }}
                        </span>
                    @else
                        <span class="badge bg-light text-muted source-pill">Last synced: —</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($connected)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-pull-ae" data-id="{{ $shopifySkuId }}">
                        <i class="ri-download-cloud-line"></i> Pull from Alibaba
                    </button>
                @endif
                @if($connected && !empty($inventoryMismatch))
                    <button type="button" class="btn btn-sm btn-warning" id="btn-sync-inventory" data-id="{{ $shopifySkuId }}">
                        <i class="ri-upload-2-line"></i> Sync Inventory
                    </button>
                @endif
            </div>
        </div>

        @include('marketplace.alibaba._nav', ['active' => 'products'])

        @if(!empty($inventoryMismatch))
            <div class="alert alert-warning py-2 small mb-3">
                Inventory mismatch: Shopify <strong>{{ (int) $shopifyQtyShow }}</strong> vs Alibaba <strong>{{ (int) $mpQtyShow }}</strong>.
                Click <strong>Sync Inventory</strong> to push Shopify qty to Alibaba now.
            </div>
        @else
            <div class="alert alert-info py-2 small mb-3">
                <strong>Compare view.</strong> Shopify (source) vs Alibaba side by side.
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body py-3">
                        <div class="text-muted small">Shopify Qty</div>
                        <div class="fs-4 fw-semibold">{{ $shopifyQtyShow !== null ? $shopifyQtyShow : '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 {{ !empty($inventoryMismatch) ? 'border-warning' : '' }}">
                    <div class="card-body py-3">
                        <div class="text-muted small">Alibaba Qty</div>
                        <div class="fs-4 fw-semibold {{ !empty($inventoryMismatch) ? 'text-warning' : '' }}">
                            {{ $mpQtyShow !== null ? $mpQtyShow : '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($aeLiveError)
            <div class="alert alert-warning">{{ $aeLiveError }}</div>
        @endif

        @if(!$linked)
            <div class="alert alert-secondary small">
                This Shopify SKU is not linked in <code>alibaba_metrics</code> yet.
                Go to <a href="{{ route('marketplace.products', 'alibaba') }}">Listings</a> and click <strong>Sync Alibaba link map</strong> to match SKUs.
            </div>
        @endif

        @include('marketplace.alibaba._compare-row', [
            'rowTitle' => 'Listing details',
            'left' => $shopifyListingHtml,
            'right' => $aeListingHtml,
            'leftEmpty' => 'No Shopify listing data',
            'rightEmpty' => 'No Alibaba listing data — link SKU and pull from Alibaba',
        ])

        @php
            ob_start();
        @endphp
        @include('marketplace.alibaba._image-gallery', [
            'images' => $shopifyImages,
            'mainImage' => $s['main_image'] ?? null,
            'galleryId' => 'shopify-gallery',
        ])
        @php $shopifyImagesHtml = ob_get_clean(); ob_start(); @endphp
        @include('marketplace.alibaba._image-gallery', [
            'images' => $aeImages,
            'mainImage' => $ae['main_image'] ?? null,
            'galleryId' => 'ae-gallery',
        ])
        @php $aeImagesHtml = ob_get_clean(); @endphp

        @include('marketplace.alibaba._compare-row', [
            'rowTitle' => 'Images',
            'left' => $shopifyImagesHtml,
            'right' => $aeImagesHtml,
            'leftEmpty' => 'No Shopify images in catalog cache',
            'rightEmpty' => 'No Alibaba images — pull live product details',
        ])

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
                                        <td class="small text-muted">{{ $v['source'] ?? ($aeSource === 'api' ? 'alibaba' : 'shopify') }}</td>
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

        @php
            $shopifyProps = $s['properties'] ?? [];
            $aeProps = $ae['properties'] ?? [];
            $hasAttributes = !empty($shopifyProps) || !empty($aeProps) || !empty($ae['subjects']);
        @endphp

        @if($hasAttributes)
            @php ob_start(); @endphp
            @if(!empty($shopifyProps))
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Property</th><th>Value</th></tr></thead>
                    <tbody>
                        @foreach($shopifyProps as $prop)
                            <tr><td class="ps-3">{{ $prop['name'] }}</td><td>{{ $prop['value'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @php $shopifyAttrsHtml = ob_get_clean(); ob_start(); @endphp
            @if(!empty($ae['subjects']))
                <div class="small text-muted mb-2">Titles by language</div>
                <table class="table table-sm mb-3">
                    <thead class="table-light"><tr><th class="ps-3">Language</th><th>Title</th></tr></thead>
                    <tbody>
                        @foreach($ae['subjects'] as $sub)
                            <tr><td class="ps-3">{{ $sub['language'] ?? '—' }}</td><td>{{ $sub['subject'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if(!empty($aeProps))
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Property</th><th>Value</th></tr></thead>
                    <tbody>
                        @foreach($aeProps as $prop)
                            <tr><td class="ps-3">{{ $prop['name'] }}</td><td>{{ $prop['value'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @php $aeAttrsHtml = ob_get_clean(); @endphp

            @include('marketplace.alibaba._compare-row', [
                'rowTitle' => 'Attributes',
                'left' => $shopifyAttrsHtml,
                'right' => $aeAttrsHtml,
                'leftEmpty' => 'No Shopify attributes in catalog',
                'rightEmpty' => 'No Alibaba properties',
            ])
        @endif

        @php
            ob_start();
            if ($shopifyDescription) {
                if ($shopifyDescSource === 'shopify_catalog') {
                    echo '<div class="text-muted small mb-2">Source: Shopify catalog cache</div>';
                } elseif ($shopifyDescSource === 'product_master') {
                    echo '<div class="text-muted small mb-2">Source: Product Master (catalog cache empty)</div>';
                }
                echo '<div class="ae-description border rounded p-3 bg-white">'.$shopifyDescription.'</div>';
            }
            $shopifyDescHtml = ob_get_clean();

            ob_start();
            if (!empty($aeDescriptionBlocks)) {
                foreach ($aeDescriptionBlocks as $desc) {
                    if (!empty($desc['language'])) {
                        echo '<div class="text-muted small mb-2">Language: '.e($desc['language']).'</div>';
                    }
                    echo '<div class="ae-description border rounded p-3 bg-white">'.($desc['html'] ?? '').'</div>';
                }
            } elseif (!empty($l['bullet_points'])) {
                echo '<div class="text-muted small mb-2">Cached bullet points (no full description)</div>';
                echo '<pre class="mb-0 small border rounded p-3 bg-white">'.e($l['bullet_points']).'</pre>';
            }
            $aeDescHtml = ob_get_clean();
        @endphp

        @if(filled($shopifyDescHtml) || filled($aeDescHtml))
            @include('marketplace.alibaba._compare-row', [
                'rowTitle' => 'Description',
                'left' => $shopifyDescHtml,
                'right' => $aeDescHtml,
                'leftEmpty' => 'No Shopify description in catalog cache or Product Master',
                'rightEmpty' => 'No Alibaba description — pull live product details',
            ])
        @endif
    </div>
</div>

<script>
document.querySelectorAll('.ae-gallery-thumb').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
        var gid = this.getAttribute('data-gallery');
        var main = document.getElementById(gid + '-main');
        if (main) {
            main.src = this.getAttribute('data-src') || this.src;
        }
        document.querySelectorAll('.ae-gallery-thumb[data-gallery="' + gid + '"]').forEach(function (el) {
            el.classList.remove('active');
        });
        this.classList.add('active');
    });
});

document.getElementById('btn-pull-ae')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Pulling…';
    fetch('{{ url('marketplace/alibaba/products') }}/' + id + '/pull', {
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
        btn.innerHTML = '<i class="ri-download-cloud-line"></i> Pull from Alibaba';
    });
});

document.getElementById('btn-sync-inventory')?.addEventListener('click', function () {
    var btn = this;
    var id = btn.getAttribute('data-id');
    if (!id) return;
    if (!confirm('Push live Shopify quantity to Alibaba for this SKU now (no queue)?')) return;
    btn.disabled = true;
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Syncing…';
    fetch('{{ url('marketplace/alibaba/products') }}/' + id + '/sync-inventory', {
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
        else {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    })
    .catch(function () {
        alert('Request failed.');
        btn.disabled = false;
        btn.innerHTML = original;
    });
});
</script>
@endsection
