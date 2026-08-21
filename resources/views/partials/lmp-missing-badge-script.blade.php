@once
<script src="{{ asset('js/lmp-missing-badge.js') }}?v=1"></script>
<script>
    window.LMP_MISSING_REPORT_URL = @json(route('lmp.missing.report'));
</script>
@endonce
