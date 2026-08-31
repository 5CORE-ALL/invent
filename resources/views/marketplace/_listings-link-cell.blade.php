@php
    $linkedFlag = $linked ?? false;
    $listingStatusFlag = $listingStatus ?? '';
    $linkShopifySkuId = $shopifySkuId ?? null;
    $linkSku = $sku ?? '';
@endphp
<span class="d-inline-flex align-items-center flex-wrap gap-1 js-mm-link-cell" onclick="event.stopPropagation();">
@if($listingStatusFlag === 'not_in_shopify')
    <span class="badge bg-warning-subtle text-warning">Not in Shopify</span>
@elseif($linkedFlag)
    <span class="badge bg-success-subtle text-success">Linked</span>
@else
    <span class="badge bg-light text-muted">Not linked</span>
    @if(!empty($linkShopifySkuId))
        <button type="button"
            class="btn btn-sm btn-primary js-mm-link-sku py-0 px-2"
            data-id="{{ (int) $linkShopifySkuId }}"
            data-sku="{{ e($linkSku) }}"
            title="Link this Shopify SKU to the marketplace, then push inventory">
            <i class="ri-link"></i> Link
        </button>
    @endif
@endif
</span>
