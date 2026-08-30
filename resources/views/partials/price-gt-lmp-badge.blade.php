@php
    $pglBadgeId = $pglBadgeId ?? 'price-gt-lmp-badge';
    $pglChannelKey = $pglChannelKey ?? '';
    $pglPriceField = $pglPriceField ?? '';
@endphp
<span class="badge fs-6 p-2 price-gt-lmp-badge" id="{{ $pglBadgeId }}"
    data-pgl-channel="{{ $pglChannelKey }}"
    data-pgl-price-field="{{ $pglPriceField }}"
    style="background-color:#dc3545;color:#fff;font-weight:700;cursor:pointer;"
    data-metric="prc_gt_lmp_count" data-invert="1" data-live-value="0" data-format="number"
    title="Price &gt; LMP (red triangle), INV &gt; 0 only. Click badge to filter. Click dot for rolling history.">
    <span class="summary-trend-dot none" data-metric="prc_gt_lmp_count" title="Rolling history"></span><i class="fas fa-exclamation-triangle"></i> 0
</span>
@once
<script src="{{ asset('js/price-gt-lmp-badge.js') }}?v=6"></script>
@include('partials.sprice-lmp-cap-script')
<script>
    window.PRICE_GT_LMP_REPORT_URL = @json(route('price.gt.lmp.report'));
</script>
@endonce
