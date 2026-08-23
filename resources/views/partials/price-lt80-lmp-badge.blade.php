@php
    $pltBadgeId = $pltBadgeId ?? 'price-lt80-lmp-badge';
    $pltChannelKey = $pltChannelKey ?? '';
    $pltPriceField = $pltPriceField ?? '';
@endphp
<span class="badge fs-6 p-2 price-lt80-lmp-badge" id="{{ $pltBadgeId }}"
    data-plt-channel="{{ $pltChannelKey }}"
    data-plt-price-field="{{ $pltPriceField }}"
    style="background-color:#28a745;color:#fff;font-weight:700;cursor:pointer;"
    title="price &lt; 80% of LMP: SKUs where Price is more than 20% below LMP (purple triangle). Green = 0. Click to show only those rows.">
    <span class="summary-trend-dot none" title="Rolling history"></span><i class="fas fa-exclamation-triangle"></i> 0
</span>
@once
<script src="{{ asset('js/price-lt80-lmp-badge.js') }}?v=2"></script>
<script>
    window.PRICE_LT80_LMP_REPORT_URL = @json(route('price.lt80.lmp.report'));
</script>
@endonce
