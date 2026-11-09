{{-- Y Sales badge: yesterday's sales (server-rendered). Needs $salesYesterday and $yesterdayLabel. Pass 'yhPage' (YesterdayBadgeRegistry key) to add the history dot + graph. --}}
<span class="badge fs-6 p-2" id="y-sales-badge"
    style="background-color: #0dcaf0; color: black; font-weight: bold;"
    title="Yesterday's sales ({{ $yesterdayLabel ?? '' }} Pacific){{ isset($ySalesNote) ? ' — ' . $ySalesNote : '' }}">Y Sales: ${{ number_format((float) ($salesYesterday ?? 0), 2) }}</span>
@isset($yhPage)
    @include('partials.y-badge-history', ['yhPage' => $yhPage, 'yhValue' => $salesYesterday ?? null])
@endisset
