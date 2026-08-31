@php
    $linkedFlag = $linked ?? false;
    $listingStatusFlag = $listingStatus ?? '';
    $linkShopifySkuId = $shopifySkuId ?? null;
    $linkSku = $sku ?? '';
@endphp
@if($listingStatusFlag === 'not_in_shopify')
    <span class="badge bg-warning-subtle text-warning">Not in Shopify</span>
@elseif($linkedFlag)
    <span class="badge bg-success-subtle text-success">Linked</span>
@else
    <span class="badge bg-light text-muted">Not linked</span>
    @if(!empty($linkShopifySkuId))
        <button type="button"
            class="btn btn-sm btn-success js-mm-link-sku ms-1 py-0 px-1"
            data-id="{{ (int) $linkShopifySkuId }}"
            data-sku="{{ e($linkSku) }}"
            onclick="event.stopPropagation();"
            title="Match this Shopify SKU to the marketplace listing">
            <i class="ri-link"></i> Link
        </button>
    @endif
@endif
