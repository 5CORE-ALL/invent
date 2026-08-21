@php
    $lmpBadgeId = $lmpBadgeId ?? 'lmp-missing-badge';
    $lmpChannelKey = $lmpChannelKey ?? '';
@endphp
<span class="badge fs-6 p-2 lmp-missing-badge" id="{{ $lmpBadgeId }}"
    data-lmp-channel="{{ $lmpChannelKey }}"
    style="background-color:#28a745;color:#fff;font-weight:700;cursor:pointer;"
    title="LMP M.: SKUs with no LMP data. Green = 0 missing. Click to show only those rows.">
    LMP M. 0
</span>
@once
<script src="{{ asset('js/lmp-missing-badge.js') }}?v=1"></script>
<script>
    window.LMP_MISSING_REPORT_URL = @json(route('lmp.missing.report'));
</script>
@endonce
