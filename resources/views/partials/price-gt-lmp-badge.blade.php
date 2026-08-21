@php
    $pglBadgeId = $pglBadgeId ?? 'price-gt-lmp-badge';
    $pglChannelKey = $pglChannelKey ?? '';
    $pglPriceField = $pglPriceField ?? '';
@endphp
<span class="badge fs-6 p-2 price-gt-lmp-badge" id="{{ $pglBadgeId }}"
    data-pgl-channel="{{ $pglChannelKey }}"
    data-pgl-price-field="{{ $pglPriceField }}"
    style="background-color:#28a745;color:#fff;font-weight:700;cursor:pointer;"
    title="price &gt;lmp: SKUs where Price &gt; LMP (red triangle). Green = 0. Click to show only those rows.">
    <i class="fas fa-exclamation-triangle"></i> 0
</span>
@once
<script src="{{ asset('js/price-gt-lmp-badge.js') }}?v=1"></script>
<script>
    window.PRICE_GT_LMP_REPORT_URL = @json(route('price.gt.lmp.report'));
</script>
@endonce
