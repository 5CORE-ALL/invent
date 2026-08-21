@once
<script src="{{ asset('js/price-lt80-lmp-badge.js') }}?v=1"></script>
<script>
    window.PRICE_LT80_LMP_REPORT_URL = @json(route('price.lt80.lmp.report'));
</script>
@endonce
