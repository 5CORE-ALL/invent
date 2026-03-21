@php
    $wmsTabClass = function (string $routeName): string {
        return request()->routeIs($routeName)
            ? 'btn btn-sm btn-primary'
            : 'btn btn-sm btn-outline-secondary';
    };
@endphp
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <span class="text-muted small me-2 d-none d-md-inline">WMS</span>
        <a href="{{ route('wms.dashboard') }}" class="{{ $wmsTabClass('wms.dashboard') }}">Dashboard</a>
        <a href="{{ route('wms.structure') }}" class="{{ $wmsTabClass('wms.structure') }}">Structure</a>
        <a href="{{ route('wms.inventory') }}" class="{{ $wmsTabClass('wms.inventory') }}">By location</a>
        <a href="{{ route('wms.scan') }}" class="{{ $wmsTabClass('wms.scan') }}">Scan</a>
        <a href="{{ route('wms.pick') }}" class="{{ $wmsTabClass('wms.pick') }}">Pick</a>
        <a href="{{ route('wms.putaway') }}" class="{{ $wmsTabClass('wms.putaway') }}">Putaway</a>
        <a href="{{ route('wms.locate') }}" class="{{ $wmsTabClass('wms.locate') }}">Locate</a>
        <a href="{{ route('wms.movements') }}" class="{{ $wmsTabClass('wms.movements') }}">History</a>
    </div>
</div>
