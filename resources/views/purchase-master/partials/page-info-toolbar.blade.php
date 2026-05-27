@php
    $infoPageKey = $pageKey ?? \App\Services\PurchasePageInfoService::resolvePageKeyFromRoute();
@endphp
@if(!empty($infoPageKey))
<div class="page-info-toolbar-item d-inline-flex align-items-center align-self-center {{ $wrapperClass ?? '' }}">
    @include('purchase-master.partials.page-info-badge', ['pageKey' => $infoPageKey])
</div>
@endif
