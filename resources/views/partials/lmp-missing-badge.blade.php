@php
    $lmpBadgeId = $lmpBadgeId ?? 'lmp-missing-badge';
    $lmpChannelKey = $lmpChannelKey ?? '';
@endphp
<span class="badge fs-6 p-2 lmp-missing-badge" id="{{ $lmpBadgeId }}"
    data-lmp-channel="{{ $lmpChannelKey }}"
    data-metric="lmp_missing_count" data-invert="1" data-live-value="0" data-format="number"
    style="background-color:#28a745;color:#fff;font-weight:700;cursor:pointer;"
    title="LMP M.: SKUs with no LMP data. Green = 0 missing. Click badge to filter. Click dot for rolling history.">
    <span class="summary-trend-dot none" data-metric="lmp_missing_count" title="Rolling history"></span>LMP M. 0
</span>
@once
<style>
    .summary-trend-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 0.22rem;
        vertical-align: 0.08em;
        flex-shrink: 0;
        cursor: pointer;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
    }
    .summary-trend-dot.up { background: #22c55e; }
    .summary-trend-dot.down { background: #ef4444; }
    .summary-trend-dot.flat,
    .summary-trend-dot.none { background: #9ca3af; }
</style>
<script src="{{ asset('js/lmp-missing-badge.js') }}?v=3"></script>
<script>
    window.LMP_MISSING_REPORT_URL = @json(route('lmp.missing.report'));
</script>
@endonce
