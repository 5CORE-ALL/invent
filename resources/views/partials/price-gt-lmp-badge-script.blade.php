@once
<script src="{{ asset('js/price-gt-lmp-badge.js') }}?v=1"></script>
<script>
    window.PRICE_GT_LMP_REPORT_URL = @json(route('price.gt.lmp.report'));
</script>
@endonce
