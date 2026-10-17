{{-- Y Sales badge: yesterday's sales (server-rendered). Needs $salesYesterday and $yesterdayLabel. --}}
<span class="badge fs-6 p-2" id="y-sales-badge"
    style="background-color: #0dcaf0; color: black; font-weight: bold;"
    title="Yesterday's sales ({{ $yesterdayLabel ?? '' }} Pacific){{ isset($ySalesNote) ? ' — ' . $ySalesNote : '' }}">Y Sales: ${{ number_format((float) ($salesYesterday ?? 0), 2) }}</span>
