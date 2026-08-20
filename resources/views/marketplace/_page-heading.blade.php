{{--
  Marketplace page heading with logo before the title.
  @param string $slug    Marketplace slug (aliexpress|alibaba|reverb)
  @param string $heading Page title text
  @param string $mt      Bootstrap margin-top class (default: mt-2)
  @param string $mb      Bootstrap margin-bottom class (default: mb-1)
--}}
@php
    $slug = strtolower($slug ?? '');
    $heading = $heading ?? '';
    $mt = $mt ?? 'mt-2';
    $mb = $mb ?? 'mb-1';
    $logoUrl = \App\Services\MarketplaceManager\MarketplaceManagerRegistry::logoUrl($slug);
@endphp
<div class="d-flex align-items-center gap-2 {{ $mt }} {{ $mb }}">
    @if($logoUrl)
        <img src="{{ $logoUrl }}" alt="{{ ucfirst($slug) }}" class="mm-mp-logo" width="32" height="32"
             style="width:32px;height:32px;object-fit:contain;flex-shrink:0;"
             onerror="this.style.display='none'">
    @endif
    <h4 class="mb-0">{{ $heading }}</h4>
    @if(str_contains(strtolower($heading), 'orders'))
        <div class="ms-auto">
            @include('marketplace._fetch-tracking-now', ['fetchTrackingMarketplace' => $slug])
        </div>
    @endif
</div>
