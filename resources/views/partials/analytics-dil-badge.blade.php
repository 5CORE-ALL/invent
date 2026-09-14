{{-- Dil% = Σ OV L30 ÷ Σ INV. Daily history dot + chart. --}}
@php
    $dilChannel = $dilChannel ?? 'unknown';
@endphp
<span class="badge analytics-dil-badge" id="analytics-dil-badge"
      data-dil-channel="{{ $dilChannel }}"
      data-metric="dil_ov_percent"
      data-format="pct"
      data-live-value="0"
      style="background-color:#fd7e14;color:#fff;font-weight:700;cursor:pointer;"
      title="Dil% = Σ OV L30 ÷ Σ INV × 100 (PARENT rows excluded). Click the dot for daily history.">
    <span class="summary-trend-dot none" data-metric="dil_ov_percent" title="Rolling history"></span>Dil%: 0%
</span>
@once
    @include('partials.lazy-chart-js')
    @include('partials.analytics-dil-badge-assets')
@endonce
